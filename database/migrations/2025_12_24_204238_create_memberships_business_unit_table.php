<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships_business_unit', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_unit_id')
                ->constrained('business_units')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Rol de Spatie dentro de la unidad (no nullable por diseño)
            $table->foreignId('role_id')
                ->constrained('roles')
                ->restrictOnDelete();

            $table->timestamps();

            // Un usuario solo puede tener una membresía por unidad
            $table->unique(
                ['business_unit_id', 'user_id'],
                'memberships_bu_unit_user_unique'
            );

            $table->index('user_id');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships_business_unit');
    }
};
