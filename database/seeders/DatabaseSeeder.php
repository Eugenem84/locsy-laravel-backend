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
            SettingsSeeder::class,
            Admin1CodesSeeder::class,
            CitiesTableSeeder::class,
            Admin1CodeTranslationSeeder::class,
            CategorySeeder::class,
        ]);
    }
}
