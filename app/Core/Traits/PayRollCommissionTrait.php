<?php

namespace App\Core\Traits;

use App\Models\Payroll;
use App\Models\User;

trait PayRollCommissionTrait
{
    public function updateCommission($userID, $position_id, $commission, $date): void
    {
        $user = User::select('id', 'sub_position_id', 'stop_payroll')->where('id', $userID)->first();
        $payFrequency = $this->payFrequencyNew($date, $user->sub_position_id, $user->id);
        $payRoll = PayRoll::where(['user_id' => $user->id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'status' => 1])->first();
        if ($payRoll) {
            $payRoll->commission = ($payRoll->commission + $commission);
            $payRoll->position_id = $position_id;
            $payRoll->pay_period_from = $payFrequency->pay_period_from;
            $payRoll->pay_period_to = $payFrequency->pay_period_to;
            $payRoll->save();
        } else {
            $user = User::where(['id' => $userID])->first();
            if ($user) {
                PayRoll::create([
                    'user_id' => $userID,
                    'position_id' => $position_id,
                    'commission' => $commission,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'status' => 1,
                    'is_stop_payroll' => $user->stop_payroll ?? 0,
                ]);
            }
        }
    }

    public function updateCommissionNew($userID, $subPositionId, $commission, $payFrequency): void
    {
        $user = User::select('id', 'stop_payroll')->where('id', $userID)->first();
        if (! PayRoll::where(['user_id' => $user->id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'status' => 1])->first()) {
            PayRoll::create([
                'user_id' => $userID,
                'position_id' => $subPositionId,
                'commission' => $commission,
                'pay_period_from' => $payFrequency->pay_period_from,
                'pay_period_to' => $payFrequency->pay_period_to,
                'status' => 1,
                'is_stop_payroll' => $user->stop_payroll ?? 0,
            ]);
        }
    }

    public function updateOverrideNew($userID, $subPositionId, $override, $payFrequency): void
    {
        $user = User::select('id', 'stop_payroll')->where('id', $userID)->first();
        if (! PayRoll::where(['user_id' => $user->id, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to, 'status' => 1])->first()) {
            PayRoll::create([
                'user_id' => $userID,
                'position_id' => $subPositionId,
                'override' => $override,
                'pay_period_from' => $payFrequency->pay_period_from,
                'pay_period_to' => $payFrequency->pay_period_to,
                'status' => 1,
                'is_stop_payroll' => $user->stop_payroll ?? 0,
            ]);
        }
    }
}
