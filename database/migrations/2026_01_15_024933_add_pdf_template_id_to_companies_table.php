<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::table('companies', function (Blueprint $table): void
		{
			$table->unsignedBigInteger('pdf_template_id')->nullable()->after('branding_logo_file_id');

			$table->foreign('pdf_template_id')
				->references('id')
				->on('templates')
				->nullOnDelete();
		});
	}

	public function down(): void
	{
		Schema::table('companies', function (Blueprint $table): void
		{
			$table->dropForeign(['pdf_template_id']);
			$table->dropColumn('pdf_template_id');
		});
	}
};
