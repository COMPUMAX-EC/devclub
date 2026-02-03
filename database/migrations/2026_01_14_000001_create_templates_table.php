<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('templates', function (Blueprint $table) {
			$table->id();
			$table->string('name');
			$table->string('slug')->unique(); // token único
			$table->string('type', 10); // HTML | PDF (no editable por front)
			$table->text('test_data_json')->nullable(); // JSON como TEXTO (sin columna JSON nativa)
			$table->unsignedBigInteger('active_template_version_id')->nullable(); // única fuente de verdad
			$table->timestamps();
			$table->softDeletes();

			$table->index('type');
			$table->index('active_template_version_id');
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('templates');
	}
};
