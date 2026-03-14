<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Location::truncate();

        $city = City::where('slug', 'new-york')->first();

        if ($city) {
            Location::create([
                'city_id' => $city->id,
                'name' => 'Central Park',
                'description' => 'A large public park in Manhattan.',
                'latitude' => '40.785091',
                'longitude' => '-73.968285',
            ]);

            Location::create([
                'city_id' => $city->id,
                'name' => 'Times Square',
                'description' => 'A major commercial intersection, tourist destination, entertainment center, and neighborhood.',
                'latitude' => '40.7580',
                'longitude' => '-73.9855',
            ]);
        }
    }
}
