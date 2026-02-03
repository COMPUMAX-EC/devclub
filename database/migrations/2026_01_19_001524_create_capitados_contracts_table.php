<?php
// database/migrations/2026_01_19_001524_create_capitados_contracts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitados_contracts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('person_id'); // FK a capitados_product_insureds

            $table->string('status', 32); // active | expired | voided

            // Inicio del contrato (primer mes aprobado, día 1)
            $table->date('entry_date');

            // Último día del último mes aprobado
            $table->date('valid_until')->nullable();

            $table->unsignedInteger('entry_age')->nullable();

            // Fechas de término de carencias (snapshots)
            $table->date('wtime_suicide_ends_at')->nullable();
            $table->date('wtime_preexisting_conditions_ends_at')->nullable();
            $table->date('wtime_accident_ends_at')->nullable();

            $table->timestamp('terminated_at')->nullable();

            // Motivo libre de término/anulación (texto completo)
            $table->text('termination_reason')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'product_id']);
            $table->index('person_id');
            $table->index('status');

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('companies');

            $table->foreign('product_id')
                ->references('id')
                ->on('products');

            $table->foreign('person_id')
                ->references('id')
                ->on('capitados_product_insureds');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitados_contracts');
    }
};
