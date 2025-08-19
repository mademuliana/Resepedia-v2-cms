<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'Sales (Paid Amount) — Last 30 Days';
    protected static ?int $sort = -100;

    /** Make this widget span the full row (standalone graph) */
    public function getColumnSpan(): string|int|array
    {
        return 'full';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $end   = Carbon::today();
        $start = $end->copy()->subDays(29);

        // Fetch paid amounts per day from payments (true cash-in)
        $rows = Payment::query()
            ->selectRaw("DATE(paid_at) as d, SUM(amount) as paid_total")
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()])
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->orderBy(DB::raw('DATE(paid_at)'))
            ->get()
            ->keyBy('d');

        // Normalize labels & dataset for all 30 days
        $labels = [];
        $data   = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $data[] = (float) ($rows[$key]->paid_total ?? 0);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Paid (IDR)',
                    'data'  => $data,
                    // no custom colors/styles per your preference; defaults are fine
                ],
            ],
        ];
    }
}
