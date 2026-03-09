<?php

namespace App\Core\Traits;

use App\Models\Payroll;
use App\Models\UserCommission;
use App\Models\UserOverrides;

trait PayrollUpdatePayFrequencyTrait
{
    use EditSaleTrait;
    use PayFrequencyTrait;
    use ReconciliationPeriodTrait;

    public function updateCommissionFrequency($data)
    {
        // $userCommission = UserCommission::where('pay_period_from','>','2023-12-05')->where('status', 1)->groupBy('pid')->get();
        $userCommission = UserCommission::where('pay_period_from', '2023-12-26')->where('status', 1)->groupBy('pid')->get();
        // return $userCommission;
        if (count($userCommission) > 0) {

            foreach ($userCommission as $key => $value) {
                $m1Commiss = UserCommission::where('pid', $value->pid)->where(['amount_type' => 'm1', 'status' => 1])->get();
                if (count($m1Commiss) > 0) {
                    foreach ($m1Commiss as $key1 => $m1Commis) {
                        $payFrequency = $this->payFrequency($m1Commis->date, $m1Commis->position_id, $m1Commis->user_id);
                        $updatedata = ['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to];

                        $m1PayFrom = $m1Commis->pay_period_from;
                        $m1PayTo = $m1Commis->pay_period_to;

                        UserCommission::where(['id' => $m1Commis->id, 'amount_type' => 'm1', 'status' => 1])->update($updatedata);
                        PayRoll::where(['user_id' => $m1Commis->user_id, 'pay_period_from' => $m1PayFrom, 'pay_period_to' => $m1PayTo, 'status' => 1])->update($updatedata);

                    }
                }

                $m2Commiss = UserCommission::where('pid', $value->pid)->where(['amount_type' => 'm2', 'status' => 1])->get();
                if (count($m2Commiss) > 0) {
                    foreach ($m2Commiss as $key2 => $m2Commis) {

                        $payFrequency = $this->payFrequency($m2Commis->date, $m2Commis->position_id, $m2Commis->user_id);
                        $updatedata = ['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to];

                        $m2PayFrom = $m2Commis->pay_period_from;
                        $m2PayTo = $m2Commis->pay_period_to;
                        UserCommission::where(['pid' => $value->pid, 'user_id' => $m2Commis->user_id, 'amount_type' => 'm2', 'status' => 1, 'pay_period_from' => $m2PayFrom, 'pay_period_to' => $m2PayTo])->update($updatedata);
                        PayRoll::where(['user_id' => $m2Commis->user_id, 'pay_period_from' => $m2PayFrom, 'pay_period_to' => $m2PayTo, 'status' => 1])->update($updatedata);

                        $overrrides = UserOverrides::where(['sale_user_id' => $m2Commis->user_id, 'pid' => $value->pid, 'overrides_settlement_type' => 'during_m2', 'status' => 1])->get();
                        if (count($overrrides) > 0) {
                            foreach ($overrrides as $key3 => $over) {
                                $overridePayFrom = $over->pay_period_from;
                                $overridePayTo = $over->pay_period_to;
                                // $updatedata = ['pay_period_from'=> $payFrequency->pay_period_from, 'pay_period_to'=> $payFrequency->pay_period_to];
                                $update = UserOverrides::where(['id' => $over->id, 'status' => 1])->update($updatedata);

                                $payRoll = Payroll::where(['user_id' => $over->user_id, 'pay_period_from' => $overridePayFrom, 'pay_period_to' => $overridePayTo])->whereIn('status', [1, 2])->first();
                                if ($payRoll) {

                                    $updatePay = PayRoll::where(['user_id' => $over->user_id, 'pay_period_from' => $overridePayFrom, 'pay_period_to' => $overridePayTo, 'status' => 1])->update($updatedata);

                                }
                            }
                        }
                    }
                }

                $alertCenter = $this->closedPayrollData($value->pid);

            }
        }

        return response()->json(['status' => true, 'Message' => 'Update Frequency Wise Sale Data Successfully'], 200);

    }
}
