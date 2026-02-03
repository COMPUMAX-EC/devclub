<?php
// database/migrations/2026_01_19_001558_create_capitados_batch_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitados_batch_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');

            // Mes de cobertura del batch
            $table->date('coverage_month');

            // excel | manual | api (en este corte, solo excel)
            $table->string('source', 32)->default('excel');

            // Integración con tabla files
            $table->unsignedBigInteger('source_file_id')->nullable();
            $table->string('original_filename', 255)->nullable();
            $table->string('file_hash', 64)->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // draft | processed | failed
            $table->string('status', 32)->default('draft');

            $table->timestamp('processed_at')->nullable();

            // Contadores
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('total_applied')->default(0);
            $table->unsignedInteger('total_rejected')->default(0);
            $table->unsignedInteger('total_duplicated')->default(0);
            $table->unsignedInteger('total_incongruences')->default(0);
            $table->unsignedInteger('total_plan_errors')->default(0);

            // Auditoría de reglas
            $table->boolean('is_any_month_allowed')->default(false);
            $table->unsignedTinyInteger('cutoff_day')->nullable(); // ej. 15

            // Mensajes / resumen
            $table->text('error_summary')->nullable();
            $table->text('summary_json')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'coverage_month']);
            $table->index('status');

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')
                ->on('companies');

            $table->foreign('source_file_id')
                ->references('id')
                ->on('files');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitados_batch_logs');
    }
};
