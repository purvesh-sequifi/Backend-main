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
        Schema::table('payroll_history', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_history', 'hourly_salary')) {
                $table->double('hourly_salary', 8, 2)->default('0')->nullable()->after('reconciliation');
            }
            if (! Schema::hasColumn('payroll_history', 'overtime')) {
                $table->double('overtime', 8, 2)->default('0')->nullable()->after('hourly_salary');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payroll_history', function (Blueprint $table) {
            //
        });
    }
};
