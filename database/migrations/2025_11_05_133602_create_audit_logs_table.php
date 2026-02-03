<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

	public function up(): void
	{
		Schema::create('audit_logs', function (Blueprint $table)
		{
			$table->id();
			$table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
			$table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
			$table->enum('realm', ['admin', 'customer'])->nullable();
			$table->string('action', 120); // e.g., user.status.changed, user.roles.synced, password.reset.sent
			$table->json('context_json')->nullable();
			$table->string('ip', 45)->nullable();
			$table->string('user_agent', 255)->nullable();
			$table->timestamp('created_at')->useCurrent();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('audit_logs');
	}
};
