<?php

namespace Database\Seeders;

use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecipeStepsSeeder extends Seeder
{
    public function run(): void
    {
        $steps = [
            'Grilled Chicken' => [
                ['Pat chicken dry and season with salt and minced garlic.', 5],
                ['Brush with a little olive oil.', 1],
                ['Preheat grill/pan to medium-high heat.', 5],
                ['Grill 5–7 minutes per side until juices run clear.', 14],
                ['Rest 5 minutes, then slice and serve.', 6],
            ],
            'Garlic Rice' => [
                ['Rinse rice until water runs clear; drain.', 5],
                ['Sauté minced garlic in coconut oil until fragrant.', 2],
                ['Add rice; stir to coat.', 2],
                ['Add water (1:1.2), salt; bring to a boil.', 3],
                ['Cover and simmer; rest 10 minutes.', 20],
                ['Fluff before serving.', 1],
            ],
            'Beef Stir-fry' => [
                ['Slice beef thinly.', 5], ['Stir-fry onions & garlic.', 3],
                ['Add beef; brown.', 5], ['Season and finish.', 3], ['Rest briefly.', 2],
            ],
            'Tofu Teriyaki' => [
                ['Cube tofu.', 3], ['Pan-fry tofu.', 6], ['Add aromatics.', 2], ['Glaze with sauce.', 4],
            ],
            'Tempeh Sambal' => [
                ['Fry tempeh.', 6], ['Blend sambal.', 4], ['Sauté sambal.', 3], ['Toss tempeh.', 2],
            ],
            'Steamed Broccoli' => [
                ['Cut florets.', 4], ['Steam until tender-crisp.', 6], ['Season & oil.', 2],
            ],
            'Carrot Stir-fry' => [
                ['Julienne carrots.', 5], ['Garlic stir-fry.', 2], ['Cook carrots.', 4], ['Season.', 1],
            ],
            'Spinach Garlic Sauté' => [
                ['Rinse spinach.', 3], ['Sauté garlic.', 2], ['Wilt spinach.', 2], ['Season & serve.', 1],
            ],
            // Optional extras
            'Brown Rice' => [
                ['Rinse brown rice well; drain.', 5], ['Toast in coconut oil briefly.', 2],
                ['Add water (1:1.6), salt; bring to boil.', 3], ['Cover & simmer until tender.', 35],
                ['Rest 10 minutes; fluff.', 10],
            ],
            'Mixed Veg Stir-fry' => [
                ['Prep broccoli, carrots, and spinach.', 6],
                ['Stir-fry garlic in olive oil.', 2],
                ['Add vegetables; toss on high heat.', 5],
                ['Season with salt; serve hot.', 1],
            ],
        ];

        $companyIds = DB::table('companies')->pluck('id');

        foreach ($companyIds as $cid) {
            foreach ($steps as $recipeName => $rows) {
                $recipe = Recipe::where('company_id', $cid)->where('name', $recipeName)->first();
                if (! $recipe) continue;

                foreach ($rows as $i => [$instruction, $minutes]) {
                    RecipeStep::updateOrCreate(
                        ['recipe_id'=>$recipe->id,'step_no'=>$i+1],
                        ['instruction'=>$instruction,'duration_minutes'=>$minutes,'media_url'=>null]
                    );
                }
            }
        }
    }
}
