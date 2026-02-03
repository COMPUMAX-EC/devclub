<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_versions', function (Blueprint $table) {
            // HTML largo en varios idiomas (JSON con es/en), puede crecer bastante
            $table->mediumText('terms_html')
                ->nullable()
                ->after('terms_file_en_id');
        });
    }

    public function down(): void
    {
        Schema::table('plan_versions', function (Blueprint $table) {
            $table->dropColumn('terms_html');
        });
    }
};
