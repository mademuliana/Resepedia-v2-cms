<?php

namespace App\Services\Analytics;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalytics
{
    /** Paid amounts per day in [start,end], optionally filtered by company_id via order relation. */
    public function paidByDay(Carbon $start, Carbon $end, ?int $companyId = null): array
    {
        $q = Payment::query()
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as paid_total')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()]);

        if ($companyId) {
            $q->whereHas('order', fn ($oq) => $oq->where('company_id', $companyId));
        }

        $rows = $q->groupBy(DB::raw('DATE(paid_at)'))
                  ->orderBy(DB::raw('DATE(paid_at)'))
                  ->get()
                  ->keyBy('d');

        $labels = [];
        $data   = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('M j');
            $data[]   = (float) ($rows[$key]->paid_total ?? 0);
        }

        return compact('labels', 'data');
    }
}
