<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batches
        Schema::table('capitados_batch_logs', function (Blueprint $table) {
            // Si ya existe 'status' no lo tocamos, solo añadimos columnas de rollback
            $table->timestamp('rolled_back_at')->nullable()->after('processed_at');
            $table->unsignedBigInteger('rolled_back_by_user_id')->nullable()->after('rolled_back_at');
        });

        // Contratos
        Schema::table('capitados_contracts', function (Blueprint $table) {
            // 'status' ya existe (active, expired, voided). No lo tocamos.
            $table->timestamp('rolled_back_at')->nullable()->after('termination_reason');
            $table->unsignedBigInteger('rolled_back_by_user_id')->nullable()->after('rolled_back_at');
        });

        // Registros mensuales
        Schema::table('capitados_monthly_records', function (Blueprint $table) {
            // Nueva columna de estado para poder marcar rolled_back
            $table->string('status', 32)->nullable()->after('price_final');
            $table->timestamp('rolled_back_at')->nullable()->after('status');
            $table->unsignedBigInteger('rolled_back_by_user_id')->nullable()->after('rolled_back_at');
        });

        // Fichas de personas aseguradas
        Schema::table('capitados_product_insureds', function (Blueprint $table) {
            $table->string('status', 32)->nullable()->after('age_reported');
            $table->timestamp('rolled_back_at')->nullable()->after('status');
            $table->unsignedBigInteger('rolled_back_by_user_id')->nullable()->after('rolled_back_at');
        });
    }

    public function down(): void
    {
        Schema::table('capitados_batch_logs', function (Blueprint $table) {
            $table->dropColumn(['rolled_back_at', 'rolled_back_by_user_id']);
        });

        Schema::table('capitados_contracts', function (Blueprint $table) {
            $table->dropColumn(['rolled_back_at', 'rolled_back_by_user_id']);
        });

        Schema::table('capitados_monthly_records', function (Blueprint $table) {
            $table->dropColumn(['status', 'rolled_back_at', 'rolled_back_by_user_id']);
        });

        Schema::table('capitados_product_insureds', function (Blueprint $table) {
            $table->dropColumn(['status', 'rolled_back_at', 'rolled_back_by_user_id']);
        });
    }
};
