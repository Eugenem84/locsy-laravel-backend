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
        // Список стран для загрузки. Для текущего кейса оставляем только РФ.
        $allowedCountries = ['RU'];
        $allowedCountrySet = array_fill_keys($allowedCountries, true);

        // Только населенные пункты (города/поселения), без районов и прочих ADM*.
        $allowedFeatureCodes = array_fill_keys([
            'PPL',
            'PPLA',
            'PPLA2',
            'PPLA3',
            'PPLA4',
            'PPLC',
        ], true);

        $path = database_path('seeders/citiesTXT/cities15000.txt');

        if (!File::exists($path)) {
            $this->command->error('File not found: database/seeders/citiesTXT/cities15000.txt');
            return;
        }

        $validAdmin1 = DB::table('admin1_codes')
            ->select('country_code', 'admin1_code')
            ->whereIn('country_code', $allowedCountries)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->country_code . '.' . $row->admin1_code => true])
            ->all();

        if (empty($validAdmin1)) {
            $this->command->error('admin1_codes is empty for selected countries. Seed admin1_codes first.');
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
                $progressBar->advance();
                continue;
            }

            $countryCode = $data[8];
            $featureClass = $data[6];
            $featureCode = $data[7];
            $admin1Code = $data[10];

            if (!isset($allowedCountrySet[$countryCode])) {
                $progressBar->advance();
                continue;
            }

            if ($featureClass !== 'P' || !isset($allowedFeatureCodes[$featureCode])) {
                $progressBar->advance();
                continue;
            }

            if (!isset($validAdmin1[$countryCode . '.' . $admin1Code])) {
                $progressBar->advance();
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
                'feature_class'     => $featureClass,
                'feature_code'      => $featureCode,
                'country_code'      => $countryCode,
                'cc2'               => $data[9],
                'admin1_code'       => $admin1Code,
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
