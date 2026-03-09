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
        Schema::table('temp_payroll_finalize_execute_details', function (Blueprint $table) {
            $table->string('worker_type')->after('pay_period_to')->comment('1099, w2')->nullable();
            $table->unsignedBigInteger('pay_frequency')->after('worker_type')->comment('1: Weekly, 2: Monthly, 3: Bi-Weekly, 4: Semi-Monthly, 5: Daily Pay')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('temp_payroll_finalize_execute_details', function (Blueprint $table) {
            //
        });
    }
};
