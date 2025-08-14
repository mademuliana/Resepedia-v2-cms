<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDelivery extends Model
{
    protected $table = 'order_delivery';

    protected $fillable = [
        'order_id',
        'address_id',
        'contact_name',
        'contact_phone',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'courier_id',
        'courier_name',
        'tracking_code',
        'delivery_window_start',
        'delivery_window_end',
        'delivered_at',
        'delivery_instructions',
    ];

    protected $casts = [
        'latitude'               => 'decimal:7',
        'longitude'              => 'decimal:7',
        'delivery_window_start'  => 'datetime',
        'delivery_window_end'    => 'datetime',
        'delivered_at'           => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}
