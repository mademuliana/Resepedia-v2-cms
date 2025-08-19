<?php

namespace App\Filament\Widgets;

use App\Services\Analytics\SalesAnalytics;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'Sales (Last 30 Days)';
    protected static ?int $sort = -100;          // keep at the very top
    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $end   = now();
        $start = now()->subDays(29);

        // Admins are scoped by global scope; super admin sees all (companyId = null)
        $companyId = auth()->user()?->company_id ?: null;

        $series = app(SalesAnalytics::class)->paidByDay($start, $end, $companyId);

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => 'Paid Amount',
                    'data'  => $series['data'],
                    // no explicit colors (keeps theme defaults)
                    'tension' => 0.3,
                    'pointRadius' => 2,
                    'borderWidth' => 2,
                    'fill' => false,
                ],
            ],
        ];
    }
}
