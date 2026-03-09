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
        Schema::table('payroll_deduction_locks', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_deduction_locks', 'is_stop_payroll')) {
                $table->tinyInteger('is_stop_payroll')->nullable()->default(0)->after('is_next_payroll');
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
        Schema::table('payroll_deduction_locks', function (Blueprint $table) {
            //
        });
    }
};
