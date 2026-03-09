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
        DB::statement("ALTER TABLE `positions` CHANGE `is_selfgen` `is_selfgen` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = None, 1 = SelfGen, 2 = Closer, 3 = Setter';");
        DB::statement("ALTER TABLE `positions` CHANGE `can_act_as_both_setter_and_closer` `can_act_as_both_setter_and_closer` TINYINT(4) NOT NULL DEFAULT '0' COMMENT '0 = None, 1 = SelfGen, 2 = Closer, 3 = Setter';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('positions', function (Blueprint $table) {
            //
        });
    }
};
