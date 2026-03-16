<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Photo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhotoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Очищаем таблицу photos для избежания дубликатов при повторном запуске
        DB::table('photos')->truncate();

        $locations = Location::all();

        foreach ($locations as $location) {
            $photoCount = rand(3, 5); // 3-5 фото для каждой локации

            for ($i = 0; $i < $photoCount; $i++) {
                Photo::create([
                    'location_id' => $location->id,
                    // Генерируем уникальный URL для каждой картинки
                    'path' => 'https://picsum.photos/seed/' . $location->id . '_' . $i . '/800/600',
                    'is_main' => $i === 0, // Первое фото будет главным
                ]);
            }
        }
    }
}
