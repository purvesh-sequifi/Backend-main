<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdatePayrollHistoryDoubleColumnsPrecision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $columns = [
            'commission',
            'override',
            'reimbursement',
            'clawback',
            'deduction',
            'adjustment',
            'reconciliation',
            'hourly_salary',
            'overtime',
            'net_pay',
            'custom_payment',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE payroll_history MODIFY `$column` DOUBLE(12,2)");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $columns = [
            'commission',
            'override',
            'reimbursement',
            'clawback',
            'deduction',
            'adjustment',
            'reconciliation',
            'hourly_salary',
            'overtime',
            'net_pay',
            'custom_payment',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE payroll_history MODIFY `$column` DOUBLE(8,2)");
        }
    }
}
