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
        if (Schema::hasTable('payroll_overtimes') && !Schema::hasColumn('payroll_overtimes', 'overtime_hours')) {
            Schema::table('payroll_overtimes', function (Blueprint $table) {
                $table->string('overtime_hours')->after('overtime')->nullable();
            });
        }
        if (Schema::hasTable('payroll_overtimes_lock') && !Schema::hasColumn('payroll_overtimes_lock', 'overtime_hours')) {
            Schema::table('payroll_overtimes_lock', function (Blueprint $table) {
                $table->string('overtime_hours')->after('overtime')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_overtimes', function (Blueprint $table) {
            //
        });
    }
};
