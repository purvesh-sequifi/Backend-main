<?php

namespace App\Core\Traits;

use App\Models\Payroll;
use App\Models\User;

trait PayRollClawbackTrait
{
    public function updateClawback($userID, $position_id, $clawback, $payFrequency, $pid)
    {
        $user = User::select('id', 'stop_payroll')->where('id', $userID)->first();
        $payRoll = PayRoll::where(['user_id' => $userID, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first();
        if ($payRoll) {
            $payRoll->clawback = ($payRoll->clawback + ($clawback));
            $payRoll->save();
        } else {
            PayRoll::create([
                'user_id' => $userID,
                'position_id' => $position_id,
                'clawback' => $clawback ?? 0,
                'commission' => (0 - $clawback),
                'pay_period_from' => $payFrequency->pay_period_from,
                'pay_period_to' => $payFrequency->pay_period_to,
                'is_stop_payroll' => $user->stop_payroll ?? 0,
                'status' => 1,
            ]);
        }
    }
}
