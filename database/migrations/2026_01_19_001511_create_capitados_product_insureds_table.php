<?php
// database/migrations/2026_01_19_001511_create_capitados_product_insureds_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitados_product_insureds', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_id');

            $table->string('document_number', 64);

            $table->string('full_name', 255);
            $table->string('sex', 1); // M / F

            // Países normalizados (FK a countries.id)
            $table->unsignedBigInteger('residence_country_id')->nullable();
            $table->unsignedBigInteger('repatriation_country_id')->nullable();

            $table->unsignedInteger('age_reported')->nullable();

            $table->timestamps();

            // Unicidad por company + product + documento
            $table->unique(
                ['company_id', 'product_id', 'document_number'],
                'capitados_insureds_company_product_document_unique'
            );

            $table->index('company_id');
            $table->index('product_id');
            $table->index('document_number');

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('companies');

            $table->foreign('product_id')
                ->references('id')
                ->on('products');

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
        Schema::dropIfExists('capitados_product_insureds');
    }
};
