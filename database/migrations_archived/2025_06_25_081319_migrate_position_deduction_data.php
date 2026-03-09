<?php

use App\Models\AdditionalPayFrequency;
use App\Models\DailyPayFrequency;
use App\Models\FrequencyType;
use App\Models\MonthlyPayFrequency;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionPayFrequency;
use App\Models\User;
use App\Models\UserDeductionHistory;
use App\Models\WeeklyPayFrequency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $deductions = PositionCommissionDeduction::whereNull('pay_period_from')->get();
        foreach ($deductions as $deduction) {
            $positionFrequency = PositionPayFrequency::where('position_id', $deduction->position_id)->first();
            if ($positionFrequency) {
                if ($positionFrequency->frequency_type_id == FrequencyType::WEEKLY_ID) {
                    $weekly = WeeklyPayFrequency::orderBy('id', 'ASC')->first();
                    if ($weekly) {
                        $deduction->pay_period_from = $weekly->pay_period_from;
                        $deduction->pay_period_to = $weekly->pay_period_to;
                        $deduction->save();
                    }

                    $users = User::where('sub_position_id', $deduction->position_id)->get();
                    foreach ($users as $user) {
                        UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->update(['pay_period_from' => $weekly->pay_period_from, 'pay_period_to' => $weekly->pay_period_to]);
                    }
                } elseif ($positionFrequency->frequency_type_id == FrequencyType::MONTHLY_ID) {
                    $monthly = MonthlyPayFrequency::orderBy('id', 'ASC')->first();
                    if ($monthly) {
                        $deduction->pay_period_from = $monthly->pay_period_from;
                        $deduction->pay_period_to = $monthly->pay_period_to;
                        $deduction->save();
                    }
                    $users = User::where('sub_position_id', $deduction->position_id)->get();
                    foreach ($users as $user) {
                        UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->update(['pay_period_from' => $monthly->pay_period_from, 'pay_period_to' => $monthly->pay_period_to]);
                    }
                } elseif ($positionFrequency->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                    $biWeekly = AdditionalPayFrequency::where('type', '1')->orderBy('id', 'ASC')->first();
                    if ($biWeekly) {
                        $deduction->pay_period_from = $biWeekly->pay_period_from;
                        $deduction->pay_period_to = $biWeekly->pay_period_to;
                        $deduction->save();
                    }
                    $users = User::where('sub_position_id', $deduction->position_id)->get();
                    foreach ($users as $user) {
                        UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->update(['pay_period_from' => $biWeekly->pay_period_from, 'pay_period_to' => $biWeekly->pay_period_to]);
                    }
                } elseif ($positionFrequency->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                    $semiMonthly = AdditionalPayFrequency::where('type', '2')->orderBy('id', 'ASC')->first();
                    if ($semiMonthly) {
                        $deduction->pay_period_from = $semiMonthly->pay_period_from;
                        $deduction->pay_period_to = $semiMonthly->pay_period_to;
                        $deduction->save();
                    }
                    $users = User::where('sub_position_id', $deduction->position_id)->get();
                    foreach ($users as $user) {
                        UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->update(['pay_period_from' => $semiMonthly->pay_period_from, 'pay_period_to' => $semiMonthly->pay_period_to]);
                    }
                } elseif ($positionFrequency->frequency_type_id == FrequencyType::DAILY_PAY_ID) {
                    $payPeriodFrom = '2020-01-01';
                    $daily = DailyPayFrequency::orderBy('id', 'ASC')->first();
                    if ($daily && $daily->pay_period_from) {
                        $deduction->pay_period_from = $daily->pay_period_from;
                        $deduction->pay_period_to = null;
                        $deduction->save();
                        $payPeriodFrom = $daily->pay_period_from;
                    } else {
                        $deduction->pay_period_from = $payPeriodFrom;
                        $deduction->pay_period_to = null;
                        $deduction->save();
                    }

                    $users = User::where('sub_position_id', $deduction->position_id)->get();
                    foreach ($users as $user) {
                        UserDeductionHistory::where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->update(['pay_period_from' => $payPeriodFrom, 'pay_period_to' => null]);
                    }
                }
            }
        }
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
