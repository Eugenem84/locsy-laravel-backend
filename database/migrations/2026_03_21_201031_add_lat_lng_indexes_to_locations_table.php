<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Создаем составной индекс для двух колонок.
            // Это самый эффективный вариант для вашего запроса.
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Laravel автоматически назовет индекс 'locations_latitude_longitude_index'
            $table->dropIndex(['latitude', 'longitude']);
        });
    }
};
