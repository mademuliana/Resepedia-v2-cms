<?php

namespace App\Services\Calculations;

use App\Models\Product;

class ProductCalculator
{
    /** Recompute totals from product_recipe rows; returns [total_cost,total_calorie,price]. */
    public function recompute(Product $product, float $markup = 2.5, int $roundTo = 100): array
    {
        $product->loadMissing(['recipes']);

        $totalCost = 0.0;
        $totalCal  = 0.0;

        foreach ($product->recipes as $r) {
            $qty = (float) $r->pivot->quantity;
            $totalCost += $qty * (float) $r->total_cost_per_portion;
            $totalCal  += $qty * (float) $r->total_calorie_per_portion;
        }

        $basePrice = $totalCost * $markup;
        $price     = $roundTo > 0 ? (int) (round($basePrice / $roundTo) * $roundTo) : (int) round($basePrice);

        return [
            'total_cost'    => round($totalCost, 2),
            'total_calorie' => round($totalCal, 2),
            'price'         => $price,
        ];
    }
}
