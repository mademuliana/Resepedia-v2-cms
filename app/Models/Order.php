<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'total_price',
        'total_calorie',
        'status',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['quantity', 'product_total_price', 'product_total_calorie'])
            ->withTimestamps();
    }
}
