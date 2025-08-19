<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'website',
        'tax_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'active',
        'notes',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
    public function couriers()
    {
        return $this->hasMany(Courier::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
