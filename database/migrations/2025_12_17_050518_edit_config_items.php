<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('config_items', function (Blueprint $table) {
            $table->id();

            // Agrupación por categoría
            $table->string('category', 100);
            $table->string('token', 191); // único dentro de la categoría

            $table->string('name');       // nombre visible
            $table->string('type', 100);  // integer, decimal, input_text_plain, file_plain, etc.

            // Configuración / reglas del registro (JSON en TEXT)
            $table->text('config')->nullable();

            // Valores numéricos / lógicos
            $table->bigInteger('value_int')->nullable();         // integer / boolean
            $table->decimal('value_decimal', 15, 2)->nullable(); // decimal

            // Texto/HTML sin traducción
            $table->longText('value_text')->nullable();

            // Texto/HTML traducible (JSON en TEXT)
            $table->longText('value_trans')->nullable();

            // Archivos (IDs en tabla files)
            $table->foreignId('value_file_plain_id')
                ->nullable()
                ->constrained('files')
                ->nullOnDelete();

            $table->foreignId('value_file_es_id')
                ->nullable()
                ->constrained('files')
                ->nullOnDelete();

            $table->foreignId('value_file_en_id')
                ->nullable()
                ->constrained('files')
                ->nullOnDelete();

            // Fecha
            $table->date('value_date')->nullable();

            $table->timestamps();

            // Índices
            $table->unique(['category', 'token']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_items');
    }
};
