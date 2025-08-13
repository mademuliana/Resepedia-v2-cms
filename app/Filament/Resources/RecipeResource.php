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

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->extraAttributes([
            'onkeydown' => 'if(event.key === "Enter"){ event.preventDefault(); }',
        ])->schema([
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
                                ->reactive()
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
                    ->formatStateUsing(function ($state) {
                        return $state . ' minute';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('portion_size')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        return $state . ' pcs';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_calorie_per_portion')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        return $state . ' kcal';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_cost_per_portion')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        return 'Rp.' . $state;
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('ingredients_list')
                    ->label('Ingredients')
                    ->getStateUsing(fn ($record) => $record->ingredients
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
    protected static function computeRowsAndTotals(array $rows, Set $set, Get $get): void
    {
        // Batch-load ingredients used in rows
        $ids = collect($rows)->pluck('ingredient_id')->filter()->unique()->values();

        $ingredientMap = Ingredient::query()
            ->whereIn('id', $ids)
            ->get(['id', 'cost_per_unit', 'calorie_per_unit'])
            ->keyBy('id');

        $totalCost = 0.0;
        $totalCal  = 0.0;

        $normalized = [];

        foreach ($rows as $i => $row) {
            $id  = $row['ingredient_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            $costPerUnit = ($id && $ingredientMap->has($id)) ? (float) $ingredientMap[$id]->cost_per_unit : 0.0;
            $calPerUnit  = ($id && $ingredientMap->has($id)) ? (float) $ingredientMap[$id]->calorie_per_unit : 0.0;

            $rowCost = $qty * $costPerUnit;
            $rowCal  = $qty * $calPerUnit;

            $totalCost += $rowCost;
            $totalCal  += $rowCal;

            // Normalize row (UI-only fields)
            $normalized[$i] = array_merge($row, [
                'ingredient_total_cost'     => self::fmt($rowCost),
                'ingredient_total_calorie'  => self::fmt($rowCal),
            ]);
        }

        // Single write for the entire repeater to keep UI consistent
        $set('ingredients', array_values($normalized));

        // Per-portion totals (if portion_size <= 0, keep 0 to match your previous behavior)
        $portion = (float) ($get('portion_size') ?? 0);
        $costPerPortion = $portion > 0 ? ($totalCost / $portion) : 0.0;
        $calPerPortion  = $portion > 0 ? ($totalCal  / $portion) : 0.0;

        $set('total_cost_per_portion',    self::fmt($costPerPortion));
        $set('total_calorie_per_portion', self::fmt($calPerPortion));
    }

    protected static function fmt(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
