<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientsController extends Controller
{
    // /api/ingredients?cost=1&calorie=1
    public function index(Request $request)
    {
        $includeCost = (bool) $request->boolean('cost', false);
        $includeCal  = (bool) $request->boolean('calorie', false);

        $cols = ['id','name','type','unit'];
        if ($includeCost) $cols[] = 'cost_per_unit';
        if ($includeCal)  $cols[] = 'calorie_per_unit';

        $rows = Ingredient::query()->select($cols)->orderBy('name')->get();

        return response()->json($rows);
    }
}
