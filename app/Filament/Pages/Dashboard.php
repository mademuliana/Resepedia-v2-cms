<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title           = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\SalesChart::class,

            \App\Filament\Widgets\WideAccountWidget::class,
            // Lists (2 per row via page columns below)
            \App\Filament\Widgets\RecentOrders::class,
            \App\Filament\Widgets\TopProducts::class,

            \App\Filament\Widgets\TopIngredients::class,
            \App\Filament\Widgets\TopRecipes::class,
        ];
    }

    public function getColumns(): int|array
    {
        // 1 column on small screens, 2 on large
        return [
            'sm' => 1,
            'lg' => 2,
        ];
    }
}
