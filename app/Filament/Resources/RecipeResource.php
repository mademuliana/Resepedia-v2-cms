<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecipeResource\Pages;
use App\Models\Recipe;
use App\Models\Ingredient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Helpers\CalculationHelper;
use Filament\Support\RawJs;

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
                        ->debounce(200)
                        ->afterStateUpdated(CalculationHelper::recalculateRecipeTotals([
                            'ingredients' => 'ingredients',
                            'total_calorie_per_portion' => 'total_calorie_per_portion',
                            'total_price_per_portion' => 'total_price_per_portion',
                            'portion' => 'portion_size',
                        ])),

                    Forms\Components\TextInput::make('total_price_per_portion')
                        ->disabled()
                        ->numeric()
                        ->default(0.00)
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('total_calorie_per_portion')
                        ->disabled()
                        ->numeric()
                        ->default(0.00)
                        ->dehydrated(false),


                ]),

            Forms\Components\Grid::make(1)
                ->schema([
                    Forms\Components\Repeater::make('ingredients')
                        ->relationship('ingredients')
                        ->reactive() // so changes inside trigger afterStateUpdated
                        ->debounce(200)
                        ->afterStateUpdated(CalculationHelper::recalculateRecipeTotals([
                            'ingredients' => 'ingredients',
                            'total_calorie' => 'total_calorie',
                            'total_price' => 'total_price',
                            'quantity' => 'quantity',
                            'total_calorie_per_portion' => 'total_calorie_per_portion',
                            'total_price_per_portion' => 'total_price_per_portion',
                            'portion' => 'portion_size',
                        ]))
                        ->schema([
                            Forms\Components\Select::make('ingredient_id')
                                ->label('Ingredient')
                                ->options(Ingredient::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(CalculationHelper::updateIngridientTotal([
                                    'ingredient' => 'ingredient_id',
                                    'total_calorie' => 'total_calorie',
                                    'total_price' => 'total_price',
                                    'quantity' => 'quantity',
                                ]))
                                ,

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->reactive()
                                ->debounce(200)
                                ->default(0.00)
                                ->afterStateUpdated(CalculationHelper::updateIngridientTotal([
                                    'ingredient' => 'ingredient_id',
                                    'total_calorie' => 'total_calorie',
                                    'total_price' => 'total_price',
                                    'quantity' => 'quantity',
                                ])),

                            Forms\Components\TextInput::make('total_price')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('total_calorie')
                                ->numeric()
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
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('prep_time_minutes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('portion_size')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_calorie_per_portion')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price_per_portion')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ingredients_list')
                    ->label('Ingredients')
                    ->getStateUsing(function ($record) {
                        return $record->ingredients
                            ->map(fn($ingredient) => $ingredient->name . ' (' . $ingredient->pivot->quantity . ' ' . $ingredient->unit . ')')
                            ->join(', ');
                    })
                    ->wrap() // makes it break into lines if too long
                    ->sortable(false)
                    ->searchable(),
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
}
