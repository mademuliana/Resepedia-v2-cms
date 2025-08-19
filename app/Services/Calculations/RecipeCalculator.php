<?php

namespace App\Services\Calculations;

use App\Models\Recipe;

class RecipeCalculator
{
    /** Recompute per-portion totals from recipe_ingredient rows. */
    public function recompute(Recipe $recipe): array
    {
        $recipe->loadMissing(['ingredients']);

        $totalCost = 0.0;
        $totalCal  = 0.0;

        foreach ($recipe->ingredients as $ing) {
            $totalCost += (float) $ing->pivot->ingredient_total_cost;
            $totalCal  += (float) $ing->pivot->ingredient_total_calorie;
        }

        $portion = max(0, (float) $recipe->portion_size);
        $perCost = $portion > 0 ? $totalCost / $portion : 0;
        $perCal  = $portion > 0 ? $totalCal  / $portion : 0;

        return [
            'total_cost_per_portion'    => round($perCost, 2),
            'total_calorie_per_portion' => round($perCal, 2),
            'sum_cost'                  => round($totalCost, 2),
            'sum_calorie'               => round($totalCal, 2),
        ];
    }
}
