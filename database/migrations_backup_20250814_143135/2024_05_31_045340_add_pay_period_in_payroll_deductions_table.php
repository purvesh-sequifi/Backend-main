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
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->date('pay_period_from')->nullable()->after('subtotal');
            $table->date('pay_period_to')->nullable()->after('pay_period_from');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            //
        });
    }
};
