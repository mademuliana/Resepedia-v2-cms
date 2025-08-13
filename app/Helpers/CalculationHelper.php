<?php

namespace App\Helpers;

use App\Models\Ingredient;
use App\Models\Recipe;

class CalculationHelper
{
    /* ---------------------------
       Low-level utility functions
    --------------------------- */

    protected static function calculateTotalsFromItems(array $items, string $costKey, string $calorieKey): array
    {
        $totalCost = 0;
        $totalCalories = 0;

        foreach ($items as $item) {
            $totalCost    += $item[$costKey] ?? 0;
            $totalCalories += $item[$calorieKey] ?? 0;
        }

        return [$totalCost, $totalCalories];
    }

    protected static function calculatePerPortion(float $total, float $portionSize): float
    {
        return $portionSize > 0 ? $total / $portionSize : 0;
    }

    protected static function formatNumber(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /* ---------------------------
       Higher-level update helpers
    --------------------------- */

    public static function updateProductRecipeTotals(array $fields): \Closure
    {
        return function (callable $set, callable $get) use ($fields) {
            // Get full row snapshot
            $currentRow = $get('..');

            $recipeId = $currentRow['recipe_id'] ?? null;
            $quantity = $currentRow['quantity'] ?? 1;

            if ($recipeId) {
                $recipe = Recipe::find($recipeId);

                if ($recipe) {
                    $rowCost    = ($recipe->total_cost_per_portion ?? 0) * $quantity;
                    $rowCalorie = ($recipe->total_calorie_per_portion ?? 0) * $quantity;

                    $set($fields['recipe_total_cost'], $rowCost);
                    $set($fields['recipe_total_calorie'], $rowCalorie);
                }
            }

            // Aggregate all recipes for product totals
            $allRecipes = $get('../../' . $fields['recipes']) ?? [];

            [$totalCost, $totalCalorie] = self::calculateTotalsFromItems(
                $allRecipes,
                $fields['recipe_total_cost'],
                $fields['recipe_total_calorie']
            );

            $set('../../' . $fields['product_total_cost'], $totalCost);
            $set('../../' . $fields['product_total_calorie'], $totalCalorie);
        };
    }

    public static function recalculateRecipeTotals(array $field): \Closure
    {
        return function (callable $set, callable $get) use ($field) {
            // Use full form snapshot (not just repeater row)
            $ingredients = $get($field['ingredients']) ?? [];

            [$totalCost, $totalCalories] = self::calculateTotalsFromItems(
                $ingredients,
                $field['total_cost'],
                $field['total_calorie']
            );

            // Per portion
            if (
                isset($field['portion']) &&
                isset($field['total_cost_per_portion']) &&
                isset($field['total_calorie_per_portion'])
            ) {
                $portionSize = $get($field['portion']) ?: 1;

                $set($field['total_cost_per_portion'], self::formatNumber(
                    self::calculatePerPortion($totalCost, $portionSize)
                ));
                $set($field['total_calorie_per_portion'], self::formatNumber(
                    self::calculatePerPortion($totalCalories, $portionSize)
                ));
            }
        };
    }

    public static function updateIngredientsTotal(array $field): \Closure
    {
        return function (callable $set, callable $get) use ($field) {
            // Get the full repeater row snapshot
            $currentRow = $get('..');

            $ingredientId = $currentRow['ingredient_id'] ?? null;
            $quantity     = $currentRow['quantity'] ?? 0;

            if (!$ingredientId || $quantity <= 0) {
                return;
            }

            $ingredient = Ingredient::find($ingredientId);

            if ($ingredient) {
                $totalCost     = $quantity * $ingredient->cost_per_unit;
                $totalCalories = $quantity * $ingredient->calorie_per_unit;

                $set($field['total_cost'], self::formatNumber($totalCost));
                $set($field['total_calorie'], self::formatNumber($totalCalories));
            }
        };
    }
}
