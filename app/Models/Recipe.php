<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
class Recipe extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'name',
        'prep_time_minutes',
        'portion_size',
        'total_calorie_per_portion',
        'total_cost_per_portion',
        'notes',
        'company_id',
    ];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class , 'recipe_ingredient')
            ->withPivot(['quantity', 'ingredient_total_cost', 'ingredient_total_calorie'])
            ->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class , 'product_recipe')
            ->withPivot(['quantity', 'recipe_total_cost', 'recipe_total_calorie'])
            ->withTimestamps();
    }

    public function steps()
    {
        return $this->hasMany(\App\Models\RecipeStep::class)
            ->orderBy('step_no');
    }
}
