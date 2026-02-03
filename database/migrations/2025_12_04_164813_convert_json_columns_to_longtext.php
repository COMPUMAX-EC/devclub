<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasa todos los campos JSON a LONGTEXT (compatible con MariaDB antiguo).
     *
     * OJO: para que ->change() funcione necesitas tener instalado doctrine/dbal:
     * composer require doctrine/dbal --dev
     */
    public function up(): void
    {
        // audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->longText('context_json')->nullable()->change();
        });

        // coverage_categories
        Schema::table('coverage_categories', function (Blueprint $table) {
            $table->longText('name')->change();            // NOT NULL
            $table->longText('description')->nullable()->change();
        });

        // coverages
        Schema::table('coverages', function (Blueprint $table) {
            $table->longText('name')->change();            // NOT NULL
            $table->longText('description')->nullable()->change();
        });

        // customer_profiles
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->longText('home_address_json')->nullable()->change();
            $table->longText('billing_address_json')->nullable()->change();
            $table->longText('tags')->nullable()->change();
        });

        // files
        Schema::table('files', function (Blueprint $table) {
            $table->longText('meta')->nullable()->change();
        });

        // plan_version_coverages
        Schema::table('plan_version_coverages', function (Blueprint $table) {
            $table->longText('value_text')->nullable()->change();
            $table->longText('notes')->nullable()->change();
        });

        // products
        Schema::table('products', function (Blueprint $table) {
            $table->longText('name')->change();            // NOT NULL
            $table->longText('description')->nullable()->change();
        });

        // system_settings
        Schema::table('system_settings', function (Blueprint $table) {
            $table->longText('value_json')->nullable()->change();
        });

        // units_of_measure
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->longText('name')->change();            // NOT NULL
            $table->longText('description')->nullable()->change();
        });

        // user_preferences
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->longText('value_json')->nullable()->change();
        });

        // users
        Schema::table('users', function (Blueprint $table) {
            $table->longText('ui_settings_json')->nullable()->change();
        });
    }

    /**
     * Intenta volver a JSON (solo si el motor lo soporta).
     *
     * En tu servidor MariaDB antiguo probablemente FALLARÁ,
     * así que pensa el down solo para tu entorno local con MySQL 8.
     */
    public function down(): void
    {
        // audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->json('context_json')->nullable()->change();
        });

        // coverage_categories
        Schema::table('coverage_categories', function (Blueprint $table) {
            $table->json('name')->change();                // NOT NULL
            $table->json('description')->nullable()->change();
        });

        // coverages
        Schema::table('coverages', function (Blueprint $table) {
            $table->json('name')->change();                // NOT NULL
            $table->json('description')->nullable()->change();
        });

        // customer_profiles
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->json('home_address_json')->nullable()->change();
            $table->json('billing_address_json')->nullable()->change();
            $table->json('tags')->nullable()->change();
        });

        // files
        Schema::table('files', function (Blueprint $table) {
            $table->json('meta')->nullable()->change();
        });

        // plan_version_coverages
        Schema::table('plan_version_coverages', function (Blueprint $table) {
            $table->json('value_text')->nullable()->change();
            $table->json('notes')->nullable()->change();
        });

        // products
        Schema::table('products', function (Blueprint $table) {
            $table->json('name')->change();                // NOT NULL
            $table->json('description')->nullable()->change();
        });

        // system_settings
        Schema::table('system_settings', function (Blueprint $table) {
            $table->json('value_json')->nullable()->change();
        });

        // units_of_measure
        Schema::table('units_of_measure', function (Blueprint $table) {
            $table->json('name')->change();                // NOT NULL
            $table->json('description')->nullable()->change();
        });

        // user_preferences
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('value_json')->nullable()->change();
        });

        // users
        Schema::table('users', function (Blueprint $table) {
            $table->json('ui_settings_json')->nullable()->change();
        });
    }
};
