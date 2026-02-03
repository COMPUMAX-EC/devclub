<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->char('iso2', 2)->nullable()->after('name');
            $table->char('iso3', 3)->nullable()->after('iso2');

            $table->unique('iso2', 'countries_iso2_unique');
            $table->unique('iso3', 'countries_iso3_unique');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropUnique('countries_iso2_unique');
            $table->dropUnique('countries_iso3_unique');

            $table->dropColumn(['iso2', 'iso3']);
        });
    }
};
