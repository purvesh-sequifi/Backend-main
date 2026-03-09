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
        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            $table->date('salary_overtime_date')->nullable()->after('cost_center_id');
        });
        Schema::table('payroll_adjustment_details_lock', function (Blueprint $table) {
            $table->date('salary_overtime_date')->nullable()->after('cost_center_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_adjustment_details', function (Blueprint $table) {
            //
        });
    }
};
