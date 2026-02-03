<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_unit_commission_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('business_unit_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('commission', 8, 2)->default(0);

            $table->timestamps();

            $table->foreign('business_unit_id')
                ->references('id')
                ->on('business_units')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->unique(
                ['business_unit_id', 'user_id'],
                'business_unit_commission_users_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_unit_commission_users');
    }
};
