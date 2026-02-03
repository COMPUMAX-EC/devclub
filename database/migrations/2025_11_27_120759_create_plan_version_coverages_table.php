<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_version_coverages')) {
            return;
        }

        Schema::create('plan_version_coverages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('plan_version_id');
            $table->unsignedBigInteger('coverage_id');

            // Alcances según tipo de unidad
            $table->integer('value_int')->nullable();
            $table->decimal('value_decimal', 12, 2)->nullable();
            $table->json('value_text')->nullable(); // json es/en

            // Observaciones también en json es/en
            $table->json('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('plan_version_id')
                ->references('id')->on('plan_versions')
                ->onDelete('cascade');

            $table->foreign('coverage_id')
                ->references('id')->on('coverages')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_version_coverages');
    }
};
