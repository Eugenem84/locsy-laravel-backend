<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            LocationSeeder::class,
            PhotoSeeder::class, // <-- Регистрируем сидер фотографий
        ]);
    }
}
