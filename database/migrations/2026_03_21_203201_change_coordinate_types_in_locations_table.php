<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Используем "сырой" SQL-запрос, так как PostgreSQL требует явного указания преобразования типа (USING)
        // Это стандартная практика при работе с Laravel и PostgreSQL для изменения типов данных.
        DB::statement('ALTER TABLE locations ALTER COLUMN latitude TYPE DECIMAL(10, 7) USING latitude::numeric(10,7)');
        DB::statement('ALTER TABLE locations ALTER COLUMN longitude TYPE DECIMAL(10, 7) USING longitude::numeric(10,7)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Откат миграции, возвращаем тип VARCHAR
        DB::statement('ALTER TABLE locations ALTER COLUMN latitude TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE locations ALTER COLUMN longitude TYPE VARCHAR(255)');
    }
};
