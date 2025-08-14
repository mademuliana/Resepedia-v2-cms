<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomersAndAddressesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'name' => 'Andi Setiawan',
                'email' => 'andi@customer.test',
                'phone' => '628111111111',
                'addresses' => [
                    ['label' => 'Home', 'line1' => 'Jl. Melati No. 12', 'city' => 'Jakarta', 'state' => 'DKI Jakarta', 'postal' => '10110', 'lat' => -6.2000, 'lng' => 106.8167, 'default' => true],
                    ['label' => 'Office', 'line1' => 'Jl. Sudirman Kav. 10', 'city' => 'Jakarta', 'state' => 'DKI Jakarta', 'postal' => '10220', 'lat' => -6.2146, 'lng' => 106.8451, 'default' => false],
                ],
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@customer.test',
                'phone' => '628122222222',
                'addresses' => [
                    ['label' => 'Home', 'line1' => 'Jl. Diponegoro No. 5', 'city' => 'Bandung', 'state' => 'Jawa Barat', 'postal' => '40115', 'lat' => -6.9147, 'lng' => 107.6098, 'default' => true],
                ],
            ],
            [
                'name' => 'Siti Aisyah',
                'email' => 'siti@customer.test',
                'phone' => '628133333333',
                'addresses' => [
                    ['label' => 'Home', 'line1' => 'Jl. Pahlawan No. 7', 'city' => 'Surabaya', 'state' => 'Jawa Timur', 'postal' => '60111', 'lat' => -7.2575, 'lng' => 112.7521, 'default' => true],
                ],
            ],
        ];

        foreach ($data as $c) {
            $cust = Customer::firstOrCreate(
                ['email' => $c['email'] ?? null, 'phone' => $c['phone'] ?? null, 'name' => $c['name']],
                ['notes' => null]
            );

            foreach ($c['addresses'] as $a) {
                Address::firstOrCreate(
                    [
                        'customer_id' => $cust->id,
                        'label'       => $a['label'],
                        'line1'       => $a['line1'],
                        'city'        => $a['city'],
                    ],
                    [
                        'line2'       => $a['line2'] ?? null,
                        'state'       => $a['state'] ?? null,
                        'postal_code' => $a['postal'] ?? null,
                        'country'     => 'ID',
                        'latitude'    => $a['lat'] ?? null,
                        'longitude'   => $a['lng'] ?? null,
                        'is_default'  => (bool) ($a['default'] ?? false),
                    ]
                );
            }
        }
    }
}
