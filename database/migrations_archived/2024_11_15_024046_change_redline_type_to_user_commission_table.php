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
        DB::statement("ALTER TABLE `user_commission` CHANGE `redline_type` `redline_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_commission', function (Blueprint $table) {
            //
        });
    }
};
