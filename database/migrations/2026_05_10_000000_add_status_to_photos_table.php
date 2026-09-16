<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Модерация фотографий: статус проверки + причина отклонения.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('status')->default('pending')->index();
            $table->string('moderation_note')->nullable();
        });

        // Фотографии, загруженные до введения модерации, считаем проверенными,
        // чтобы уже опубликованные галереи не исчезли из каталога.
        DB::table('photos')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'moderation_note']);
        });
    }
};
