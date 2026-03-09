<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Adding the columns to each table
        $tables = [
            'user_commission',
            'payroll_adjustment_details',
            'payroll_adjustments',
            'clawback_settlements',
            'user_overrides',
            'approvals_and_requests',
            'payroll_deductions',
            'user_overrides_lock',
            'user_commission_lock',
            'payroll_deduction_locks',
            'payroll_adjustments_lock',
            'payroll_adjustment_details_lock',
            'clawback_settlements_lock',
            'approvals_and_requests_lock',
            'payroll_history',
            'payrolls',
        ];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('is_onetime_payment')->default(0);
                $table->unsignedBigInteger('one_time_payment_id')->nullable();
            });
        }
    }

    public function down()
    {
        // Dropping the columns in case of rollback
        $tables = [
            'user_commission',
            'payroll_adjustment_details',
            'payroll_adjustments',
            'clawback_settlements',
            'user_overrides',
            'approvals_and_requests',
            'payroll_deductions',
            'user_overrides_lock',
            'user_commission_lock',
            'payroll_deduction_locks',
            'payroll_adjustments_lock',
            'payroll_adjustment_details_lock',
            'clawback_settlements_lock',
            'approvals_and_requests_lock',
            'payroll_history',
            'payrolls',
        ];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Dropping both columns during rollback
                $table->dropColumn('is_onetime_payment');
                $table->dropColumn('one_time_payment_id');
            });
        }
    }
};
