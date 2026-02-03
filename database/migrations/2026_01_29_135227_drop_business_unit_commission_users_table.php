<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eliminar tabla vieja de comisiones GSA por unidad.
     *
     * IMPORTANTE:
     * - Esta migración se ejecuta después de crear la tabla regalias
     *   y de haber actualizado el código para usar SOLO regalias.
     * - NO hay migración de datos: toda la lógica vieja desaparece.
     */
    public function up(): void
    {
        // Cambia el nombre de la tabla si en tu proyecto es distinto.
        if (! Schema::hasTable('business_unit_commission_users')) {
            return;
        }

        Schema::dropIfExists('business_unit_commission_users');
    }

    /**
     * Down intencionadamente vacío.
     *
     * No se recrea la tabla vieja porque el nuevo sistema de regalías
     * (tabla regalias) es la única fuente de verdad.
     */
    public function down(): void
    {
        // Intencionalmente no se recrea la tabla eliminada.
        // Si alguna vez necesitaras la estructura antigua,
        // deberías definirla en una migración independiente.
    }
};
