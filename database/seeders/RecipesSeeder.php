<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipesSeeder extends Seeder
{
    public function run(): void
    {
        $recipes = [
            ['name' => 'Grilled Chicken',     'prep' => 30, 'portion' => 250],
            ['name' => 'Garlic Rice',         'prep' => 20, 'portion' => 200],
            ['name' => 'Beef Stir-fry',       'prep' => 25, 'portion' => 250],
            ['name' => 'Tofu Teriyaki',       'prep' => 20, 'portion' => 220],
            ['name' => 'Tempeh Sambal',       'prep' => 20, 'portion' => 220],
            ['name' => 'Steamed Broccoli',    'prep' => 15, 'portion' => 150],
            ['name' => 'Carrot Stir-fry',     'prep' => 15, 'portion' => 180],
            ['name' => 'Spinach Garlic Sauté','prep' => 10, 'portion' => 150],
        ];

        // Ingredient mapping (by name) to make deterministic combos
        $pick = fn($names) => Ingredient::whereIn('name', $names)->get()->keyBy('name');

        $plans = [
            'Grilled Chicken'      => ['Chicken Breast','Olive Oil','Salt','Garlic'],
            'Garlic Rice'          => ['Rice','Garlic','Salt','Coconut Oil'],
            'Beef Stir-fry'        => ['Beef Sirloin','Onion','Garlic','Olive Oil','Salt'],
            'Tofu Teriyaki'        => ['Tofu','Garlic','Coconut Oil','Onion'],
            'Tempeh Sambal'        => ['Tempeh','Chili','Garlic','Salt','Coconut Oil'],
            'Steamed Broccoli'     => ['Broccoli','Salt','Olive Oil'],
            'Carrot Stir-fry'      => ['Carrot','Garlic','Coconut Oil','Salt'],
            'Spinach Garlic Sauté' => ['Spinach','Garlic','Olive Oil','Salt'],
        ];

        // Base qtys (grams or ml) by ingredient name (approximate realistic)
        $baseQty = [
            'Chicken Breast' => 180, 'Beef Sirloin' => 150, 'Tofu' => 180, 'Tempeh' => 180,
            'Rice' => 180, 'Brown Rice' => 180, 'Broccoli' => 130, 'Carrot' => 150, 'Spinach' => 120,
            'Olive Oil' => 10, 'Coconut Oil' => 10, 'Salt' => 3, 'Garlic' => 8, 'Onion' => 20, 'Chili' => 5,
        ];

        foreach ($recipes as $rd) {
            $recipe = Recipe::firstOrCreate(
                ['name' => $rd['name']],
                [
                    'prep_time_minutes' => $rd['prep'],
                    'portion_size' => $rd['portion'],
                    'total_calorie_per_portion' => 0,
                    'total_cost_per_portion'    => 0,
                    'notes' => null,
                ]
            );

            $names = $plans[$rd['name']];
            $ings  = $pick($names);

            $totalCost = 0.0;
            $totalCal  = 0.0;

            foreach ($names as $ingName) {
                $ing = $ings[$ingName];
                $qty = $baseQty[$ingName] ?? 100;

                $cost = $qty * (float) $ing->cost_per_unit;
                $cal  = $qty * (float) $ing->calorie_per_unit;

                DB::table('recipe_ingredient')->updateOrInsert(
                    ['recipe_id' => $recipe->id, 'ingredient_id' => $ing->id],
                    [
                        'quantity'                 => $qty,
                        'ingredient_total_cost'    => round($cost, 2),
                        'ingredient_total_calorie' => round($cal, 2),
                        'created_at'               => now(),
                        'updated_at'               => now(),
                    ]
                );

                $totalCost += $cost;
                $totalCal  += $cal;
            }

            $portion = (float) $recipe->portion_size;
            $perCost = $portion > 0 ? $totalCost / $portion : 0.0; // per gram/ml
            $perCal  = $portion > 0 ? $totalCal / $portion : 0.0;  // per gram/ml

            $recipe->update([
                'total_cost_per_portion'    => round($perCost, 2),
                'total_calorie_per_portion' => round($perCal, 2),
            ]);
        }
    }
}
