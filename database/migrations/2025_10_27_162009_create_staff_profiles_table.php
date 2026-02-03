<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('staff_profiles', function (Blueprint $table) {
            // PK=FK (1:1 real con users)
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();

            // Contacto laboral
            $table->string('work_phone', 30)->nullable();

			$table->decimal('commission_regular_first_year_pct', 5, 2)->nullable();
			$table->decimal('commission_regular_renewal_pct', 5, 2)->nullable();
			$table->decimal('commission_capitados_pct', 5, 2)->nullable();

			// Preferencias / Notas
            $table->text('notes_admin')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('staff_profiles');
    }
};
