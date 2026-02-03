<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('customer_profiles', function (Blueprint $table) {
            // PK=FK (1:1 real con users)
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();

            // Contacto
            $table->string('mobile_e164', 20)->nullable();
            $table->string('alt_email', 190)->nullable();

            // Documento / personales
            $table->string('doc_type', 30)->nullable();
            $table->text('doc_number')->nullable(); // cifra en el modelo si aplica
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male','female','other'])->nullable();

            // Dirección y preferencias
            $table->json('home_address_json')->nullable();
            $table->enum('preferred_language', ['es','en'])->default('es');
            $table->enum('contact_via', ['email','whatsapp','sms'])->default('email');

            // Emergencia
            $table->string('emergency_name', 160)->nullable();
            $table->string('emergency_phone_e164', 20)->nullable();
            $table->string('emergency_relation', 60)->nullable();

            // Facturación y notas
            $table->string('billing_name', 160)->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->json('billing_address_json')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes_internal')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('customer_profiles');
    }
};
