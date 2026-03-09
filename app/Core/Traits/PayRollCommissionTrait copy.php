<?php

namespace App\Core\Traits;

use App\Models\Payroll;
use App\Models\PayrollAlerts;
use App\Models\PositionPayFrequency;
use App\Models\User;
use Carbon\Carbon;
use DB;

trait PayRollCommissionTrait
{
    public function updateCommission($userID, $position_id, $commission, $date)
    {
        // echo $commission;die;
        $user = User::where(['id' => $userID])->first();
        $positionPayFrequency = PositionPayFrequency::where(['position_id' => $user->sub_position_id])->first();

        if ($positionPayFrequency) {
            // Weekly
            if ($positionPayFrequency->frequency_type_id == 2) {
                $current_data = Carbon::now();
                $weeklyPayFrequency = DB::table('weekly_pay_frequencies')->whereRaw('"'.$date.'" between `pay_period_from` and `pay_period_to`')->first();

                if ($weeklyPayFrequency) {

                    $payRoll = PayRoll::where(['user_id' => $userID, 'pay_period_from' => $weeklyPayFrequency->pay_period_from, 'pay_period_to' => $weeklyPayFrequency->pay_period_to, 'status' => 1, 'is_mark_paid' => 0])->first();
                    // $payRoll = PayRoll::where(['user_id'=> $userID, 'pay_period_from' =>$weeklyPayFrequency->pay_period_from,'pay_period_to' =>$weeklyPayFrequency->pay_period_to,'status'=> 1])->first();
                    if ($payRoll) {
                        $payRoll->commission = ($payRoll->commission + $commission);
                        $payRoll->position_id = $position_id;
                        $payRoll->pay_period_from = $weeklyPayFrequency->pay_period_from;
                        $payRoll->pay_period_to = $weeklyPayFrequency->pay_period_to;
                        $payRoll->save();
                    } else {
                        $payRoll = PayRoll::where(['user_id' => $userID, 'pay_period_from' => $weeklyPayFrequency->pay_period_from, 'pay_period_to' => $weeklyPayFrequency->pay_period_to])->first();
                        if ($payRoll && 1 == 2) {
                            if ($payRoll->status == 2) {

                                PayrollAlerts::create(
                                    [
                                        'user_id' => $userID,
                                        'position_id' => $user->sub_position_id,
                                        'commission' => $commission,
                                        'pay_period_from' => $weeklyPayFrequency->pay_period_from,
                                        'pay_period_to' => $weeklyPayFrequency->pay_period_to,
                                        'status' => 1,
                                    ]
                                );

                            } else {

                                PayRoll::create(
                                    [
                                        'user_id' => $userID,
                                        'position_id' => $user->sub_position_id,
                                        'commission' => $commission,
                                        'pay_period_from' => $weeklyPayFrequency->pay_period_from,
                                        'pay_period_to' => $weeklyPayFrequency->pay_period_to,
                                        'status' => 1,
                                    ]
                                );

                            }
                        } else {
                            PayRoll::create(
                                [
                                    'user_id' => $userID,
                                    'position_id' => $user->sub_position_id,
                                    'commission' => $commission,
                                    'pay_period_from' => $weeklyPayFrequency->pay_period_from,
                                    'pay_period_to' => $weeklyPayFrequency->pay_period_to,
                                    'status' => 1,
                                ]
                            );
                        }

                    }

                }

            }

            // Monnthly
            if ($positionPayFrequency->frequency_type_id == 5) {
                $current_data = Carbon::now();
                $monthly_pay_frequencies = DB::table('monthly_pay_frequencies')->whereRaw('"'.$date.'" between `pay_period_from` and `pay_period_to`')->first();

                if ($monthly_pay_frequencies) {

                    $payRoll = PayRoll::where(['user_id' => $userID, 'pay_period_from' => $monthly_pay_frequencies->pay_period_from, 'pay_period_to' => $monthly_pay_frequencies->pay_period_to, 'status' => 1])->first();
                    if ($payRoll) {
                        $payRoll->commission = ($payRoll->commission + $commission);
                        $payRoll->position_id = $position_id;
                        $payRoll->pay_period_from = $monthly_pay_frequencies->pay_period_from;
                        $payRoll->pay_period_to = $monthly_pay_frequencies->pay_period_to;
                        $payRoll->save();
                    } else {
                        $payRoll = PayRoll::where(['user_id' => $userID, 'pay_period_from' => $monthly_pay_frequencies->pay_period_from, 'pay_period_to' => $monthly_pay_frequencies->pay_period_to])->first();
                        if ($payRoll) {
                            if ($payRoll->status == 2) {

                                PayrollAlerts::create(
                                    [
                                        'user_id' => $userID,
                                        'position_id' => $position_id,
                                        'commission' => $commission,
                                        'pay_period_from' => $monthly_pay_frequencies->pay_period_from,
                                        'pay_period_to' => $monthly_pay_frequencies->pay_period_to,
                                        'status' => 1,
                                    ]
                                );

                            } else {

                                PayRoll::create(
                                    [
                                        'user_id' => $userID,
                                        'position_id' => $user->sub_position_id,
                                        'commission' => $commission,
                                        'pay_period_from' => $monthly_pay_frequencies->pay_period_from,
                                        'pay_period_to' => $monthly_pay_frequencies->pay_period_to,
                                        'status' => 1,
                                    ]
                                );

                            }
                        } else {
                            PayRoll::create(
                                [
                                    'user_id' => $userID,
                                    'position_id' => $user->sub_position_id,
                                    'commission' => $commission,
                                    'pay_period_from' => $monthly_pay_frequencies->pay_period_from,
                                    'pay_period_to' => $monthly_pay_frequencies->pay_period_to,
                                    'status' => 1,
                                ]
                            );
                        }

                    }

                }

            }
        }

    }
}
