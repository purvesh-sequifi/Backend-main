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
        Schema::table('user_commission_history', function (Blueprint $table) {
            DB::statement("ALTER TABLE `user_commission_history` CHANGE `commission_type` `commission_type` ENUM('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `user_commission_history` CHANGE `old_commission_type` `old_commission_type` ENUM('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_commission_history', function (Blueprint $table) {
            //
        });
    }
};
