<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('template_versions', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('template_id');
			$table->string('name');
			$table->longText('content'); // sin default (MySQL no permite default en TEXT/LONGTEXT)
			$table->text('test_data_json')->nullable(); // JSON como TEXTO (sin columna JSON nativa)
			$table->timestamps();

			$table->foreign('template_id')
				->references('id')
				->on('templates')
				->onDelete('cascade');

			$table->index('template_id');
		});

		// FK opcional para evitar borrar una versión activa referenciada por templates.active_template_version_id
		Schema::table('templates', function (Blueprint $table) {
			$table->foreign('active_template_version_id')
				->references('id')
				->on('template_versions')
				->onDelete('restrict');
		});
	}

	public function down(): void
	{
		Schema::table('templates', function (Blueprint $table) {
			$table->dropForeign(['active_template_version_id']);
		});

		Schema::dropIfExists('template_versions');
	}
};
