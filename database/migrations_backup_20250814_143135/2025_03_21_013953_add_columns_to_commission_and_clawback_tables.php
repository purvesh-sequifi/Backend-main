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
        Schema::table('user_commission_lock', function (Blueprint $table) {
            $table->double('commission_amount', 8, 2)->nullable();
            $table->enum('commission_type', ['percent', 'per kw', 'per sale'])->nullable();
        });

        Schema::table('user_commission', function (Blueprint $table) {
            $table->double('commission_amount', 8, 2)->nullable();
            $table->enum('commission_type', ['percent', 'per kw', 'per sale'])->nullable();
        });

        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            $table->double('clawback_cal_amount', 8, 2)->nullable();
            $table->enum('clawback_cal_type', ['percent', 'per kw', 'per sale'])->nullable();
        });

        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->double('clawback_cal_amount', 8, 2)->nullable();
            $table->enum('clawback_cal_type', ['percent', 'per kw', 'per sale'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_commission_lock', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_type']);
        });

        Schema::table('user_commission', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'commission_type']);
        });

        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            $table->dropColumn(['clawback_cal_amount', 'clawback_cal_type']);
        });

        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->dropColumn(['clawback_cal_amount', 'clawback_cal_type']);
        });
    }
};
