<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'type',     // deposit, balance, full, refund
        'method',   // cash, bank_transfer, ewallet, card, other
        'amount',
        'status',   // pending, paid, failed, refunded
        'paid_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class);
    }
}
