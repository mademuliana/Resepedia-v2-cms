<?php

namespace App\Services\Calculations;

use App\Models\Product;

class OrderFormCalculator
{
    /**
     * @param array $rows [{product_id, quantity, ...}, ...]
     * @param bool $format Return "0.00" strings for UI when true
     * @return array{
     *   rows: array<int,array>,
     *   total_price: string|float,
     *   total_calorie: string|float
     * }
     */
    public function compute(array $rows, bool $format = true): array
    {
        $ids = collect($rows)->pluck('product_id')->filter()->unique()->values();

        $productMap = Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'price', 'total_calorie'])
            ->keyBy('id');

        $sumPrice = 0.0;
        $sumCal   = 0.0;
        $outRows  = [];

        foreach ($rows as $i => $row) {
            $pid = $row['product_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            $unitPrice = ($pid && $productMap->has($pid)) ? (float) $productMap[$pid]->price          : 0.0;
            $unitCal   = ($pid && $productMap->has($pid)) ? (float) $productMap[$pid]->total_calorie : 0.0;

            $rowPrice = $qty * $unitPrice;
            $rowCal   = $qty * $unitCal;

            $sumPrice += $rowPrice;
            $sumCal   += $rowCal;

            $outRows[$i] = array_merge($row, [
                'product_total_price'   => $format ? $this->fmt2($rowPrice) : $rowPrice,
                'product_total_calorie' => $format ? $this->fmt2($rowCal)   : $rowCal,
            ]);
        }

        return [
            'rows'          => array_values($outRows),
            'total_price'   => $format ? $this->fmt2($sumPrice) : $sumPrice,
            'total_calorie' => $format ? $this->fmt2($sumCal)   : $sumCal,
        ];
    }

    private function fmt2(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
