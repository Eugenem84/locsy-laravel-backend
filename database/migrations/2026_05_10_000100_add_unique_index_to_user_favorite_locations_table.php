<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Защита избранного от дублей: один пользователь — одна локация в избранном.
     */
    public function up(): void
    {
        // Сначала убираем уже существующие дубликаты (если есть)
        DB::statement('
            DELETE FROM user_favorite_locations a
            USING user_favorite_locations b
            WHERE a.id > b.id
              AND a.user_id = b.user_id
              AND a.location_id = b.location_id
        ');

        Schema::table('user_favorite_locations', function (Blueprint $table) {
            $table->unique(['user_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_favorite_locations', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'location_id']);
        });
    }
};
