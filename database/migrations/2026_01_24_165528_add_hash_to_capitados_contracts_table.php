<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHashToCapitadosContractsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('capitados_contracts', function (Blueprint $table) {
            // UUID estilo estándar (char(36))
            $table->uuid('uuid')->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capitados_contracts', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}
