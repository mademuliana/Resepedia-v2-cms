<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\DatePicker::make('order_date')
                            ->required()
                            ->default(now()),
                    ]),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('total_price')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        Forms\Components\TextInput::make('total_calorie')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ]),
                Forms\Components\Grid::make(1)
                    ->schema([
                        Forms\Components\Repeater::make('products')
                            ->relationship('products')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1),

                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\TextInput::make('total_calorie')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->createItemButtonLabel('Add Product'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->money('idr', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_calorie')
                    ->sortable(),

                Tables\Columns\TextColumn::make('products_list')
                    ->label('Products')
                    ->getStateUsing(function ($record) {
                        return $record->products
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
