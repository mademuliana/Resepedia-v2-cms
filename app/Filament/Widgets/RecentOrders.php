<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Support\Format;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Table;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';
    protected static ?int $sort = 10; // first row (left)

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Builder handles: must have items, latest, limit(10)
                Order::query()->recentWithItems(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->state(fn ($record) => Format::idr($record->total_price)),

                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Ordered')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Order $record) => \App\Filament\Resources\OrderResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
