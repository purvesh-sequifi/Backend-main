<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdatePayrollHistoryFromPayrolls extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('
            UPDATE payroll_history
            INNER JOIN payrolls ON payrolls.id = payroll_history.payroll_id
            SET 
                payroll_history.commission     = CASE WHEN payrolls.commission IS NOT NULL AND payroll_history.commission IS NOT NULL AND payrolls.commission != payroll_history.commission THEN payrolls.commission ELSE payroll_history.commission END,
                payroll_history.override       = CASE WHEN payrolls.override IS NOT NULL AND payroll_history.override IS NOT NULL AND payrolls.override != payroll_history.override THEN payrolls.override ELSE payroll_history.override END,
                payroll_history.reimbursement  = CASE WHEN payrolls.reimbursement IS NOT NULL AND payroll_history.reimbursement IS NOT NULL AND payrolls.reimbursement != payroll_history.reimbursement THEN payrolls.reimbursement ELSE payroll_history.reimbursement END,
                payroll_history.clawback        = CASE WHEN payrolls.clawback IS NOT NULL AND payroll_history.clawback IS NOT NULL AND payrolls.clawback != payroll_history.clawback THEN payrolls.clawback ELSE payroll_history.clawback END,
                payroll_history.deduction       = CASE WHEN payrolls.deduction IS NOT NULL AND payroll_history.deduction IS NOT NULL AND payrolls.deduction != payroll_history.deduction THEN payrolls.deduction ELSE payroll_history.deduction END,
                payroll_history.adjustment      = CASE WHEN payrolls.adjustment IS NOT NULL AND payroll_history.adjustment IS NOT NULL AND payrolls.adjustment != payroll_history.adjustment THEN payrolls.adjustment ELSE payroll_history.adjustment END,
                payroll_history.reconciliation  = CASE WHEN payrolls.reconciliation IS NOT NULL AND payroll_history.reconciliation IS NOT NULL AND payrolls.reconciliation != payroll_history.reconciliation THEN payrolls.reconciliation ELSE payroll_history.reconciliation END,
                payroll_history.hourly_salary   = CASE WHEN payrolls.hourly_salary IS NOT NULL AND payroll_history.hourly_salary IS NOT NULL AND payrolls.hourly_salary != payroll_history.hourly_salary THEN payrolls.hourly_salary ELSE payroll_history.hourly_salary END,
                payroll_history.overtime        = CASE WHEN payrolls.overtime IS NOT NULL AND payroll_history.overtime IS NOT NULL AND payrolls.overtime != payroll_history.overtime THEN payrolls.overtime ELSE payroll_history.overtime END,
                payroll_history.net_pay         = CASE WHEN payrolls.net_pay IS NOT NULL AND payroll_history.net_pay IS NOT NULL AND payrolls.net_pay != payroll_history.net_pay THEN payrolls.net_pay ELSE payroll_history.net_pay END,
                payroll_history.custom_payment  = CASE WHEN payrolls.custom_payment IS NOT NULL AND payroll_history.custom_payment IS NOT NULL AND payrolls.custom_payment != payroll_history.custom_payment THEN payrolls.custom_payment ELSE payroll_history.custom_payment END
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No rollback needed, because we can't recover previous values safely.
    }
}
