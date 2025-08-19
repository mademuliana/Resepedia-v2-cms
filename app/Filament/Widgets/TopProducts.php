<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProducts extends BaseWidget
{
    protected static ?string $heading = 'Most Ordered Products';
    protected static ?int $sort = 30;

    public function getColumnSpan(): string|int|array
    {
        return 1;
    }

    public function table(Table $table): Table
    {
        $query = Product::query()
            ->withSum('orders as total_qty', 'order_items.quantity')
            ->withSum('orders as total_revenue', 'order_items.product_total_price')
            ->having('total_qty', '>', 0)                 // ← hide unused products
            ->orderByDesc('total_revenue')
            ->limit(10);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('total_qty')->label('Qty')->numeric(),
                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->formatStateUsing(fn ($state) => 'Rp.' . number_format((float) $state, 2, '.', ',')),
            ])
            ->paginated(false);
    }
}
