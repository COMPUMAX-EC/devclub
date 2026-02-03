<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

	public function up(): void
	{
		Schema::create('terms', function (Blueprint $table)
		{
			$table->id(); // correlativo que usaremos como version
			$table->string('title');
			$table->text('summary')->nullable(); // opcional: un resumen corto
			$table->string('pdf_path')->nullable();     // ruta relativa en storage/app/public/...
			$table->string('pdf_original_name')->nullable(); // nombre de archivo original (opcional)
			$table->boolean('is_active')->default(false); // versión vigente
			$table->timestamp('published_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('terms');
	}
};
