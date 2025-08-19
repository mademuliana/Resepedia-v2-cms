<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompaniesSeeder::class,
            UsersSeeder::class,                 // 2 admins (each company) + 1 super_admin
            IngredientsSeeder::class,          // global (no company_id)
            RecipesSeeder::class,              // per company
            RecipeStepsSeeder::class,          // per company
            ProductsSeeder::class,             // per company
            CustomersAndAddressesSeeder::class,// per company
            CouriersSeeder::class,             // per company
            OrdersSeeder::class,               // per company (scoped to each)
        ]);
    }
}
