<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompaniesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('companies')->updateOrInsert(
            ['name' => 'Resepedia Jakarta'],
            [
                'email' => 'jakarta@resepedia.test',
                'phone' => '628111000111',
                'website' => 'https://jakarta.resepedia.test',
                'city' => 'Jakarta',
                'country' => 'ID',
                'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]
        );

        DB::table('companies')->updateOrInsert(
            ['name' => 'Resepedia Bandung'],
            [
                'email' => 'bandung@resepedia.test',
                'phone' => '628222000222',
                'website' => 'https://bandung.resepedia.test',
                'city' => 'Bandung',
                'country' => 'ID',
                'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]
        );
    }
}
