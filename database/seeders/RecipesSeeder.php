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
        $companies = DB::table('companies')->pluck('id', 'name');

        // Up to 10 recipes available; we’ll slice per company based on offset
        $recipes = [
            ['Grilled Chicken', 30, 250],
            ['Garlic Rice', 20, 200],
            ['Beef Stir-fry', 25, 250],
            ['Tofu Teriyaki', 20, 220],
            ['Tempeh Sambal', 20, 220],
            ['Steamed Broccoli', 15, 150],
            ['Carrot Stir-fry', 15, 180],
            ['Spinach Garlic Sauté', 10, 150],
            // extras (for +1/+2)
            ['Brown Rice', 25, 200],
            ['Mixed Veg Stir-fry', 18, 220],
        ];

        $plans = [
            'Grilled Chicken'      => ['Chicken Breast','Olive Oil','Salt','Garlic'],
            'Garlic Rice'          => ['Rice','Garlic','Salt','Coconut Oil'],
            'Beef Stir-fry'        => ['Beef Sirloin','Onion','Garlic','Olive Oil','Salt'],
            'Tofu Teriyaki'        => ['Tofu','Garlic','Coconut Oil','Onion'],
            'Tempeh Sambal'        => ['Tempeh','Chili','Garlic','Salt','Coconut Oil'],
            'Steamed Broccoli'     => ['Broccoli','Salt','Olive Oil'],
            'Carrot Stir-fry'      => ['Carrot','Garlic','Coconut Oil','Salt'],
            'Spinach Garlic Sauté' => ['Spinach','Garlic','Olive Oil','Salt'],
            'Brown Rice'           => ['Brown Rice','Salt','Coconut Oil'],
            'Mixed Veg Stir-fry'   => ['Broccoli','Carrot','Spinach','Olive Oil','Salt','Garlic'],
        ];

        $baseQty = [
            'Chicken Breast'=>180,'Beef Sirloin'=>150,'Tofu'=>180,'Tempeh'=>180,
            'Rice'=>180,'Brown Rice'=>180,'Broccoli'=>130,'Carrot'=>150,'Spinach'=>120,
            'Olive Oil'=>10,'Coconut Oil'=>10,'Salt'=>3,'Garlic'=>8,'Onion'=>20,'Chili'=>5,
        ];

        $clamp = fn($n,$min,$max)=>max($min,min($max,$n));
        foreach ($companies as $companyName => $companyId) {
            $offset = (crc32((string) $companyId) % 5) - 2; // -2..+2
            $target = $clamp(8 + $offset, 4, count($recipes));

            foreach (array_slice($recipes, 0, $target) as [$name,$prep,$portion]) {
                $recipe = Recipe::updateOrCreate(
                    ['name'=>$name,'company_id'=>$companyId],
                    ['prep_time_minutes'=>$prep,'portion_size'=>$portion,'total_calorie_per_portion'=>0,'total_cost_per_portion'=>0,'notes'=>null]
                );

                $names = $plans[$name];
                $ings  = Ingredient::whereIn('name', $names)->get()->keyBy('name');

                $totalCost = 0.0; $totalCal = 0.0;
                foreach ($names as $ingName) {
                    $ing = $ings[$ingName]; $qty = $baseQty[$ingName] ?? 100;
                    $cost = $qty * (float) $ing->cost_per_unit;
                    $cal  = $qty * (float) $ing->calorie_per_unit;

                    DB::table('recipe_ingredient')->updateOrInsert(
                        ['recipe_id'=>$recipe->id,'ingredient_id'=>$ing->id],
                        ['quantity'=>$qty,'ingredient_total_cost'=>round($cost,2),'ingredient_total_calorie'=>round($cal,2),'created_at'=>now(),'updated_at'=>now()]
                    );
                    $totalCost += $cost; $totalCal += $cal;
                }

                $portionF = (float) $portion;
                $recipe->update([
                    'total_cost_per_portion'    => $portionF>0? round($totalCost/$portionF,2):0,
                    'total_calorie_per_portion' => $portionF>0? round($totalCal/$portionF,2):0,
                ]);
            }
        }
    }
}
