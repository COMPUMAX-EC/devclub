<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();

            // Datos básicos
            $table->text('name'); // JSON "simulado" de HasTranslatableJson
            $table->string('short_code', 10)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();

            $table->text('description')->nullable(); // también TranslatableJson

            // Estado
            $table->string('status', 32)->default(Company::STATUS_ACTIVE);

            // Branding específico (si se omite se usan defaults)
            $table->string('branding_text_dark', 16)->nullable();
            $table->string('branding_bg_light', 16)->nullable();
            $table->string('branding_text_light', 16)->nullable();
            $table->string('branding_bg_dark', 16)->nullable();

            $table->unsignedBigInteger('branding_logo_file_id')->nullable();

            // Comisiones
            $table->unsignedBigInteger('commission_beneficiary_user_id')->nullable();

            $table->timestamps();

            $table->foreign('branding_logo_file_id')
                ->references('id')
                ->on('files')
                ->nullOnDelete();

            $table->foreign('commission_beneficiary_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
