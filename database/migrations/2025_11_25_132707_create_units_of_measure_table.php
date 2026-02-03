<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();

            // Textos multilenguaje
            $table->json('name');                    // JSON ES/EN
            $table->json('description')->nullable(); // JSON ES/EN

            // Tipo de medida:
            // integer -> número entero sin decimales
            // decimal -> número con dos decimales
            // text    -> texto libre multi-idioma
            // none    -> no aplica (no se captura valor)
            $table->string('measure_type', 20); // integer, decimal, text, none

            // active / inactive
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index('measure_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units_of_measure');
    }
};
