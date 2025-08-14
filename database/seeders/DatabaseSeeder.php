<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            IngredientsSeeder::class,
            RecipesSeeder::class,
            ProductsSeeder::class,
            CustomersAndAddressesSeeder::class,
            CouriersSeeder::class,
            OrdersSeeder::class,
        ]);
    }
}
