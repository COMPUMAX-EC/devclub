<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crear tabla genérica de regalías.
     *
     * Estructura:
     * - source_type: tipo de origen ("unit", "user", etc.)
     * - source_id:   id del origen, interpretado según source_type
     * - beneficiary_user_id: usuario que recibe la regalía
     * - commission:  porcentaje de regalía (0–100)
     */
    public function up(): void
    {
        Schema::create('regalias', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Origen de la regalía (polimórfico por tipo + id)
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');

            // Usuario beneficiario
            $table->unsignedBigInteger('beneficiary_user_id');

            // Porcentaje de regalía (0–100)
            $table->decimal('commission', 5, 2)->default(0);

            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index(['source_type', 'source_id'], 'regalias_source_index');
            $table->index('beneficiary_user_id', 'regalias_beneficiary_index');

            // Unicidad lógica: un beneficiario no puede tener dos regalías
            // para el mismo origen (source_type + source_id)
            $table->unique(
                ['source_type', 'source_id', 'beneficiary_user_id'],
                'regalias_source_beneficiary_unique'
            );

            // FK a users (beneficiario)
            $table->foreign('beneficiary_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Revertir creación de tabla.
     */
    public function down(): void
    {
        Schema::dropIfExists('regalias');
    }
};
