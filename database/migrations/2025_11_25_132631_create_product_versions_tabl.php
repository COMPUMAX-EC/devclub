<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_versions', function (Blueprint $table) {
            $table->id();

            // Relación con el producto padre
            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete(); // no borrar producto si tiene versiones

			$table->string('name', 255);
			
            // Estado de la versión: solo una activa por producto (regla de negocio en capa app)
            $table->boolean('is_active')->default('inactive');

            // --- Precios ---
            // Costo interno de esta versión
            $table->decimal('cost_price', 15, 2)->nullable();

            // Precio de venta al público
            $table->decimal('public_price', 15, 2)->nullable();

            // --- Parámetros de edad ---
            $table->unsignedInteger('max_entry_age')->nullable();   // edad máx. para contratar
            $table->unsignedInteger('max_renewal_age')->nullable(); // edad máx. para renovar

            // --- Tiempos de carencia (en días) ---
            $table->unsignedInteger('wtime_suicide')->nullable();                // suicidio
            $table->unsignedInteger('wtime_preexisting_conditions')->nullable(); // preexistentes
            $table->unsignedInteger('wtime_accident')->nullable();               // accidente

            // --- Términos y condiciones PDF (archivo centralizado) ---
            $table->foreignId('terms_file_id')
                ->nullable()
                ->constrained('files')
                ->restrictOnDelete(); // no borrar file si hay versión apuntando

            $table->timestamps();

            // Índices
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_versions');
    }
};
