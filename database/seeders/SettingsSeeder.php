<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->updateOrInsert(
            [
                'group' => 'moderation',
                'name' => 'location_moderation_enabled',
            ],
            [
                'payload' => json_encode(false),
                'locked' => false,
            ]
        );
    }
}
