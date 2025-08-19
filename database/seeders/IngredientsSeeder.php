<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientsSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            // name, type, unit, kcal/unit, IDR/unit, stock
            ['Chicken Breast','meat','gram', 1.65,  90, 8000],
            ['Beef Sirloin',  'meat','gram', 2.50, 140, 5000],
            ['Tofu',          'protein','gram',0.76,  25, 6000],
            ['Tempeh',        'protein','gram',1.93,  30, 6000],
            ['Rice',          'grain','gram', 1.30,  14, 15000],
            ['Brown Rice',    'grain','gram', 1.11,  18, 15000],
            ['Broccoli',      'vegetable','gram',0.34, 35, 5000],
            ['Carrot',        'vegetable','gram',0.41, 20, 5000],
            ['Spinach',       'vegetable','gram',0.23, 18, 4000],
            ['Olive Oil',     'oil','ml',   8.84, 200, 3000],
            ['Coconut Oil',   'oil','ml',   8.62, 160, 3000],
            ['Salt',          'spice','gram',0.00,  10, 4000],
            ['Garlic',        'spice','gram',1.49,  30, 2000],
            ['Onion',         'spice','gram',0.40,  22, 3000],
            ['Chili',         'spice','gram',0.40,  28, 2000],
        ];

        foreach ($ingredients as $i) {
            Ingredient::firstOrCreate(
                ['name' => $i[0]],
                [
                    'type' => $i[1],
                    'unit' => $i[2],
                    'calorie_per_unit' => $i[3],
                    'cost_per_unit'    => $i[4],
                    'stock_quantity'   => $i[5],
                ]
            );
        }
    }
}
