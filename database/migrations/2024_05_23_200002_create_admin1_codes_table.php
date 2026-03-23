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
        Schema::create('admin1_codes', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2);
            $table->string('admin1_code', 20);
            $table->string('name');
            $table->timestamps();

            $table->unique(['country_code', 'admin1_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin1_codes');
    }
};
