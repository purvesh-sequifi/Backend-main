<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdatePayrollsDoubleColumnsPrecision extends Migration
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
            'gross_pay',
            'custom_payment',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE payrolls MODIFY `$column` DOUBLE(12,2)");
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
            'gross_pay',
            'custom_payment',
        ];

        foreach ($columns as $column) {
            DB::statement("ALTER TABLE payrolls MODIFY `$column` DOUBLE(8,2)");
        }
    }
}
