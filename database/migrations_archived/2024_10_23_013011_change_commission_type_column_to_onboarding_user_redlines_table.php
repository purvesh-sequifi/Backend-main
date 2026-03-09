<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('onboarding_user_redlines', function (Blueprint $table) {
            DB::statement("ALTER TABLE `onboarding_user_redlines` CHANGE `commission_type` `commission_type` ENUM('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('onboarding_user_redlines', function (Blueprint $table) {
            //
        });
    }
};
