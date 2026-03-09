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
        Schema::table('one_time_payments', function (Blueprint $table) {
            $table->tinyInteger('from_payroll')->default(0)->after('everee_paymentId');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_to_one_time_payments', function (Blueprint $table) {
            //
        });
    }
};
