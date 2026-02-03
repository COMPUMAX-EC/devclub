<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            // Nombre traducible (ES/EN) almacenado como JSON en columna de texto
            $table->longText('name');

            $table->string('continent_code', 2);
            $table->string('phone_code', 10)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('continent_code');
            $table->index('phone_code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
