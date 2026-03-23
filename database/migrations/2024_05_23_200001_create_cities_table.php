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
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            // --- Поля из GeoNames ---
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('geonameid')->unique()->nullable();
            $table->string('asciiname')->nullable();
            $table->text('alternatenames')->nullable();
            $table->char('feature_class', 1)->nullable();
            $table->string('feature_code')->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('cc2')->nullable();
            $table->string('admin1_code')->nullable();
            $table->string('admin2_code')->nullable();
            $table->unsignedBigInteger('population')->default(0);
            $table->string('timezone')->nullable();
            $table->date('modification_date')->nullable();

            // --- Составной уникальный ключ (слаг + код страны + код региона) ---
            $table->unique(['slug', 'country_code', 'admin1_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
