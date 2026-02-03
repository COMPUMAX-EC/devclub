<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_versions', function (Blueprint $table) {
            // País y zona
            $table->unsignedBigInteger('country_id')
                ->nullable()
                ->after('product_id');

            $table->unsignedBigInteger('zone_id')
                ->nullable()
                ->after('country_id');

            // Nuevos precios genéricos
            $table->decimal('price_1', 15, 2)
                ->nullable()
                ->after('public_price');

            $table->decimal('price_2', 15, 2)
                ->nullable()
                ->after('price_1');

            $table->decimal('price_3', 15, 2)
                ->nullable()
                ->after('price_2');

            $table->decimal('price_4', 15, 2)
                ->nullable()
                ->after('price_3');

            // Archivos de términos ES / EN
            $table->unsignedBigInteger('terms_file_es_id')
                ->nullable()
                ->after('terms_file_id');

            $table->unsignedBigInteger('terms_file_en_id')
                ->nullable()
                ->after('terms_file_es_id');

            // FKs
            $table->foreign('country_id')
                ->references('id')
                ->on('countries');

            $table->foreign('zone_id')
                ->references('id')
                ->on('zones');

            $table->foreign('terms_file_es_id')
                ->references('id')
                ->on('files');

            $table->foreign('terms_file_en_id')
                ->references('id')
                ->on('files');
        });
    }

    public function down(): void
    {
        Schema::table('plan_versions', function (Blueprint $table) {
            // Primero soltar FKs
            $table->dropForeign(['country_id']);
            $table->dropForeign(['zone_id']);
            $table->dropForeign(['terms_file_es_id']);
            $table->dropForeign(['terms_file_en_id']);

            // Luego columnas
            $table->dropColumn([
                'country_id',
                'zone_id',
                'price_1',
                'price_2',
                'price_3',
                'price_4',
                'terms_file_es_id',
                'terms_file_en_id',
            ]);
        });
    }
};
