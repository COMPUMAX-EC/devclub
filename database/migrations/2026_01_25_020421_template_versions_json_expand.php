<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('templates', function (Blueprint $table) {
			$table->mediumText('test_data_json')->nullable()->change();
		});
		Schema::table('template_versions', function (Blueprint $table) {
			$table->mediumText('test_data_json')->nullable()->change();
		});
	}

	public function down(): void
	{
		Schema::table('templates', function (Blueprint $table) {
			$table->text('test_data_json')->nullable()->change();
		});
		Schema::table('template_versions', function (Blueprint $table) {
			$table->text('test_data_json')->nullable()->change();
		});
	}
};
