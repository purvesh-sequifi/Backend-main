<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the view if it exists
        DB::statement('DROP VIEW `get_payroll_data`;');

        // Create the view
        DB::statement('
            CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `get_payroll_data` AS 
            SELECT `pd`.`id`, `pd`.`user_id`, `pd`.`position_id`, `pd`.`commission`, `pd`.`override`, 
                `pd`.`reimbursement`, `pd`.`clawback`, `pd`.`deduction`, `pd`.`adjustment`, 
                `pd`.`reconciliation`, `pd`.`net_pay`, `pd`.`pay_period_from`, `pd`.`pay_period_to`, 
                `pd`.`status`
            FROM (
                SELECT `payrolls`.`id`, `payrolls`.`user_id`, `payrolls`.`position_id`, `payrolls`.`commission`, 
                    `payrolls`.`override`, `payrolls`.`reimbursement`, `payrolls`.`clawback`, `payrolls`.`deduction`, 
                    `payrolls`.`adjustment`, `payrolls`.`reconciliation`, `payrolls`.`net_pay`, 
                    `payrolls`.`pay_period_from`, `payrolls`.`pay_period_to`, `payrolls`.`status` 
                FROM `payrolls`
                
                UNION
                
                SELECT `payroll_history`.`payroll_id` AS `id`, `payroll_history`.`user_id`, `payroll_history`.`position_id`, 
                    `payroll_history`.`commission`, `payroll_history`.`override`, `payroll_history`.`reimbursement`, 
                    `payroll_history`.`clawback`, `payroll_history`.`deduction`, `payroll_history`.`adjustment`, 
                    `payroll_history`.`reconciliation`, `payroll_history`.`net_pay`, 
                    `payroll_history`.`pay_period_from`, `payroll_history`.`pay_period_to`, `payroll_history`.`status`
                FROM `payroll_history`
            ) AS `pd`;
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('DROP VIEW `get_payroll_data`;');
    }
};
