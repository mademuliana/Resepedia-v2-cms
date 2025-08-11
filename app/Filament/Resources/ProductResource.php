<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Recipe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('price')
                        ->numeric()
                        ->required()
                        ->default(0),

                    Forms\Components\TextInput::make('total_calorie')
                        ->numeric()
                        ->required()
                        ->default(0),
                ]),
            Forms\Components\Grid::make(1)
                ->schema([
                    Forms\Components\Repeater::make('recipes')
                        ->relationship('recipes')
                        ->schema([
                            Forms\Components\Select::make('recipe_id')
                                ->label('Recipe')
                                ->options(Recipe::all()->pluck('name', 'id'))
                                ->searchable()
                                ->required(),

                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->default(1),

                            Forms\Components\TextInput::make('total_calorie')
                                ->numeric()
                                ->default(0),

                            Forms\Components\TextInput::make('price')
                                ->numeric()
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
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('price')
                    ->money('idr', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_calorie')
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipes_list')
                    ->label('Recipes')
                    ->getStateUsing(function ($record) {
                        return $record->recipes
                            ->map(fn($item) => $item->name . ' (' . $item->pivot->quantity . 'ps)')
                            ->join(', ');
                    })
                    ->wrap(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
        ];
    }
}
