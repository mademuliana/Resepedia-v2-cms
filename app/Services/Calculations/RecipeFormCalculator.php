<?php

namespace App\Services\Calculations;

use App\Models\Ingredient;

class RecipeFormCalculator
{

    public function compute(array $rows, float|int|string|null $portion, bool $format = true): array
    {
        $portionF = (float) ($portion ?? 0);

        // batch-load used ingredients
        $ids = collect($rows)->pluck('ingredient_id')->filter()->unique()->values();
        $map = Ingredient::query()
            ->whereIn('id', $ids)
            ->get(['id', 'cost_per_unit', 'calorie_per_unit'])
            ->keyBy('id');

        $sumCost = 0.0;
        $sumCal  = 0.0;
        $out     = [];

        foreach ($rows as $i => $row) {
            $id  = $row['ingredient_id'] ?? null;
            $qty = (float) ($row['quantity'] ?? 0);

            $cpu = ($id && $map->has($id)) ? (float) $map[$id]->cost_per_unit : 0.0;
            $kpu = ($id && $map->has($id)) ? (float) $map[$id]->calorie_per_unit : 0.0;

            $rowCost = $qty * $cpu;
            $rowCal  = $qty * $kpu;

            $sumCost += $rowCost;
            $sumCal  += $rowCal;

            $out[$i] = array_merge($row, [
                'ingredient_total_cost'    => $format ? $this->fmt2($rowCost) : $rowCost,
                'ingredient_total_calorie' => $format ? $this->fmt2($rowCal)  : $rowCal,
            ]);
        }

        $costPerPortion = $portionF > 0 ? $sumCost / $portionF : 0.0;
        $calPerPortion  = $portionF > 0 ? $sumCal  / $portionF : 0.0;

        return [
            'rows'                      => array_values($out),
            'total_cost_per_portion'    => $format ? $this->fmt2($costPerPortion) : $costPerPortion,
            'total_calorie_per_portion' => $format ? $this->fmt2($calPerPortion)  : $calPerPortion,
            'sum_cost'                  => $format ? $this->fmt2($sumCost)        : $sumCost,
            'sum_calorie'               => $format ? $this->fmt2($sumCal)         : $sumCal,
        ];
    }

    private function fmt2(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
