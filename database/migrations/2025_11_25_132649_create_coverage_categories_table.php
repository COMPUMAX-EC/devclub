<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_categories', function (Blueprint $table) {
            $table->id();

            // Textos multilenguaje
            $table->json('name');                    // JSON ES/EN
            $table->json('description')->nullable(); // JSON ES/EN

            // active / archived
            $table->string('status', 20)->default('active');

            // Orden de presentación (drag & drop)
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_categories');
    }
};
