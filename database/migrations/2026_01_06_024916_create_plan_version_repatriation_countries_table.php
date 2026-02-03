<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('plan_version_repatriation_countries', function (Blueprint $table) {
			$table->id();
			$table->unsignedBigInteger('plan_version_id');
			$table->unsignedBigInteger('country_id');
			$table->timestamps();

			$table->foreign('plan_version_id')
				->references('id')
				->on('plan_versions')
				->onDelete('cascade');

			$table->foreign('country_id')
				->references('id')
				->on('countries')
				->onDelete('restrict');

			$table->unique(['plan_version_id', 'country_id'], 'plan_version_repatriation_country_unique');
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('plan_version_repatriation_countries');
	}
};
