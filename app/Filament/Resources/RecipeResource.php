<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecipeResource\Pages;
use App\Models\Recipe;
use App\Models\Ingredient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use App\Support\Format;
use App\Services\Calculations\RecipeFormCalculator;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->extraAttributes([
            'onkeydown' => 'if(event.key === "Enter"){ event.preventDefault(); }',
        ])->schema([
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
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('prep_time_minutes')
                        ->required()
                        ->numeric()
                        ->default(0),
                ]),

            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('portion_size')
                        ->required()
                        ->numeric()
                        ->default(0.00)
                        ->reactive()
                        ->debounce(300)
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            // Recompute from the latest full repeater snapshot
                            self::computeRowsAndTotals($get('ingredients') ?? [], $set, $get);
                        }),

                    Forms\Components\TextInput::make('total_cost_per_portion')
                        ->disabled()
                        ->prefix('Rp.')
                        ->default(0.00)
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('total_calorie_per_portion')
                        ->disabled()
                        ->suffix('kcal')
                        ->default(0.00)
                        ->dehydrated(false),
                ]),

            Forms\Components\Grid::make(1)
                ->schema([
                    Forms\Components\Repeater::make('ingredients')
                        ->relationship('ingredients')
                        ->reactive()                // ensure changes inside trigger this repeater's afterStateUpdated
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
                            Forms\Components\Select::make('ingredient_id')
                                ->label('Ingredient')
                                ->options(Ingredient::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->reactive(), // no per-field afterStateUpdated here (avoid race)

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->live(debounce: 300)
                                ->default(0.00)
                                ->suffix(function (callable $get) {
                                    $ingredientId = $get('ingredient_id');
                                    if ($ingredientId) {
                                        $ingredient = \App\Models\Ingredient::find($ingredientId);
                                        return $ingredient ? $ingredient->unit : null;
                                    }
                                    return null;
                                }),

                            Forms\Components\TextInput::make('ingredient_total_cost')
                                ->numeric()
                                ->label('Cost')
                                ->prefix('Rp.')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('ingredient_total_calorie')
                                ->numeric()
                                ->label('Calorie')
                                ->suffix('kcal')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(4)
                        ->createItemButtonLabel('Add Ingredient')
                        ->default([]),
                ]),
            Forms\Components\Section::make('Steps')
                ->description('Ordered instructions for preparing the recipe.')
                ->schema([
                    Forms\Components\Repeater::make('steps')
                        ->relationship('steps')           // hasMany(RecipeStep::class)
                        ->defaultItems(0)
                        ->reorderable()                   // drag & drop order in UI
                        ->itemLabel(fn(array $state): ?string => isset($state['step_no']) ? 'Step ' . $state['step_no'] : null)
                        ->columns(12)
                        ->schema([
                            Forms\Components\TextInput::make('step_no')
                                ->label('No.')
                                ->numeric()
                                ->minValue(1)
                                ->disabled()             // we auto-assign based on order
                                ->dehydrated(true)       // still save to DB
                                ->columnSpan(2),

                            Forms\Components\Textarea::make('instruction')
                                ->required()
                                ->rows(2)
                                ->columnSpan(6),

                            Forms\Components\TextInput::make('duration_minutes')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->suffix('min')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('media_url')
                                ->url()
                                ->placeholder('https://...')
                                ->nullable()
                                ->columnSpan(2),
                        ])

                        // Normalize numbering whenever hydrated/changed/reordered:
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            $rows = $get('steps') ?? [];
                            $n = 1;
                            foreach ($rows as $key => $row) {
                                $rows[$key]['step_no'] = $n++;
                            }
                            $set('steps', $rows);
                        })
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $rows = $get('steps') ?? [];
                            $n = 1;
                            foreach ($rows as $key => $row) {
                                $rows[$key]['step_no'] = $n++;
                            }
                            $set('steps', $rows);
                        })


                        // Extra safety: before saving each row, trim and coerce types
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                            $data['instruction'] = trim((string) ($data['instruction'] ?? ''));
                            $data['duration_minutes'] = $data['duration_minutes'] !== null ? (int) $data['duration_minutes'] : null;
                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                            $data['instruction'] = trim((string) ($data['instruction'] ?? ''));
                            $data['duration_minutes'] = $data['duration_minutes'] !== null ? (int) $data['duration_minutes'] : null;
                            return $data;
                        }),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('prep_time_minutes')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => Format::minute($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('portion_size')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => Format::pcs($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_calorie_per_portion')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => Format::kcal($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_cost_per_portion')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => Format::idr($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('ingredients_list')
                    ->label('Ingredients')
                    ->getStateUsing(
                        fn($record) => $record->ingredients
                            ->map(fn($item) => $item->name . ' (' . $item->pivot->quantity . ' ' . $item->unit . ')')
                            ->implode('<br>')
                    )
                    ->html()      // allow <br>
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
                        // optional: keep in session as fallback
                        session()->put('recipes.table.selected', $ids);
                        // go to the report page with ids
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecipes::route('/'),
            'create' => Pages\CreateRecipe::route('/create'),
            'edit' => Pages\EditRecipe::route('/{record}/edit'),
            'selection' => Pages\RecipeSelectionDetails::route('/selection-details'),
            'view' => Pages\DetailRecipe::route('/{record}'),
        ];
    }

    /* ============================================================
     *                 Calculation inside the Resource
     * ============================================================
     */

    /**
     * Compute row-level totals and overall per-portion totals from a full repeater snapshot.
     * Writes back the normalized repeater state once to avoid race/flicker.
     */
    /**
     * Single source of truth for form-side recompute.
     * Keeps the same signature so your existing calls don't change.
     */
    protected static function computeRowsAndTotals(array $rows, Set $set, Get $get): void
    {
        $portion = (float) ($get('portion_size') ?? 0);

        /** @var RecipeFormCalculator $svc */
        $svc  = app(RecipeFormCalculator::class);
        $calc = $svc->compute($rows, $portion, true); // formatted strings for UI

        // Write back exactly like before (minimizes UI bugs/race conditions)
        $set('ingredients', $calc['rows']);
        $set('total_cost_per_portion',    $calc['total_cost_per_portion']);
        $set('total_calorie_per_portion', $calc['total_calorie_per_portion']);
    }

}
