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
        DB::statement("ALTER TABLE `user_agreement_histories`
            CHANGE `offer_include_bonus` `offer_include_bonus` enum('1','0') NULL DEFAULT '0' AFTER `old_probation_period`,
            CHANGE `old_offer_include_bonus` `old_offer_include_bonus` enum('1','0') NULL DEFAULT '0' AFTER `offer_include_bonus`;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_agreement_histories', function (Blueprint $table) {
            //
        });
    }
};
