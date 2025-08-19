<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Builders\IngredientBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'type',
        'unit',
        'calorie_per_unit',
        'cost_per_unit',
        'stock_quantity',
    ];

    public function newEloquentBuilder($query): EloquentBuilder
    {
        return new IngredientBuilder($query);
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredient')
            ->withPivot(['quantity', 'ingredient_total_cost', 'ingredient_total_calorie'])
            ->withTimestamps();
    }
}
