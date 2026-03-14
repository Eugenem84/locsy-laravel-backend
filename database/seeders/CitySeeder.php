<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Очищаем таблицу перед заполнением, чтобы избежать дубликатов
        City::truncate();

        $cities = [
            [
                'name' => 'New York',
                'latitude' => 40.7128,
                'longitude' => -74.0060,
            ],
            [
                'name' => 'London',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
            ],
            [
                'name' => 'Paris',
                'latitude' => 48.8566,
                'longitude' => 2.3522,
            ],
        ];

        foreach ($cities as $cityData) {
            City::create($cityData);
        }
    }
}
