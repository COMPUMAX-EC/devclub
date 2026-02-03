<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Login / ámbito
            $table->enum('realm', ['backoffice','customer'])->index();
            $table->string('email');               // sin UNIQUE directo
            $table->string('password');

            // Identidad
            $table->string('first_name', 100);
            $table->string('last_name', 120);
            $table->string('display_name', 120)->nullable();

            // Estado y verificación
            $table->enum('status', ['active','suspended','locked'])
                  ->default('active')->index();
            $table->timestamp('email_verified_at')->nullable();

            // Preferencias & auditoría
            $table->string('locale', 5)->default('es');
            $table->string('timezone', 50)->default('America/Santiago');
			$table->json('ui_settings_json')->nullable(); // preferencias comunes de UI (globales)
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            // Índices útiles
            $table->index(['email','realm']);
            $table->index(['realm','status']);

            // 🔒 Unicidad "activa" por email+realm (ignora los soft-deleted)
            // Requiere MySQL 8+ (columnas generadas deterministas)
            $table->string('email_realm_active')->virtualAs(
                "CASE WHEN deleted_at IS NULL THEN CONCAT(email,'|',realm) ELSE NULL END"
            );
            $table->unique('email_realm_active', 'users_email_realm_active_unique');
        });

		Schema::create('sessions', function (Blueprint $table)
		{
			$table->string('id')->primary();
			$table->foreignId('user_id')->nullable()->index();
			$table->string('ip_address', 45)->nullable();
			$table->text('user_agent')->nullable();
			$table->longText('payload');
			$table->integer('last_activity')->index();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('users');
		Schema::dropIfExists('sessions');
	}
};
