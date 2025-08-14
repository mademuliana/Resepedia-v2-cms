<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Define 10 products with recipe mixes (qty in grams)
        $mix = fn($name, $lines) => ['name' => $name, 'lines' => $lines];

        $plans = [
            $mix('Protein Pack', [
                ['Grilled Chicken', 200], ['Garlic Rice', 200], ['Steamed Broccoli', 100],
            ]),
            $mix('Beef & Veg Bowl', [
                ['Beef Stir-fry', 200], ['Garlic Rice', 200], ['Spinach Garlic Sauté', 80],
            ]),
            $mix('Tofu Teriyaki Bowl', [
                ['Tofu Teriyaki', 220], ['Garlic Rice', 180],
            ]),
            $mix('Tempeh Sambal Plate', [
                ['Tempeh Sambal', 220], ['Garlic Rice', 180], ['Carrot Stir-fry', 100],
            ]),
            $mix('Broccoli Rice', [
                ['Steamed Broccoli', 150], ['Garlic Rice', 220],
            ]),
            $mix('Lean Chicken Bowl', [
                ['Grilled Chicken', 220], ['Spinach Garlic Sauté', 100], ['Brown Rice', 0], // Brown Rice recipe not defined; ignore
            ]),
            $mix('Beef Power Bowl', [
                ['Beef Stir-fry', 240], ['Garlic Rice', 200],
            ]),
            $mix('Greens Medley', [
                ['Spinach Garlic Sauté', 150], ['Steamed Broccoli', 150], ['Carrot Stir-fry', 150],
            ]),
            $mix('Tempeh Protein Bowl', [
                ['Tempeh Sambal', 240], ['Garlic Rice', 180],
            ]),
            $mix('Chicken Rice Duo', [
                ['Grilled Chicken', 200], ['Garlic Rice', 220],
            ]),
        ];

        foreach ($plans as $p) {
            $product = Product::firstOrCreate(
                ['name' => $p['name']],
                ['price' => 0, 'total_cost' => 0, 'total_calorie' => 0, 'notes' => null]
            );

            $totalCost = 0.0;
            $totalCal  = 0.0;

            foreach ($p['lines'] as [$recipeName, $qty]) {
                $recipe = Recipe::where('name', $recipeName)->first();
                if (! $recipe || $qty <= 0) continue;

                $rowCost = (float) $recipe->total_cost_per_portion * (float) $qty;
                $rowCal  = (float) $recipe->total_calorie_per_portion * (float) $qty;

                DB::table('product_recipe')->updateOrInsert(
                    ['product_id' => $product->id, 'recipe_id' => $recipe->id],
                    [
                        'quantity'             => (int) $qty,
                        'recipe_total_cost'    => round($rowCost, 2),
                        'recipe_total_calorie' => round($rowCal, 2),
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]
                );

                $totalCost += $rowCost;
                $totalCal  += $rowCal;
            }

            $basePrice = $totalCost * 2.5;
            $rounded   = (int) (round($basePrice / 100) * 100);

            $product->update([
                'price'         => $rounded,
                'total_cost'    => round($totalCost, 2),
                'total_calorie' => round($totalCal, 2),
            ]);
        }
    }
}
