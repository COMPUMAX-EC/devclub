<?php
// database/migrations/2026_01_19_001614_create_capitados_batch_item_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitados_batch_item_logs', function (Blueprint $table) {
            $table->id();

            // Referencia a cabecera de batch
            $table->unsignedBigInteger('batch_id');

            // Ubicación en el Excel
            $table->string('sheet_name', 128)->nullable();
            $table->unsignedInteger('row_number')->nullable();

            // Plan / versión
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('plan_version_id')->nullable();

            // Países: texto crudo y código extraído (ISO2/ISO3)
            $table->string('residence_raw', 255)->nullable();
            $table->string('residence_code_extracted', 8)->nullable();

            $table->string('repatriation_raw', 255)->nullable();
            $table->string('repatriation_code_extracted', 8)->nullable();

            // Países detectados (FK a countries.id)
            $table->unsignedBigInteger('residence_country_id')->nullable();
            $table->unsignedBigInteger('repatriation_country_id')->nullable();

            // Datos de persona desde el Excel
            $table->string('document_number', 64)->nullable();
            $table->string('full_name', 255)->nullable();
            $table->string('sex', 1)->nullable();
            $table->unsignedInteger('age_reported')->nullable();

            // Resultado de la fila
            // applied | rejected | incongruence | duplicated
            $table->string('result', 32)->nullable();
            $table->string('rejection_code', 64)->nullable();
            $table->text('rejection_detail')->nullable();

            // Referencias a entidades principales
            $table->unsignedBigInteger('person_id')->nullable();            // capitados_product_insureds.id
            $table->unsignedBigInteger('contract_id')->nullable();          // capitados_contracts.id
            $table->unsignedBigInteger('monthly_record_id')->nullable();    // capitados_monthly_records.id
            $table->unsignedBigInteger('duplicated_record_id')->nullable(); // registro mensual duplicado

            $table->timestamps();

            // Índices
            $table->index('batch_id');
            $table->index(['sheet_name', 'row_number']);
            $table->index('product_id');
            $table->index('result');
            $table->index('rejection_code');
            $table->index('document_number');
            $table->index('residence_country_id');
            $table->index('repatriation_country_id');

            // Foreign keys
            $table->foreign('batch_id')
                ->references('id')
                ->on('capitados_batch_logs');

            $table->foreign('product_id')
                ->references('id')
                ->on('products');

            $table->foreign('plan_version_id')
                ->references('id')
                ->on('plan_versions');

            $table->foreign('person_id')
                ->references('id')
                ->on('capitados_product_insureds');

            $table->foreign('contract_id')
                ->references('id')
                ->on('capitados_contracts');

            $table->foreign('monthly_record_id')
                ->references('id')
                ->on('capitados_monthly_records');

            $table->foreign('duplicated_record_id')
                ->references('id')
                ->on('capitados_monthly_records');

            $table->foreign('residence_country_id')
                ->references('id')
                ->on('countries');

            $table->foreign('repatriation_country_id')
                ->references('id')
                ->on('countries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitados_batch_item_logs');
    }
};
