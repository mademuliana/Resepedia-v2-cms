<?php

namespace App\Models;


use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Courier extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'name',
        'type',
        'phone',
        'notes',
        'active',
        'company_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function deliveries()
    {
        return $this->hasMany(OrderDelivery::class);
    }
}
