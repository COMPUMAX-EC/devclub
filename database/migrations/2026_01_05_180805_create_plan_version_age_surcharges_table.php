<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::create('plan_version_age_surcharges', function (Blueprint $table) {
			$table->id();

			$table->unsignedBigInteger('plan_version_id');

			// Ahora permiten null para poder guardar estados intermedios
			$table->unsignedInteger('age_from')->nullable();
			$table->unsignedInteger('age_to')->nullable();

			// Ej: 0.00, 10.50, 150.00, etc.
			// Se mantiene default 0 pero también permitimos null explícito
			$table->decimal('surcharge_percent', 5, 2)->default(0)->nullable();

			$table->timestamps();

			$table
				->foreign('plan_version_id')
				->references('id')
				->on('plan_versions')
				->onDelete('cascade');

			$table->index(['plan_version_id', 'age_from']);
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('plan_version_age_surcharges');
	}
};
