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

            // 1️⃣ INGREDIENTS
            $ingredients = [
                ['name' => 'Chicken Breast', 'type' => 'meat', 'unit' => 'gram', 'calorie_per_unit' => 1.65, 'price_per_unit' => 0.02, 'stock_quantity' => 5000],
                ['name' => 'Rice', 'type' => 'grain', 'unit' => 'gram', 'calorie_per_unit' => 1.3, 'price_per_unit' => 0.005, 'stock_quantity' => 10000],
                ['name' => 'Broccoli', 'type' => 'vegetable', 'unit' => 'gram', 'calorie_per_unit' => 0.34, 'price_per_unit' => 0.01, 'stock_quantity' => 3000],
                ['name' => 'Olive Oil', 'type' => 'oil', 'unit' => 'ml', 'calorie_per_unit' => 8.84, 'price_per_unit' => 0.03, 'stock_quantity' => 2000],
                ['name' => 'Salt', 'type' => 'spice', 'unit' => 'gram', 'calorie_per_unit' => 0, 'price_per_unit' => 0.001, 'stock_quantity' => 1000],
                ['name' => 'Garlic', 'type' => 'spice', 'unit' => 'gram', 'calorie_per_unit' => 1.49, 'price_per_unit' => 0.02, 'stock_quantity' => 500],
            ];
            $ingredientModels = [];
            foreach ($ingredients as $data) {
                $ingredientModels[] = Ingredient::create($data);
            }

            // 2️⃣ RECIPES
            $recipes = [
                ['name' => 'Grilled Chicken', 'prep_time_minutes' => 30, 'portion_size' => 250],
                ['name' => 'Steamed Broccoli', 'prep_time_minutes' => 15, 'portion_size' => 150],
                ['name' => 'Garlic Rice', 'prep_time_minutes' => 20, 'portion_size' => 200],
                ['name' => 'Chicken Rice Bowl', 'prep_time_minutes' => 25, 'portion_size' => 400],
            ];
            $recipeModels = [];
            foreach ($recipes as $recipeData) {
                $recipe = Recipe::create(array_merge($recipeData, [
                    'total_calorie_per_portion' => 0,
                    'total_price_per_portion' => 0,
                ]));

                // Attach ingredients randomly
                $selectedIngredients = collect($ingredientModels)->random(rand(2, 4));
                $totalCalorie = 0;
                $totalPrice = 0;
                foreach ($selectedIngredients as $ingredient) {
                    $qty = rand(50, 200);
                    $cal = $ingredient->calorie_per_unit * $qty;
                    $price = $ingredient->price_per_unit * $qty;

                    $totalCalorie += $cal;
                    $totalPrice += $price;

                    DB::table('recipe_ingredient')->insert([
                        'recipe_id' => $recipe->id,
                        'ingredient_id' => $ingredient->id,
                        'quantity' => $qty,
                        'total_price' => $price,
                        'total_calorie' => $cal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $recipe->update([
                    'total_calorie_per_portion' => $totalCalorie,
                    'total_price_per_portion' => $totalPrice,
                ]);

                $recipeModels[] = $recipe;
            }

            // 3️⃣ PRODUCTS
            $products = [
                ['name' => 'Protein Pack', 'price' => 0, 'total_calorie' => 0],
                ['name' => 'Veggie Bowl', 'price' => 0, 'total_calorie' => 0],
            ];
            $productModels = [];
            foreach ($products as $productData) {
                $product = Product::create($productData);

                $selectedRecipes = collect($recipeModels)->random(rand(1, 3));
                $totalCalorie = 0;
                $totalPrice = 0;
                foreach ($selectedRecipes as $recipe) {
                    $qty = rand(1, 2);
                    $cal = $recipe->total_calorie_per_portion * $qty;
                    $price = $recipe->total_price_per_portion * $qty;

                    $totalCalorie += $cal;
                    $totalPrice += $price;

                    DB::table('product_recipe')->insert([
                        'product_id' => $product->id,
                        'recipe_id' => $recipe->id,
                        'quantity' => $qty,
                        'total_price' => $price,
                        'total_calorie' => $cal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $product->update([
                    'price' => $totalPrice,
                    'total_calorie' => $totalCalorie,
                ]);

                $productModels[] = $product;
            }

            // 4️⃣ ORDER
            $order = Order::create([
                'customer_name' => 'John Doe',
                'customer_phone' => '123456789',
                'total_price' => 0,
                'total_calorie' => 0,
                'status' => 'confirmed',
            ]);

            $totalCalorie = 0;
            $totalPrice = 0;
            foreach ($productModels as $product) {
                $qty = rand(1, 3);
                $cal = $product->total_calorie * $qty;
                $price = $product->price * $qty;

                $totalCalorie += $cal;
                $totalPrice += $price;

                DB::table('order_items')->insert([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'total_price' => $price,
                    'total_calorie' => $cal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $order->update([
                'total_price' => $totalPrice,
                'total_calorie' => $totalCalorie,
            ]);
        });
    }
}
