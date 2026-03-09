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
        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->tinyInteger('clawback_status')->default(0)->after('pay_period_to');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clawback_settlements', function (Blueprint $table) {
            //
        });
    }
};
