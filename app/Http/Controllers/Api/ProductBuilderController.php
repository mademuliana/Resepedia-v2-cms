<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductBuilderController extends Controller
{
    // List recipes (name + per-portion kcal), scoped by company unless super admin
    public function recipes(Request $request)
    {
        $query = Recipe::query()->select('id','name','total_calorie_per_portion');
        // Global scope already limits admins; super admin sees all
        return response()->json($query->orderBy('name')->get());
    }

    // Compute totals for a custom product; persist if persist=true
    public function compute(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable','string','max:255'],
            'persist' => ['nullable','boolean'],
            'recipes' => ['required','array','min:1'],
            'recipes.*.recipe_id' => ['required','integer','exists:recipes,id'],
            'recipes.*.quantity' => ['required','numeric','min:0'],
        ]);

        // Load recipe per-portion metrics in batch
        $ids = collect($data['recipes'])->pluck('recipe_id')->unique()->values();
        $map = Recipe::query()->whereIn('id',$ids)->get(['id','total_cost_per_portion','total_calorie_per_portion'])->keyBy('id');

        $totalCost = 0.0; $totalCal = 0.0; $lines = [];
        foreach ($data['recipes'] as $row) {
            $r = $map[$row['recipe_id']] ?? null; if (! $r) continue;
            $qty = (float) $row['quantity'];
            $rowCost = $qty * (float) $r->total_cost_per_portion;
            $rowCal  = $qty * (float) $r->total_calorie_per_portion;
            $totalCost += $rowCost; $totalCal += $rowCal;

            $lines[] = [
                'recipe_id' => (int) $row['recipe_id'],
                'quantity'  => $qty,
                'recipe_total_cost'    => round($rowCost,2),
                'recipe_total_calorie' => round($rowCal,2),
            ];
        }

        $price = (int) (round(($totalCost * 2.5) / 100) * 100);

        if (! ($data['persist'] ?? false)) {
            return response()->json([
                'name' => $data['name'] ?? null,
                'total_cost'    => round($totalCost,2),
                'total_calorie' => round($totalCal,2),
                'price'         => $price,
                'lines'         => $lines,
            ]);
        }

        // Persist as a Product for the user's company
        $user = $request->user();
        $companyId = $user->isSuperAdmin() ? ($user->company_id ?? null) : $user->company_id;

        return DB::transaction(function () use ($data, $companyId, $lines, $totalCost, $totalCal, $price) {
            $product = Product::create([
                'company_id'   => $companyId,
                'name'         => $data['name'] ?? 'Custom Product',
                'price'        => $price,
                'total_cost'   => round($totalCost,2),
                'total_calorie'=> round($totalCal,2),
                'notes'        => 'Created via API builder',
            ]);

            foreach ($lines as $ln) {
                DB::table('product_recipe')->insert([
                    'product_id' => $product->id,
                    'recipe_id'  => $ln['recipe_id'],
                    'quantity'   => $ln['quantity'],
                    'recipe_total_cost'    => $ln['recipe_total_cost'],
                    'recipe_total_calorie' => $ln['recipe_total_calorie'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return response()->json(['product_id' => $product->id], 201);
        });
    }

    // Aggregate ingredients from selected recipes (scaled by recipe qty / portion_size)
    public function ingredients(Request $request)
    {
        $data = $request->validate([
            'recipes' => ['required','array','min:1'],
            'recipes.*.recipe_id' => ['required','integer','exists:recipes,id'],
            'recipes.*.quantity'  => ['required','numeric','min:0'], // grams/ml of recipe used
        ]);

        $ids = collect($data['recipes'])->pluck('recipe_id')->unique()->values();
        $recipes = Recipe::with(['ingredients' => function ($q) {
            $q->select('ingredients.id','ingredients.name','ingredients.unit');
        }])->whereIn('id',$ids)->get(['id','portion_size']);

        $byId = $recipes->keyBy('id');

        $agg = []; // ingredient_id => ['name','unit','qty','cal','cost']
        foreach ($data['recipes'] as $row) {
            $rid = (int) $row['recipe_id']; $qty = (float) $row['quantity'];
            $recipe = $byId[$rid] ?? null; if (! $recipe) continue;

            $portion = max(1.0, (float) $recipe->portion_size); // avoid /0
            $scale   = $qty / $portion;

            foreach ($recipe->ingredients as $ing) {
                $pivotQty = (float) $ing->pivot->quantity;
                $pivotCost= (float) $ing->pivot->ingredient_total_cost;
                $pivotCal = (float) $ing->pivot->ingredient_total_calorie;

                $addQty = $pivotQty * $scale;
                $addCost= $pivotCost * $scale;
                $addCal = $pivotCal * $scale;

                $agg[$ing->id] ??= ['name'=>$ing->name,'unit'=>$ing->unit,'quantity'=>0,'cost'=>0,'calorie'=>0];
                $agg[$ing->id]['quantity'] += $addQty;
                $agg[$ing->id]['cost']     += $addCost;
                $agg[$ing->id]['calorie']  += $addCal;
            }
        }

        // format
        $out = [];
        foreach ($agg as $ingId => $v) {
            $out[] = [
                'ingredient_id' => $ingId,
                'name'    => $v['name'],
                'unit'    => $v['unit'],
                'quantity'=> round($v['quantity'], 2),
                'total_cost'    => round($v['cost'], 2),
                'total_calorie' => round($v['calorie'], 2),
            ];
        }
        return response()->json([
            'ingredients' => array_values($out),
            'totals' => [
                'cost'    => round(array_sum(array_column($out,'total_cost')), 2),
                'calorie' => round(array_sum(array_column($out,'total_calorie')), 2),
            ],
        ]);
    }
}
