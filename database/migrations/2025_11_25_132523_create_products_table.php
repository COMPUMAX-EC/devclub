<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Nombre multilenguaje JSON (ES/EN)
            $table->json('name');

            // Tipo de producto: plan_regular, plan_capitado, etc.
            $table->string('product_type', 50);

            // Mostrar en widget de home
            $table->boolean('show_in_widget')->default(false);

            $table->timestamps();

            $table->index('status');
            $table->index('product_type');
            $table->index('show_in_widget');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
