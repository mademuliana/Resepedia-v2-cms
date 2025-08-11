<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'name',
        'prep_time_minutes',
        'portion_size',
        'total_calorie_per_portion',
        'total_price_per_portion',
    ];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class , 'recipe_ingredient')
            ->withPivot(['quantity', 'total_price', 'total_calorie'])
            ->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class , 'product_recipe')
            ->withPivot(['quantity', 'total_price', 'total_calorie'])
            ->withTimestamps();
    }
}
