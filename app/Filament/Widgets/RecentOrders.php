<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrders extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';
    protected static ?int $sort = 10;

    public function getColumnSpan(): string|int|array
    {
        return 1; // half width on lg+ (since dashboard grid is 2 columns)
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->whereHas('products')
                    ->latest('ordered_at')
                    ->latest('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'Rp.' . number_format((float) $state, 2, '.', ',')),
                Tables\Columns\TextColumn::make('ordered_at')
                    ->since()
                    ->label('Ordered'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Open')
                    ->url(fn (Order $record) => OrderResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
}
