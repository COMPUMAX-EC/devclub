<?php
// database/migrations/2026_01_19_001536_create_capitados_monthly_records_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitados_monthly_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('person_id');   // capitados_product_insureds.id
            $table->unsignedBigInteger('contract_id'); // capitados_contracts.id

            // Mes de cobertura normalizado a YYYY-MM-01
            $table->date('coverage_month');

            $table->unsignedBigInteger('plan_version_id');   // plan_versions.id
            $table->unsignedBigInteger('load_batch_id');     // capitados_batch_logs.id (sin FK explícita, ver orden de migraciones)

            // Snapshot de datos de persona aprobados
            $table->string('full_name', 255);
            $table->string('sex', 1);
            $table->unsignedInteger('age_reported')->nullable();

            // Países normalizados (FK a countries.id)
            $table->unsignedBigInteger('residence_country_id')->nullable();
            $table->unsignedBigInteger('repatriation_country_id')->nullable();

            // Auditoría tarifa
            $table->decimal('price_base', 12, 2)->nullable();
            $table->string('price_source', 32)->nullable(); // country | global

            $table->unsignedBigInteger('age_surcharge_rule_id')->nullable();
            $table->decimal('age_surcharge_percent', 5, 2)->nullable();
            $table->decimal('age_surcharge_amount', 12, 2)->nullable();

            $table->decimal('price_final', 12, 2)->nullable();

            $table->timestamps();

            // Unicidad: un aprobado por company+product+persona+mes
            $table->unique(
                ['company_id', 'product_id', 'person_id', 'coverage_month'],
                'capitados_monthly_records_unique'
            );

            $table->index(['company_id', 'product_id']);
            $table->index(['person_id', 'contract_id']);
            $table->index('coverage_month');

            // Foreign keys principales
            $table->foreign('company_id')
                ->references('id')
                ->on('companies');

            $table->foreign('product_id')
                ->references('id')
                ->on('products');

            $table->foreign('person_id')
                ->references('id')
                ->on('capitados_product_insureds');

            $table->foreign('contract_id')
                ->references('id')
                ->on('capitados_contracts');

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions');

            $table->foreign('residence_country_id')
                ->references('id')
                ->on('countries');

            $table->foreign('repatriation_country_id')
                ->references('id')
                ->on('countries');

            // NOTA: no se agrega FK para load_batch_id ni age_surcharge_rule_id aquí
            // para evitar problemas de orden de migraciones / tablas auxiliares.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitados_monthly_records');
    }
};
