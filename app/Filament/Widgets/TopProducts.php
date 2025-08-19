<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Support\Format;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Table;

class TopProducts extends BaseWidget
{
    protected static ?string $heading = 'Top Products';
    protected static ?int $sort = 20; // first row (right)

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Only products that appear in order_items; includes sums & ordering; limit(10)
                Product::query()->topOrdered(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Qty')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->state(fn ($record) => Format::idr($record->total_revenue ?? 0)),
            ])
            ->paginated(false);
    }
}
