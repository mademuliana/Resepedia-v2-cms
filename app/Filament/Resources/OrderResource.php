<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

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

                        Forms\Components\DatePicker::make('customer_phone')
                            ->label('Customer Phone Number')
                            ->required()
                            ->default(now()),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('total_price')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp.')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_calorie')
                            ->numeric()
                            ->default(0)
                            ->suffix('kcal')
                            ->disabled(),
                    ]),

                Forms\Components\Grid::make(1)
                    ->schema([
                        Forms\Components\Repeater::make('products')
                            ->relationship('products')
                            ->reactive()
                            ->debounce(300)
                            ->afterStateHydrated(function (Set $set, ?array $state, Get $get) {
                                // Normalize & compute once on load/open
                                self::computeRowsAndTotals($state ?? [], $set, $get);
                            })
                            ->afterStateUpdated(function (?array $state, Set $set, Get $get) {
                                // Single source of truth: recompute using ENTIRE repeater snapshot
                                self::computeRowsAndTotals($state ?? [], $set, $get);
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive(), // no per-field afterStateUpdated (avoid race)

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->reactive(),

                                Forms\Components\TextInput::make('product_total_price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('Rp.')
                                    ->default(0),

                                Forms\Components\TextInput::make('product_total_calorie')
                                    ->label('Calorie')
                                    ->numeric()
                                    ->suffix('kcal')
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
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession()
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_price')
                    ->money('idr', true)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_calorie')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('products_list')
                    ->label('Products')
                    ->getStateUsing(fn ($record) => $record->products
                        ->map(fn ($item) => e($item->name) . " ({$item->pivot->quantity}ps)")
                        ->implode('<br>')
                    )
                    ->html()      // allow <br>
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false)


            ])
            ->actions([
                Action::make('details')
                ->label('Details')
                ->icon('heroicon-o-eye')
                ->url(fn ($record) => static::getUrl('details', ['record' => $record]))
                ->openUrlInNewTab(false),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Keep only what you need — Delete example:
                Tables\Actions\DeleteBulkAction::make()
                    ->deselectRecordsAfterCompletion()
                ,
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
            'details' => Pages\OrderDetails::route('/{record}/details'),
        ];
    }

    /* ============================================================
     *                 Calculation inside the Resource
     * ============================================================
     */

    /**
     * Compute row totals (product_total_price/calorie) and aggregate order totals
     * from the full repeater snapshot. Write back once to avoid race/flicker.
     */
    protected static function computeRowsAndTotals(array $rows, Set $set, Get $get): void
    {
        // Batch-load products used in rows
        $ids = collect($rows)->pluck('product_id')->filter()->unique()->values();

        $productMap = Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'price', 'total_calorie'])
            ->keyBy('id');

        $totalPrice = 0.0;
        $totalCal   = 0.0;
        $normalized = [];

        foreach ($rows as $i => $row) {
            $pid = $row['product_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            $unitPrice = ($pid && $productMap->has($pid))
                ? (float) $productMap[$pid]->price
                : 0.0;

            $unitCal = ($pid && $productMap->has($pid))
                ? (float) $productMap[$pid]->total_calorie
                : 0.0;

            $rowPrice = $qty * $unitPrice;
            $rowCal   = $qty * $unitCal;

            $totalPrice += $rowPrice;
            $totalCal   += $rowCal;

            // Normalize row fields (keep existing names)
            $normalized[$i] = array_merge($row, [
                'product_total_price'    => self::fmt($rowPrice),
                'product_total_calorie'  => self::fmt($rowCal),
            ]);
        }

        // Single write: keep UI consistent
        $set('products', array_values($normalized));

        // Top-level totals
        $set('total_price',   self::fmt($totalPrice));
        $set('total_calorie', self::fmt($totalCal));
    }

    protected static function fmt(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
