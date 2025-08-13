<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CateringSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            /* ---------------------------------------------------------
             * 1) INGREDIENTS (realistic kcal/unit + IDR per unit)
             *    kcal = per gram (g) or per milliliter (ml)
             *    cost = IDR per unit (g or ml)
             * --------------------------------------------------------- */
            $ingredients = [
                // name            type         unit   kcal/unit  IDR/unit  stock
                ['name' => 'Chicken Breast', 'type' => 'meat',      'unit' => 'gram', 'calorie_per_unit' => 1.65, 'cost_per_unit' => 90,  'stock_quantity' => 5000], // ~90k / kg
                ['name' => 'Rice',           'type' => 'grain',     'unit' => 'gram', 'calorie_per_unit' => 1.30, 'cost_per_unit' => 14,  'stock_quantity' => 10000], // ~14k / kg (uncooked would be higher kcal)
                ['name' => 'Broccoli',       'type' => 'vegetable', 'unit' => 'gram', 'calorie_per_unit' => 0.34, 'cost_per_unit' => 35,  'stock_quantity' => 3000],  // ~35k / kg
                ['name' => 'Olive Oil',      'type' => 'oil',       'unit' => 'ml',   'calorie_per_unit' => 8.84, 'cost_per_unit' => 200, 'stock_quantity' => 2000],  // ~200k / L mid-range EVOO
                ['name' => 'Salt',           'type' => 'spice',     'unit' => 'gram', 'calorie_per_unit' => 0.00, 'cost_per_unit' => 10,  'stock_quantity' => 1000],  // ~10k / kg
                ['name' => 'Garlic',         'type' => 'spice',     'unit' => 'gram', 'calorie_per_unit' => 1.49, 'cost_per_unit' => 30,  'stock_quantity' => 500],   // ~30k / kg
            ];

            $ingredientModels = [];
            foreach ($ingredients as $data) {
                $ingredientModels[$data['name']] = Ingredient::create($data);
            }

            /* ---------------------------------------------------------
             * 2) RECIPES
             *    - We’ll scale ingredient quantities to match portion_size
             *    - Store *_per_portion as totals / portion_size
             * --------------------------------------------------------- */
            $recipesData = [
                ['name' => 'Grilled Chicken',   'prep_time_minutes' => 30, 'portion_size' => 250], // grams
                ['name' => 'Steamed Broccoli',  'prep_time_minutes' => 15, 'portion_size' => 150],
                ['name' => 'Garlic Rice',       'prep_time_minutes' => 20, 'portion_size' => 200],
                ['name' => 'Chicken Rice Bowl', 'prep_time_minutes' => 25, 'portion_size' => 400],
            ];

            $recipeModels = [];

            foreach ($recipesData as $recipeData) {
                $recipe = Recipe::create(array_merge($recipeData, [
                    'total_calorie_per_portion' => 0,
                    'total_cost_per_portion'    => 0,
                ]));

                // Pick 2–4 ingredients and assign base quantities by unit
                $pool = collect($ingredientModels)->values()->all();
                $picked = collect($pool)->shuffle()->take(rand(2, 4));

                $baseRows = [];
                $baseTotalQty = 0;

                foreach ($picked as $ingredient) {
                    // Base qty ranges by unit (ml is typically smaller)
                    if ($ingredient->unit === 'ml') {
                        $qty = rand(5, 20); // 5–20 ml
                    } else {
                        $qty = rand(30, 200); // 30–200 g
                    }
                    $baseRows[] = ['ingredient' => $ingredient, 'qty' => $qty];
                    $baseTotalQty += $qty;
                }

                // Scale base quantities so they sum ~ portion_size
                $target = max(1, (int) $recipe->portion_size);
                $scale  = $baseTotalQty > 0 ? $target / $baseTotalQty : 1;

                $totalCalorie = 0.0;
                $totalCost    = 0.0;

                foreach ($baseRows as $row) {
                    $ingredient = $row['ingredient'];
                    $qty        = max(1, (int) round($row['qty'] * $scale)); // scaled integer qty

                    $cal  = $ingredient->calorie_per_unit * $qty; // kcal
                    $cost = $ingredient->cost_per_unit    * $qty; // IDR

                    $totalCalorie += $cal;
                    $totalCost    += $cost;

                    DB::table('recipe_ingredient')->insert([
                        'recipe_id'                => $recipe->id,
                        'ingredient_id'            => $ingredient->id,
                        'quantity'                 => $qty,
                        'ingredient_total_cost'    => round($cost, 2),
                        'ingredient_total_calorie' => round($cal, 2),
                        'created_at'               => now(),
                        'updated_at'               => now(),
                    ]);
                }

                // Store per-portion (per unit mass/volume) values
                // Our UI/resource computes per-unit as total / portion_size, so keep DB consistent:
                $perUnitCost = $target > 0 ? $totalCost / $target : 0;
                $perUnitCal  = $target > 0 ? $totalCalorie / $target : 0;

                $recipe->update([
                    'total_calorie_per_portion' => round($perUnitCal, 2),
                    'total_cost_per_portion'    => round($perUnitCost, 2),
                ]);

                $recipeModels[$recipe->name] = $recipe;
            }

            /* ---------------------------------------------------------
             * 3) PRODUCTS
             *    Use sensible compositions instead of random:
             *    - Protein Pack: Chicken + Rice + Broccoli
             *    - Veggie Bowl:  Rice + Broccoli
             *    Quantity here = grams of each recipe included in the product.
             * --------------------------------------------------------- */
            $productsPlan = [
                'Protein Pack' => [
                    ['recipe' => 'Grilled Chicken',  'qty' => 200],
                    ['recipe' => 'Garlic Rice',      'qty' => 200],
                    ['recipe' => 'Steamed Broccoli', 'qty' => 100],
                ],
                'Veggie Bowl' => [
                    ['recipe' => 'Garlic Rice',      'qty' => 200],
                    ['recipe' => 'Steamed Broccoli', 'qty' => 200],
                ],
            ];

            $productModels = [];

            foreach ($productsPlan as $productName => $lines) {
                $product = Product::create([
                    'name'          => $productName,
                    'price'         => 0,
                    'total_cost'    => 0,
                    'total_calorie' => 0,
                ]);

                $totalCost = 0.0;
                $totalCal  = 0.0;

                foreach ($lines as $line) {
                    if (! isset($recipeModels[$line['recipe']])) {
                        continue;
                    }
                    $recipe = $recipeModels[$line['recipe']];
                    $qty    = (float) $line['qty'];

                    // recipe_*_per_portion are per gram/ml → multiply by qty (grams/ml) to get totals for the line
                    $rowCost = $qty * (float) $recipe->total_cost_per_portion;
                    $rowCal  = $qty * (float) $recipe->total_calorie_per_portion;

                    $totalCost += $rowCost;
                    $totalCal  += $rowCal;

                    DB::table('product_recipe')->insert([
                        'product_id'           => $product->id,
                        'recipe_id'            => $recipe->id,
                        'quantity'             => (int) $qty,
                        'recipe_total_cost'    => round($rowCost, 2),
                        'recipe_total_calorie' => round($rowCal, 2),
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                }

                // Price with markup; round to the nearest 100 IDR for realism
                $basePrice = $totalCost * 2.5;
                $rounded   = (int) (round($basePrice / 100) * 100);

                $product->update([
                    'price'         => $rounded,
                    'total_cost'    => round($totalCost, 2),
                    'total_calorie' => round($totalCal, 2),
                ]);

                $productModels[] = $product;
            }

            /* ---------------------------------------------------------
             * 4) ORDER (example)
             * --------------------------------------------------------- */
            $order = Order::create([
                'customer_name'  => 'John Doe',
                'customer_phone' => '628123456789',
                'total_price'    => 0,
                'total_calorie'  => 0,
                'status'         => 'confirmed',
            ]);

            $orderTotalCal  = 0.0;
            $orderTotalPrice = 0.0;

            foreach ($productModels as $product) {
                $qty   = rand(1, 3);
                $cal   = (float) $product->total_calorie * $qty;
                $price = (float) $product->price * $qty;

                $orderTotalCal  += $cal;
                $orderTotalPrice += $price;

                DB::table('order_items')->insert([
                    'order_id'              => $order->id,
                    'product_id'            => $product->id,
                    'quantity'              => $qty,
                    'product_total_price'   => round($price, 2),
                    'product_total_calorie' => round($cal, 2),
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            $order->update([
                'total_price'   => round($orderTotalPrice, 2),
                'total_calorie' => round($orderTotalCal, 2),
            ]);
        });
    }
}
