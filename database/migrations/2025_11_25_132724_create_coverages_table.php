<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverages', function (Blueprint $table) {
            $table->id();

            // Textos multilenguaje
            $table->json('name');                    // JSON ES/EN
            $table->json('description')->nullable(); // JSON ES/EN

            // Unidad de medida
            $table->foreignId('unit_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();

            // Categoría de la cobertura
            $table->foreignId('category_id')
                ->constrained('coverage_categories')
                ->restrictOnDelete();

            // active / archived
            $table->string('status', 20)->default('active');

            // Orden dentro de la categoría (drag & drop)
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('status');
            $table->index(['category_id', 'sort_order']);
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverages');
    }
};
