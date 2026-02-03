<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();

            // En qué disk de Storage está (local, public, s3, etc.)
            $table->string('disk', 50)->default('public');

            // Ruta relativa dentro del disk
            $table->string('path', 512);

            // Nombre original del archivo subido
            $table->string('original_name')->nullable();

            // Mime type detectado
            $table->string('mime_type', 255)->nullable();

            // Tamaño en bytes
            $table->unsignedBigInteger('size')->nullable();

            // Quién lo subió (opcional)
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Metadatos opcionales, para uso humano / debugging.
            // Ejemplo:
            // {
            //   "context": "product_version_terms",
            //   "model": "plan_versions",
            //   "model_id": 263,
            //   "model_field": "terms_file_id"
            // }
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['disk', 'path']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
