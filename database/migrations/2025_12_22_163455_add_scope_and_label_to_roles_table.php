<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Contexto del rol (system, matrix, agency, freelance, etc.)
            $table->string('scope', 50)
                ->nullable()
                ->after('guard_name')
                ->index();

            // Etiqueta traducible serializada como JSON en un TEXT.
            // Ej: {"es": "Administrador", "en": "Administrator"}
            $table->text('label')
                ->nullable()
                ->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label', 'scope']);
        });
    }
};
