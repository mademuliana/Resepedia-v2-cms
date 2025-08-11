<?php

namespace App\Helpers;
use App\Models\Ingredient;

class CalculationHelper
{
    public static function recalculateRecipeTotals(array $fieldNames = []): \Closure
    {
        return function (callable $set, callable $get) use ($fieldNames) {
            $ingredientsKey = $fieldNames['ingredients'];
            $portionKey = $fieldNames['portion'] ?? null;

            $ingredients = $get($ingredientsKey) ?? [];

            // Sum ingredient totals
            $totalCalories = 0;
            $totalPrice = 0;

            // If 'total_calorie' and 'total_price' keys exist, set raw totals
            if (isset($fieldNames['total_calorie']) && isset($fieldNames['total_price'])) {
                $quantityKey = $fieldNames['quantity'] ?? null;

                // If quantity key exists, multiply totals by quantity
                if ($quantityKey !== null) {
                    $quantity = $get($quantityKey) ?: 1;
                    $totalCalories *= $quantity;
                    $totalPrice *= $quantity;
                }
                $set($fieldNames['total_price'], number_format($totalPrice, 2, '.', ''));
                $set($fieldNames['total_calorie'], number_format($totalCalories, 2, '.', ''));
            }

            foreach ($ingredients as $item) {
                $totalCalories += $item['total_calorie'] ?? 0;
                $totalPrice += $item['total_price'] ?? 0;
            }

            // Calculate per portion totals only if portionKey and per portion keys are present
            if (
                $portionKey !== null
                && isset($fieldNames['total_calorie_per_portion'])
                && isset($fieldNames['total_price_per_portion'])
            ) {
                $portionSize = $get($portionKey) ?: 1;

                $caloriesPerPortion = $portionSize > 0 ? $totalCalories / $portionSize : 0;
                $pricePerPortion = $portionSize > 0 ? $totalPrice / $portionSize : 0;

                $set($fieldNames['total_price_per_portion'], number_format($pricePerPortion, 2, '.', ''));
                $set($fieldNames['total_calorie_per_portion'], number_format($caloriesPerPortion, 2, '.', ''));
            }
        };
    }


    public static function updateIngridientTotal(array $fieldNames = []): \Closure
    {
        return function (callable $set, callable $get) use ($fieldNames) {
            $ingredientKey = $fieldNames['ingredient'];
            $quantityKey = $fieldNames['quantity'];
            $totalCalorieKey = $fieldNames['total_calorie'];
            $totalPriceKey = $fieldNames['total_price'];

            $ingredient = Ingredient::find($get($ingredientKey));
            $quantity = $get($quantityKey);

            $totalCalories = $quantity * $ingredient->calorie_per_unit;
            $totalPrice = $quantity * $ingredient->price_per_unit;

            if ($ingredient) {
                $set($totalPriceKey, number_format($totalPrice, 2, '.', ''));
                $set($totalCalorieKey, number_format($totalCalories, 2, '.', ''));
            }
        };
    }

}

