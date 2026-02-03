<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();

            // Tipo de unidad: consolidator | office | counter | freelance
            $table->string('type', 20);

            // Unidad padre (nullable). Se referencia a esta misma tabla.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('business_units')
                ->restrictOnDelete();

            // Identificación básica
            $table->string('name');
            $table->string('status', 20)->default('active'); // active | suspended | archived

            // Branding (hex opcional, se normaliza en el modelo)
            $table->string('branding_text_dark', 12)->nullable();
            $table->string('branding_bg_light', 12)->nullable();
            $table->string('branding_text_light', 12)->nullable();
            $table->string('branding_bg_dark', 12)->nullable();

            // Logo asociado (archivo)
            $table->foreignId('branding_logo_file_id')
                ->nullable()
                ->constrained('files')
                ->nullOnDelete();

            $table->timestamps();

            // Índices recomendados
            $table->index('type');
            $table->index('parent_id');
            $table->index('status');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_units');
    }
};
