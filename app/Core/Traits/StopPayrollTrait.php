<?php

namespace App\Core\Traits;

use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\Payroll;
use App\Models\PayrollDeductions;
use App\Models\PositionPayFrequency;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\WeeklyPayFrequency;

trait StopPayrollTrait
{
    use PayFrequencyTrait;
    use ReconciliationPeriodTrait;

    public function updatePayrollData($data)
    {
        $userId = $data->id;
        $payrolls = Payroll::where(['user_id' => $userId, 'status' => 1, 'is_stop_payroll' => 1])->get();
        if (count($payrolls) > 0) {
            // $frequency = $this->payFrequencyType($data->sub_position_id);
            // $payFrom = $frequency->next_pay_period_from;
            // $payTo   = $frequency->next_pay_period_to;
            foreach ($payrolls as $key => $payroll) {

                // if ($payroll->pay_period_from < $frequency->next_pay_period_from) {
                //     updateExistingPayroll($userId, $payFrom, $payTo, $payroll->commission, 'commission', $payroll->sub_position_id, 0);
                // }else {
                //     Payroll::where(['id'=> $payroll->id])->update(['is_stop_payroll'=> 0]);
                // }

                UserCommission::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 0]);
                UserOverrides::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 0]);
                // ReconciliationFinalizeHistory::where(['payroll_id'=> $payroll->id])->update(['is_stop_payroll'=> 0]);
                ClawbackSettlement::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 0]);
                PayrollDeductions::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 0]);

                Payroll::where(['id' => $payroll->id])->update(['is_stop_payroll' => 0]);

            }

        }

    }

    public function updatePayrollStop($data)
    {
        if ($data->id) {
            $userId = $data->id;

            $payrolls = Payroll::where(['user_id' => $userId, 'status' => 1, 'is_stop_payroll' => 0])->get();
            if (count($payrolls) > 0) {

                foreach ($payrolls as $key => $payroll) {

                    UserCommission::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 1]);
                    UserOverrides::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 1]);
                    // ReconciliationFinalizeHistory::where(['payroll_id'=> $payroll->id])->update(['is_stop_payroll'=> 0]);
                    ClawbackSettlement::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 1]);
                    PayrollDeductions::where(['user_id' => $userId, 'pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'status' => 1])->update(['is_stop_payroll' => 1]);

                }

            }

            $payroll = Payroll::where(['user_id' => $userId, 'status' => 1])->update(['is_stop_payroll' => 1]);
        }

    }

    public function payFrequencyType($position_id)
    {
        $positionPayFrequency = PositionPayFrequency::where(['position_id' => $position_id])->first();
        if ($positionPayFrequency) {
            if ($positionPayFrequency->frequency_type_id == 2) {
                $payFrequency = WeeklyPayFrequency::where('pay_period_from', '>', '2023-12-05')->where('closed_status', 1)->orderBy('pay_period_from', 'desc')->first();
                $payFrequency1 = WeeklyPayFrequency::where('pay_period_from', '>', '2023-12-05')->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->pay_period_to = $payFrequency->pay_period_to;
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            }
            if ($positionPayFrequency->frequency_type_id == 5) {
                $payFrequency = MonthlyPayFrequency::where('id', '>', '2023-12-05')->where('closed_status', 1)->orderBy('pay_period_from', 'desc')->first();
                $payFrequency1 = MonthlyPayFrequency::where('id', '>', '2023-12-05')->where('closed_status', 0)->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->pay_period_from = $payFrequency->pay_period_from;
                $payFrequency->pay_period_to = $payFrequency->pay_period_to;
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            }
            if ($positionPayFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                $payFrequency = AdditionalPayFrequency::where(['closed_status' => 1, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])->orderBy('pay_period_from', 'desc')->first();
                $payFrequency1 = AdditionalPayFrequency::where(['closed_status' => 0, 'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE])->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            }
            if ($positionPayFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                $payFrequency = AdditionalPayFrequency::where(['closed_status' => 1, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])->orderBy('pay_period_from', 'desc')->first();
                $payFrequency1 = AdditionalPayFrequency::where(['closed_status' => 0, 'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE])->orderBy('pay_period_from', 'asc')->first();
                $payFrequency->next_pay_period_from = $payFrequency1->pay_period_from;
                $payFrequency->next_pay_period_to = $payFrequency1->pay_period_to;
            }
        }

        return $payFrequency;
    }
}
