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
            if (! Schema::hasColumn('payroll_deductions', 'status')) {
                $table->tinyInteger('status')->nullable()->default(1)->after('pay_period_to');
            }
            if (! Schema::hasColumn('payroll_deductions', 'is_mark_paid')) {
                $table->tinyInteger('is_mark_paid')->nullable()->default(0)->after('status');
            }

            if (! Schema::hasColumn('payroll_deductions', 'is_next_payroll')) {
                $table->tinyInteger('is_next_payroll')->nullable()->default(0)->after('is_mark_paid');
            }

            if (! Schema::hasColumn('payroll_deductions', 'ref_id')) {
                $table->integer('ref_id')->nullable()->default(0)->after('is_next_payroll');
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
        Schema::table('payroll_deductions', function (Blueprint $table) {
            //
        });
    }
};
