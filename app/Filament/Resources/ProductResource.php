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

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form->schema([
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
                            // Normalize and compute once on load/open
                            self::computeRowsAndTotals($state ?? [], $set, $get);
                        })
                        ->afterStateUpdated(function (?array $state, Set $set, Get $get) {
                            // Single source of truth: recompute using ENTIRE repeater state snapshot
                            self::computeRowsAndTotals($state ?? [], $set, $get);
                        })
                        ->schema([
                            Forms\Components\Select::make('recipe_id')
                                ->label('Recipe')
                                ->options(Recipe::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->reactive(), // no per-field afterStateUpdated (avoid race)

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->reactive(), // changes here trigger the repeater hook

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
                    ->formatStateUsing(function ($state) {
                        return 'Rp.' . $state;
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_cost')
                    ->formatStateUsing(function ($state) {
                        return 'Rp.' . $state;
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_calorie')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return $state . 'kcal';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('recipes_list')
                    ->label('Recipes')
                    ->getStateUsing(fn ($record) => $record->recipes
                    ->map(fn($item) => $item->name . ' (' . $item->pivot->quantity . 'ps)')
                        ->implode('<br>')
                    )
                    ->html()      // allow <br>
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
        // Batch-load needed recipes
        $ids = collect($rows)->pluck('recipe_id')->filter()->unique()->values();

        $recipeMap = Recipe::query()
            ->whereIn('id', $ids)
            ->get(['id', 'total_cost_per_portion', 'total_calorie_per_portion'])
            ->keyBy('id');

        $totalCost = 0.0;
        $totalCal  = 0.0;
        $normalized = [];

        foreach ($rows as $i => $row) {
            $rid = $row['recipe_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            $costPerPortion = ($rid && $recipeMap->has($rid))
                ? (float) $recipeMap[$rid]->total_cost_per_portion
                : 0.0;

            $calPerPortion = ($rid && $recipeMap->has($rid))
                ? (float) $recipeMap[$rid]->total_calorie_per_portion
                : 0.0;

            $rowCost = $qty * $costPerPortion;
            $rowCal  = $qty * $calPerPortion;

            $totalCost += $rowCost;
            $totalCal  += $rowCal;

            // Normalize row (UI-only fields)
            $normalized[$i] = array_merge($row, [
                'recipe_total_cost'     => self::fmt($rowCost),
                'recipe_total_calorie'  => self::fmt($rowCal),
            ]);
        }

        // Single write for the entire repeater to keep UI in sync
        $set('recipes', array_values($normalized));

        // Top-level totals (display-only as per your form: dehydrated(false))
        $set('total_cost',    self::fmt($totalCost));
        $set('total_calorie', self::fmt($totalCal));
    }

    protected static function fmt(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
