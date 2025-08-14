<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Note: if you don’t have roles/permissions yet, we just seed basic users.
        // You can add roles later via Spatie/your own system.
        $users = [
            ['name' => 'Admin One',  'email' => 'admin1@resepedia.test'],
            ['name' => 'Admin Two',  'email' => 'admin2@resepedia.test'],
            ['name' => 'Super Admin','email' => 'super@resepedia.test'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('resepedia')]
            );
        }
    }
}
