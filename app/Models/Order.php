<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Order extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'customer_id',
        'address_id',
        'customer_name',
        'customer_phone',
        'total_price',
        'total_calorie',
        'status',
        'ordered_at',
        'required_at',
        'customer_email',
        'deposit_required',
        'deposit_amount',
        'notes',
        'company_id',
    ];

    protected $casts = [
        'ordered_at'       => 'datetime',
        'required_at'      => 'datetime',
        'deposit_required' => 'boolean',
        'deposit_amount'   => 'decimal:2',
        'total_price'      => 'decimal:2',
        'total_calorie'    => 'decimal:2',
    ];

    protected $appends = [
        'paid_total',
        'balance_due',
        'is_fully_paid',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')
            ->withPivot(['quantity', 'product_total_price', 'product_total_calorie'])
            ->withTimestamps();
    }

    public function delivery()
    {
        return $this->hasOne(OrderDelivery::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at', 'desc');
    }

    // Computed attributes
    public function getPaidTotalAttribute()
    {
        $paid = $this->payments
            ->where('status', 'paid')
            ->sum('amount');

        $refunded = $this->payments
            ->where('type', 'refund')
            ->where('status', 'paid')
            ->sum('amount');

        return (float) ($paid - $refunded);
    }

    public function getBalanceDueAttribute()
    {
        return max(0, (float) $this->total_price - $this->paid_total);
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->balance_due <= 0.0;
    }

    // Boot method to log status changes automatically
    protected static function booted()
    {
        static::updated(function ($order) {
            if ($order->isDirty('status')) {
                $order->statusHistory()->create([
                    'status_from' => $order->getOriginal('status'),
                    'status_to'   => $order->status,
                    'changed_at'  => now(),
                    'changed_by'  => Auth::id(),
                    'note'        => null,
                ]);
            }
        });
    }
}
