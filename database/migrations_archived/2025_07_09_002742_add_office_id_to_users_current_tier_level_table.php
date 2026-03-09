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
            $table->unsignedBigInteger('office_id')->after('next_tier_schema_level_id')->nullable();
        });
        Schema::table('users_tiers_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->after('next_tier_schema_level_id')->nullable();
        });
        Schema::table('sale_tiers_details', function (Blueprint $table) {
            $table->unsignedBigInteger('office_id')->after('user_id')->nullable();
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
