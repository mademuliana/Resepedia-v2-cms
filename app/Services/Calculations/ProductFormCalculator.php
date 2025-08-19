<?php

namespace App\Services\Calculations;

use App\Models\Recipe;

class ProductFormCalculator
{
    /**
     * @param array $rows [{recipe_id, quantity, ...}, ...]
     * @param bool $format Return 2-dec strings for UI when true
     * @return array{
     *   rows: array<int,array>,
     *   total_cost: string|float,
     *   total_calorie: string|float
     * }
     */
    public function compute(array $rows, bool $format = true): array
    {
        $ids = collect($rows)->pluck('recipe_id')->filter()->unique()->values();

        $recipeMap = Recipe::query()
            ->whereIn('id', $ids)
            ->get(['id', 'total_cost_per_portion', 'total_calorie_per_portion'])
            ->keyBy('id');

        $totalCost = 0.0;
        $totalCal  = 0.0;
        $outRows   = [];

        foreach ($rows as $i => $row) {
            $rid = $row['recipe_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            $costPer = ($rid && $recipeMap->has($rid))
                ? (float) $recipeMap[$rid]->total_cost_per_portion
                : 0.0;

            $calPer = ($rid && $recipeMap->has($rid))
                ? (float) $recipeMap[$rid]->total_calorie_per_portion
                : 0.0;

            $rowCost = $qty * $costPer;
            $rowCal  = $qty * $calPer;

            $totalCost += $rowCost;
            $totalCal  += $rowCal;

            $outRows[$i] = array_merge($row, [
                'recipe_total_cost'     => $format ? $this->fmt2($rowCost) : $rowCost,
                'recipe_total_calorie'  => $format ? $this->fmt2($rowCal)  : $rowCal,
            ]);
        }

        return [
            'rows'         => array_values($outRows),
            'total_cost'   => $format ? $this->fmt2($totalCost)   : $totalCost,
            'total_calorie'=> $format ? $this->fmt2($totalCal)    : $totalCal,
        ];
    }

    private function fmt2(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
