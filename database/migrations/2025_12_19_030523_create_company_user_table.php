<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');

            // Lugar para "funciones básicas" futuras (libre por ahora)
            $table->string('basic_functions', 191)->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'user_id']);

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
