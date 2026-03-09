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
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            //
            $table->string('hourlysalary_type')->default('hourlysalary')->after('reconciliations_amount');
            $table->double('hourlysalary_amount', 6, 2)->nullable()->after('hourlysalary_type');
            $table->string('overtime_type')->default('overtime')->after('hourlysalary_amount');
            $table->double('overtime_amount', 6, 2)->nullable()->after('overtime_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            //
        });
    }
};
