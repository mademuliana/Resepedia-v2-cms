<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'total_calorie',
        'total_cost',
        'notes',
    ];

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'product_recipe')
            ->withPivot(['quantity', 'recipe_total_cost', 'recipe_total_calorie'])
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot(['quantity', 'product_total_price', 'product_total_calorie'])
            ->withTimestamps();
    }
}
