<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'notes',
        'company_id',
    ];

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
