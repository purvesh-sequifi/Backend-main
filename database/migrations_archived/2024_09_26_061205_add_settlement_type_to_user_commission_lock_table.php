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
        Schema::table('user_commission_lock', function (Blueprint $table) {
            $table->string('settlement_type')->default('during_m2')->after('amount_type')->comment('during_m2, reconciliation');
        });
        DB::statement("
            ALTER TABLE `user_commission_lock`
            CHANGE `amount_type` `amount_type` ENUM('m1', 'm2', 'm2 update', 'reconciliation', 'reconciliation update') NOT NULL;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_commission_lock', function (Blueprint $table) {
            //
        });
    }
};
