<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('company_commission_users', function (Blueprint $table) {
			$table->id();

			$table->unsignedBigInteger('company_id');
			$table->unsignedBigInteger('user_id');

			// Comisión con 2 decimales (porcentaje, monto, etc.)
			$table->decimal('commission', 8, 2)->default(0);

			$table->timestamps();

			$table->foreign('company_id')
				->references('id')
				->on('companies')
				->onDelete('cascade');

			$table->foreign('user_id')
				->references('id')
				->on('users')
				->onDelete('cascade');

			$table->unique(['company_id', 'user_id'], 'company_commission_users_unique');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('company_commission_users');
	}
};
