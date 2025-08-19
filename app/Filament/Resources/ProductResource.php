<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Recipe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use App\Support\Format;
use App\Services\Calculations\ProductFormCalculator;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(1)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Company')
                        ->relationship('company', 'name')
                        ->required()
                        ->visible(fn() => auth()->user()?->isSuperAdmin()) // admins don’t see this
                        ->live() // so dependent fields can react
                        ->afterStateUpdated(function (Set $set) {
                            // reset dependent selects when company changes
                            foreach (['customer_id', 'address_id', 'courier_id'] as $field) {
                                if (property_exists((object)[], $field)) {
                                } // no-op; keep static analyzer happy
                                $set($field, null);
                            }
                        }),
                ]),
            Forms\Components\Grid::make(4)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('price')
                        ->numeric()
                        ->required()
                        ->prefix('Rp.')
                        ->default(0),

                    Forms\Components\TextInput::make('total_cost')
                        ->disabled()
                        ->required()
                        ->prefix('Rp.')
                        ->default(0)
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('total_calorie')
                        ->disabled()
                        ->required()
                        ->suffix('kcal')
                        ->default(0)
                        ->dehydrated(false),
                ]),

            Forms\Components\Grid::make(1)
                ->schema([
                    Forms\Components\Repeater::make('recipes')
                        ->relationship('recipes')
                        ->reactive()
                        ->debounce(300)
                        ->afterStateHydrated(function (Set $set, ?array $state, Get $get) {
                            self::computeRowsAndTotals($state ?? [], $set, $get);
                        })
                        ->afterStateUpdated(function (?array $state, Set $set, Get $get) {
                            self::computeRowsAndTotals($state ?? [], $set, $get);
                        })
                        ->schema([
                            Forms\Components\Select::make('recipe_id')
                                ->label('Recipe')
                                ->options(Recipe::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->reactive(),

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->live(debounce: 300),

                            Forms\Components\TextInput::make('recipe_total_cost')
                                ->disabled()
                                ->label('Cost')
                                ->prefix('Rp.')
                                ->default(0),

                            Forms\Components\TextInput::make('recipe_total_calorie')
                                ->disabled()
                                ->label('Calorie')
                                ->suffix('kcal')
                                ->default(0),
                        ])
                        ->columns(4)
                        ->createItemButtonLabel('Add Recipe'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => Format::idr($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_cost')
                    ->formatStateUsing(fn ($state) => Format::idr($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_calorie')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Format::kcal($state))
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('recipes_list')
                    ->label('Recipes')
                    ->getStateUsing(
                        fn($record) => $record->recipes
                            ->map(fn($item) => $item->name . ' (' . $item->pivot->quantity . 'ps)')
                            ->implode('<br>')
                    )
                    ->html()      // allow <br>
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('selectionDetails')
                    ->label('View selection details')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->all();
                        if (empty($ids)) return;

                        // (optional) keep in session as a fallback
                        session()->put('products.table.selected', $ids);

                        // redirect to the report page with ids
                        return redirect(
                            static::getUrl('selection', ['ids' => implode(',', $ids)])
                        );
                    }),

                Tables\Actions\DeleteBulkAction::make()
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'selection' => Pages\ProductSelectionDetails::route('/selection-details'),
            'view' => Pages\DetailProduct::route('/{record}'),
        ];
    }

    /* ============================================================
     *                 Calculation inside the Resource
     * ============================================================
     */

    /**
     * Compute row-level totals (recipe_total_cost/calorie) and aggregate product totals
     * from the full repeater snapshot. Write back once to avoid race/flicker.
     */
    protected static function computeRowsAndTotals(array $rows, Set $set, Get $get): void
    {
        /** @var ProductFormCalculator $svc */
        $svc  = app(ProductFormCalculator::class);
        $calc = $svc->compute($rows, true); // formatted strings for UI

        // Keep your original set() pattern to avoid UI race conditions
        $set('recipes', $calc['rows']);
        $set('total_cost',    $calc['total_cost']);
        $set('total_calorie', $calc['total_calorie']);
    }
}
