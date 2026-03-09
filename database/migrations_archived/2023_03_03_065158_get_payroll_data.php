<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('DROP VIEW IF EXISTS get_payroll_data;');
        DB::statement('CREATE VIEW get_payroll_data AS
        select `pd`.`id` AS `id`,`pd`.`user_id` AS `user_id`,`pd`.`position_id` AS `position_id`,`pd`.`commission` AS `commission`,`pd`.`override` AS `override`,`pd`.`reimbursement` AS `reimbursement`,`pd`.`clawback` AS `clawback`,`pd`.`deduction` AS `deduction`,`pd`.`adjustment` AS `adjustment`,`pd`.`reconciliation` AS `reconciliation`,`pd`.`net_pay` AS `net_pay`,`pd`.`pay_period_from` AS `pay_period_from`,`pd`.`pay_period_to` AS `pay_period_to`,`pd`.`status` AS `status` from (select `payrolls`.`id` AS `id`,`payrolls`.`user_id` AS `user_id`,`payrolls`.`position_id` AS `position_id`,`payrolls`.`commission` AS `commission`,`payrolls`.`override` AS `override`,`payrolls`.`reimbursement` AS `reimbursement`,`payrolls`.`clawback` AS `clawback`,`payrolls`.`deduction` AS `deduction`,`payrolls`.`adjustment` AS `adjustment`,`payrolls`.`reconciliation` AS `reconciliation`,`payrolls`.`net_pay` AS `net_pay`,`payrolls`.`pay_period_from` AS `pay_period_from`,`payrolls`.`pay_period_to` AS `pay_period_to`,`payrolls`.`status` AS `status` from `payrolls` union select `payroll_history`.`payroll_id` AS `id`,`payroll_history`.`user_id` AS `user_id`,`payroll_history`.`position_id` AS `position_id`,`payroll_history`.`commission` AS `commission`,`payroll_history`.`override` AS `override`,`payroll_history`.`reimbursement` AS `reimbursement`,`payroll_history`.`clawback` AS `clawback`,`payroll_history`.`deduction` AS `deduction`,`payroll_history`.`adjustment` AS `adjustment`,`payroll_history`.`reconciliation` AS `reconciliation`,`payroll_history`.`net_pay` AS `net_pay`,`payroll_history`.`pay_period_from` AS `pay_period_from`,`payroll_history`.`pay_period_to` AS `pay_period_to`,`payroll_history`.`status` AS `status` from `payroll_history`) `pd`');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
