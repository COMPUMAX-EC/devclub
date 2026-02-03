<?php
// database/migrations/2026_01_19_001626_create_capitados_void_reasons_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitados_void_reasons', function (Blueprint $table) {
            $table->id();

            $table->string('label', 255);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitados_void_reasons');
    }
};
