<?php

namespace App\Core\Traits;

use App\Models\Payroll;
use App\Models\User;

trait PayRollDeductionTrait
{
    public function updateDeduction($userID, $position_id, $deduction)
    {
        $user = User::select('id', 'stop_payroll')->where('id', $userID)->first();
        $payRoll = PayRoll::where(['user_id' => $userID, 'status' => 1])->first();
        if ($payRoll) {
            $payRoll->deduction = ($payRoll->deduction + ($deduction));
            $payRoll->save();
        } else {
            PayRoll::create([
                'user_id' => $userID,
                'position_id' => $position_id,
                'deduction' => $deduction,
                'is_stop_payroll' => $user->stop_payroll ?? 0,
                'status' => 1,
            ]);
        }
    }
}
