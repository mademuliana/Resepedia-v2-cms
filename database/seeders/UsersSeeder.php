<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $jakartaId = DB::table('companies')->where('name', 'Resepedia Jakarta')->value('id');
        $bandungId = DB::table('companies')->where('name', 'Resepedia Bandung')->value('id');

        // Admins (each belongs to exactly one company)
        User::updateOrCreate(
            ['email' => 'jakarta@resepedia.test'],
            ['name' => 'Admin One', 'password' => Hash::make('resepedia'), 'role' => 'admin', 'company_id' => $jakartaId]
        );

        User::updateOrCreate(
            ['email' => 'bandung@resepedia.test'],
            ['name' => 'Admin Two', 'password' => Hash::make('resepedia'), 'role' => 'admin', 'company_id' => $bandungId]
        );

        // Super admin (can see all; company_id = null)
        User::updateOrCreate(
            ['email' => 'super@resepedia.test'],
            ['name' => 'Super Admin', 'password' => Hash::make('resepedia'), 'role' => 'super_admin', 'company_id' => null]
        );
    }
}
