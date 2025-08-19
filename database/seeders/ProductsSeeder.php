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
        $companyIds = DB::table('companies')->pluck('id');
        $clamp = fn($n,$min,$max)=>max($min,min($max,$n));

        $plan = fn($name,$lines)=>['name'=>$name,'lines'=>$lines];

        // Up to 12 product plans available
        $plans = [
            $plan('Protein Pack',          [['Grilled Chicken',200], ['Garlic Rice',200], ['Steamed Broccoli',100]]),
            $plan('Beef & Veg Bowl',       [['Beef Stir-fry',200], ['Garlic Rice',200], ['Spinach Garlic Sauté',80]]),
            $plan('Tofu Teriyaki Bowl',    [['Tofu Teriyaki',220], ['Garlic Rice',180]]),
            $plan('Tempeh Sambal Plate',   [['Tempeh Sambal',220], ['Garlic Rice',180], ['Carrot Stir-fry',100]]),
            $plan('Broccoli Rice',         [['Steamed Broccoli',150], ['Garlic Rice',220]]),
            $plan('Lean Chicken Bowl',     [['Grilled Chicken',220], ['Spinach Garlic Sauté',100]]),
            $plan('Beef Power Bowl',       [['Beef Stir-fry',240], ['Garlic Rice',200]]),
            $plan('Greens Medley',         [['Spinach Garlic Sauté',150], ['Steamed Broccoli',150], ['Carrot Stir-fry',150]]),
            $plan('Tempeh Protein Bowl',   [['Tempeh Sambal',240], ['Garlic Rice',180]]),
            $plan('Chicken Rice Duo',      [['Grilled Chicken',200], ['Garlic Rice',220]]),
            // extras (for +1/+2)
            $plan('Brown Rice Bowl',       [['Brown Rice',250], ['Spinach Garlic Sauté',80]]),
            $plan('Beef & Broccoli',       [['Beef Stir-fry',220], ['Steamed Broccoli',120]]),
        ];

        foreach ($companyIds as $cid) {
            $offset = (crc32((string) $cid) % 5) - 2; // -2..+2
            // Filter plans to those whose recipes exist in this company
            $available = Recipe::where('company_id',$cid)->pluck('name')->all();

            $filtered = [];
            foreach ($plans as $p) {
                $lines = array_values(array_filter($p['lines'], fn($ln)=>in_array($ln[0], $available, true)));
                if (count($lines) > 0) {
                    $filtered[] = ['name'=>$p['name'],'lines'=>$lines];
                }
            }

            $target = $clamp(10 + $offset, 4, count($filtered));

            foreach (array_slice($filtered, 0, $target) as $p) {
                $product = Product::updateOrCreate(
                    ['name'=>$p['name'],'company_id'=>$cid],
                    ['price'=>0,'total_cost'=>0,'total_calorie'=>0,'notes'=>null]
                );

                $totalCost = 0.0; $totalCal = 0.0;
                foreach ($p['lines'] as [$recipeName,$qty]) {
                    $recipe = Recipe::where('company_id',$cid)->where('name',$recipeName)->first();
                    if (! $recipe) continue;

                    $rowCost = (float)$recipe->total_cost_per_portion * (float)$qty;
                    $rowCal  = (float)$recipe->total_calorie_per_portion * (float)$qty;

                    DB::table('product_recipe')->updateOrInsert(
                        ['product_id'=>$product->id,'recipe_id'=>$recipe->id],
                        ['quantity'=>(int)$qty,'recipe_total_cost'=>round($rowCost,2),'recipe_total_calorie'=>round($rowCal,2),'created_at'=>now(),'updated_at'=>now()]
                    );
                    $totalCost += $rowCost; $totalCal += $rowCal;
                }

                $price = (int) (round(($totalCost * 2.5) / 100) * 100);
                $product->update(['price'=>$price,'total_cost'=>round($totalCost,2),'total_calorie'=>round($totalCal,2)]);
            }
        }
    }
}
