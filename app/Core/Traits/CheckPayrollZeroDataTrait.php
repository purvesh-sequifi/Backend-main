<?php

namespace App\Core\Traits;

use App\Models\ApprovalsAndRequest;
use App\Models\ClawbackSettlement;
use App\Models\CustomField;
use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDeductions;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollOvertime;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationsAdjustement;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;

trait CheckPayrollZeroDataTrait
{
    public function updatePayrollZeroData($pay_period_from, $pay_period_to, $pay_frequency)
    {
        if ($pay_frequency == FrequencyType::DAILY_PAY_ID) {
            $payrolls = Payroll::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('status', 1)->get();
            if (count($payrolls) > 0) {
                foreach ($payrolls as $key => $data) {
                    $userID = $data->user_id;
                    $pay_period_from = $data->pay_period_from;
                    $pay_period_to = $data->pay_period_to;
                    $commission = UserCommission::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $userID)->where('status', '!=', 3)->count();
                    $overrides = UserOverrides::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $userID)->where('status', '!=', 3)->count();
                    $clawbackSettlement = ClawbackSettlement::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'clawback_type' => 'next payroll'])->where('status', '!=', 3)->count();

                    $approvalsAndRequest = ApprovalsAndRequest::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $userID)->where('status', 'Accept')->count();
                    $payrollAdjustment = PayrollAdjustment::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $userID)->where('status', '!=', 3)->count();

                    /* $closerReconciliation = UserReconciliationWithholding::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('closer_id',$userID)->where('status', '!=', 3)->count();
                    $setterReconciliation = UserReconciliationWithholding::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('setter_id',$userID)->where('status', '!=', 3)->count(); */
                    // $reconciliationsAdjustement = ReconciliationsAdjustement::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id',$userID)->count();
                    $customField = CustomField::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where('user_id', $userID)->count();

                    $hourlySalary = PayrollHourlySalary::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $userID])->where('status', '!=', 3)->count();
                    $overtime = PayrollOvertime::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $userID])->where('status', '!=', 3)->count();

                    $payrolldeduction = PayrollDeductions::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $userID])->where('status', '!=', 3)->count();

                    $reconFinalizeData = ReconciliationFinalizeHistory::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $userID])->where('payroll_id', $data->id)->count();
                    /* recon payroll zero data */
                    // $reconFinalizeData = ReconciliationFinalizeHistory::where(['user_id'=> $userID, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->where('payroll_id', $data->id)->count();
                    // $check = ($commission+$overrides+$clawbackSettlement+$approvalsAndRequest+$payrollAdjustment+$reconFinalizeData+$closerReconciliation+$setterReconciliation+$customField+$hourlySalary+$overtime);

                    $check = ($commission + $overrides + $clawbackSettlement + $approvalsAndRequest + $payrollAdjustment + $customField + $hourlySalary + $overtime + $payrolldeduction + $reconFinalizeData);

                    if ($check < 1) {
                        Payroll::whereBetween('pay_period_from', [$pay_period_from, $pay_period_to])->whereBetween('pay_period_to', [$pay_period_from, $pay_period_to])->whereColumn('pay_period_from', 'pay_period_to')->where(['user_id' => $userID, 'status' => 1])->delete();
                    }
                }
            }
        } else {

            $payrolls = Payroll::where(['pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'status' => 1])->get();
            if (count($payrolls) > 0) {
                foreach ($payrolls as $key => $data) {
                    $userID = $data->user_id;
                    $pay_period_from = $data->pay_period_from;
                    $pay_period_to = $data->pay_period_to;
                    $commission = UserCommission::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', '!=', 3)->count();
                    $overrides = UserOverrides::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', '!=', 3)->count();
                    $clawbackSettlement = ClawbackSettlement::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'clawback_type' => 'next payroll'])->where('status', '!=', 3)->count();

                    $approvalsAndRequest = ApprovalsAndRequest::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', 'Accept')->count();
                    $payrollAdjustment = PayrollAdjustment::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', '!=', 3)->count();

                    /* $closerReconciliation = UserReconciliationWithholding::where(['closer_id'=> $userID, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->where('status', '!=', 3)->count();
                    $setterReconciliation = UserReconciliationWithholding::where(['setter_id'=> $userID, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->where('status', '!=', 3)->count(); */
                    // $reconciliationsAdjustement = ReconciliationsAdjustement::where(['user_id'=> $userID, 'pay_period_from'=> $pay_period_from, 'pay_period_to'=> $pay_period_to])->count();
                    $customField = CustomField::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->count();

                    $hourlySalary = PayrollHourlySalary::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', '!=', 3)->count();
                    $overtime = PayrollOvertime::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', '!=', 3)->count();

                    /* recon payroll zero data */
                    $reconFinalizeData = ReconciliationFinalizeHistory::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('payroll_id', $data->id)->count();
                    $payrolldeduction = PayrollDeductions::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->where('status', '!=', 3)->count();

                    /* $check = ($commission+$overrides+$clawbackSettlement+$approvalsAndRequest+$payrollAdjustment+$closerReconciliation+$setterReconciliation+$customField); */
                    $check = ($commission + $overrides + $clawbackSettlement + $approvalsAndRequest + $payrollAdjustment + $reconFinalizeData + $customField + $hourlySalary + $overtime + $payrolldeduction);

                    if ($check < 1) {
                        Payroll::where(['user_id' => $userID, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'status' => 1])->delete();
                    }
                }
            }
        }

    }
}
