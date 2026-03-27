<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class Admin1CodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $path = database_path('seeders/citiesTXT/admin1CodesASCII.txt');

        if (!File::exists($path)) {
            $this->command?->error('File not found: database/seeders/citiesTXT/admin1CodesASCII.txt');
            return;
        }

        DB::table('admin1_codes')->truncate();

        $this->command?->info('Seeding admin1 codes from admin1CodesASCII.txt...');

        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_AHEAD | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $chunk = [];
        $chunkSize = 1000;

        foreach ($file as $line) {
            $row = trim((string) $line);
            if ($row === '') {
                continue;
            }

            $parts = explode("\t", $row);
            if (count($parts) < 2) {
                continue;
            }

            [$code, $name] = $parts;
            $codeParts = explode('.', $code, 2);
            if (count($codeParts) !== 2) {
                continue;
            }

            [$countryCode, $admin1Code] = $codeParts;
            if ($countryCode === '' || $admin1Code === '' || $name === '') {
                continue;
            }

            $chunk[] = [
                'country_code' => $countryCode,
                'admin1_code' => $admin1Code,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($chunk) >= $chunkSize) {
                DB::table('admin1_codes')->insertOrIgnore($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            DB::table('admin1_codes')->insertOrIgnore($chunk);
        }

        $this->command?->info('Admin1 codes seeding completed successfully.');
    }
}
