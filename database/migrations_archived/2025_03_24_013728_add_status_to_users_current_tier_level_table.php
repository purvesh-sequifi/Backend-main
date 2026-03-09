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
        Schema::table('users_tiers_histories', function (Blueprint $table) {
            $table->tinyInteger('status')->default('1')->comment('1 = ENABLED, 0 = DISABLED')->after('remaining_value');
        });
        Schema::table('users_current_tier_level', function (Blueprint $table) {
            $table->tinyInteger('status')->default('1')->comment('1 = ENABLED, 0 = DISABLED')->after('remaining_value');
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
