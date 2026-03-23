<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Путь к файлу
        $path = database_path('seeders/citiesTXT/cities15000.txt');

        if (!File::exists($path)) {
            $this->command->error('File not found: database/seeders/citiesTXT/cities15000.txt');
            return;
        }

        $this->command->info('Starting to seed cities from cities15000.txt...');

        // Очищаем таблицу перед заполнением
        DB::table('cities')->truncate();

        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $chunk = [];
        $chunkSize = 500; // Вставляем по 500 записей за раз для лучшей производительности

        // Создаем прогресс-бар для наглядности
        $totalLines = iterator_count($file); // Считаем строки для прогресс-бара
        $file->rewind(); // Возвращаемся в начало файла
        $progressBar = $this->command->getOutput()->createProgressBar($totalLines);

        foreach ($file as $line) {
            $data = explode("	", $line);

            // Пропускаем строки, где не хватает данных
            if (count($data) < 19) {
                continue;
            }

            $chunk[] = [
                'name'              => $data[1],
                'slug'              => Str::slug($data[1]),
                'latitude'          => $data[4],
                'longitude'         => $data[5],
                'geonameid'         => $data[0],
                'asciiname'         => $data[2],
                'alternatenames'    => $data[3],
                'feature_class'     => $data[6],
                'feature_code'      => $data[7],
                'country_code'      => $data[8],
                'cc2'               => $data[9],
                'admin1_code'       => $data[10],
                'admin2_code'       => $data[11],
                'population'        => $data[14],
                'timezone'          => $data[17],
                'modification_date' => $data[18],
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            if (count($chunk) >= $chunkSize) {
                // ИСПОЛЬЗУЕМ insertOrIgnore
                DB::table('cities')->insertOrIgnore($chunk);
                $chunk = [];
            }

            $progressBar->advance();
        }

        // Вставляем оставшиеся записи, если они есть
        if (!empty($chunk)) {
            // ИСПОЛЬЗУEM insertOrIgnore
            DB::table('cities')->insertOrIgnore($chunk);
        }

        $progressBar->finish();
        $this->command->info("\nCities seeding completed successfully!");
    }
}
