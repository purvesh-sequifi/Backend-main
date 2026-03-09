<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_current_tier_level', function (Blueprint $table) {
            $table->string('current_level')->nullable()->after('remaining_value');
            $table->string('remaining_level')->nullable()->after('current_level');
        });
        Schema::table('users_tiers_histories', function (Blueprint $table) {
            $table->string('current_level')->nullable()->after('remaining_value');
            $table->string('remaining_level')->nullable()->after('current_level');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_current_tier_level', function (Blueprint $table) {
            //
        });
    }
};
