<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('capitados_batch_logs', function (Blueprint $table) {
            $table->unsignedInteger('total_rolled_back')
                ->default(0)
                ->after('total_plan_errors');
        });
    }

    public function down(): void
    {
        Schema::table('capitados_batch_logs', function (Blueprint $table) {
            $table->dropColumn('total_rolled_back');
        });
    }
};
