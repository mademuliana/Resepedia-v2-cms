<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CouriersSeeder extends Seeder
{
    public function run(): void
    {
        $couriers = [
            ['name' => 'GoSend', 'type' => 'third_party', 'phone' => '628111000000', 'notes' => 'Same-day delivery', 'active' => true],
            ['name' => 'JNE',    'type' => 'third_party', 'phone' => '628222000000', 'notes' => 'Next-day for outer areas', 'active' => true],
        ];

        foreach ($couriers as $c) {
            Courier::firstOrCreate(
                ['name' => $c['name']],
                ['type' => $c['type'], 'phone' => $c['phone'], 'notes' => $c['notes'], 'active' => $c['active']]
            );
        }
    }
}
