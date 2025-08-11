<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'total_calorie',
    ];

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'product_recipe')
            ->withPivot(['quantity', 'total_price', 'total_calorie'])
            ->withTimestamps();
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot(['quantity', 'total_price', 'total_calorie'])
            ->withTimestamps();
    }
}
