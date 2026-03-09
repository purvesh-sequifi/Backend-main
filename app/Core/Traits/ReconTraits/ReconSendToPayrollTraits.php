<?php

namespace App\Core\Traits\ReconTraits;

use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\ReconAdjustment;
use App\Models\ReconOverrideHistory;
use App\Models\User;
use App\Models\UserOverrides;

trait ReconSendToPayrollTraits
{
    public function sendToPayrollOverride($overrideData, $bodyData)
    {
        foreach ($overrideData as $value) {
            $usersData = User::with('positionDetail')->where('id', $value->user_id)->first();
            $this->manageReconOverridePayFrequency($value, $usersData, $bodyData);

            /* update recon payment status code */
            if ($value->percentage == 100) {
                /* Note: if status is 6 and is_move_to_recon is 0 then this row amount is paid from recon */
                $userOverrideData = UserOverrides::where('pid', $value->pid)
                    ->where('type', $value->type)
                    ->where('user_id', $value->user_id);

                $userOverrideData->each(function ($item) {
                    $item->status = 6;
                    $item->is_move_to_recon = 0;
                    $item->save(); // Save the update
                });
            }
        }
    }

    public function manageReconOverridePayFrequency($overrideData, $userData, $bodyData)
    {
        $frequencyTypeId = $userData?->positionDetail?->payFrequency?->frequency_type_id;
        $startDate = $overrideData->start_date;
        $endDate = $overrideData->end_date;

        $overrideSent = ReconOverrideHistory::where('status', 'payroll')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->orderBy('id', 'desc')
            ->first();

        /* Calculate the new sent count */
        $overridesCount = isset($overrideSent->sent_count) ? $overrideSent->sent_count + 1 : 1;
        switch ($frequencyTypeId) {
            case 1:
                $payPeriodFrom = $bodyData['daily']['pay_period_from'];
                $payPeriodTo = $bodyData['daily']['pay_period_to'];
                $this->handleOverrideDailyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 2:
                $payPeriodFrom = $bodyData['weekly']['pay_period_from'];
                $payPeriodTo = $bodyData['weekly']['pay_period_to'];
                $this->handleOverrideWeeklyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 5:
                $payPeriodFrom = $bodyData['monthly']['pay_period_from'];
                $payPeriodTo = $bodyData['monthly']['pay_period_to'];
                $this->handleOverrideMonthlyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::BI_WEEKLY_ID:
                $payPeriodFrom = $bodyData['biweekly']['pay_period_from'];
                $payPeriodTo = $bodyData['biweekly']['pay_period_to'];
                $this->handleOverrideBiAndSemiWeeklyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::SEMI_MONTHLY_ID:
                $payPeriodFrom = $bodyData['semimonthly']['pay_period_from'];
                $payPeriodTo = $bodyData['semimonthly']['pay_period_to'];
                $this->handleOverrideBiAndSemiWeeklyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo);
                break;
            default:
                $payPeriodFrom = null;
                $payPeriodTo = null;
        }

        $payData = Payroll::where('user_id', $overrideData->user_id)
            ->where([
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
        if ($payData) {
            $userReconComm = ReconOverrideHistory::where([
                'status' => 'payroll',
                'user_id' => $overrideData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'is_ineligible' => '0',
            ])->first();
            /* update payroll id in recon history table after send to payroll recon data */
            if ($userReconComm) {
                ReconOverrideHistory::where([
                    'status' => 'payroll',
                    'user_id' => $overrideData->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_ineligible' => '0',
                ])->update(['payroll_id' => $payData->id]);
            }
            /* update adjustment Data */
            ReconAdjustment::where('user_id', $overrideData->user_id)
                ->where('pid', $overrideData->pid)
                ->where('payroll_status', 'finalize')
                ->where('adjustment_type', 'override')
                ->where('adjustment_override_type', $overrideData->type)
                ->whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->whereNull('payroll_id')
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to')
                ->update([
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'payroll_id' => $payData->id,
                    'payroll_status' => 'payroll',
                    'sent_count' => $overridesCount,
                ]);
        } else {
            $payroll_data = Payroll::create([
                'user_id' => $userData->id,
                'position_id' => $userData->sub_position_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'status' => 1,
            ]);
            $payRollId = $payroll_data->id;

            $userReconComm = ReconOverrideHistory::where([
                'status' => 'payroll',
                'user_id' => $overrideData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'is_ineligible' => '0',
            ])->first();
            if (isset($userReconComm) && $userReconComm != '') {
                ReconOverrideHistory::where([
                    'status' => 'payroll',
                    'user_id' => $overrideData->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_ineligible' => '0',
                ])->update(['payroll_id' => $payRollId, 'sent_count' => $overridesCount]);
            }

            /* update recon adjustment */
            ReconAdjustment::where('user_id', $overrideData->user_id)
                ->where('pid', $overrideData->pid)
                ->where('payroll_status', 'finalize')
                ->where('adjustment_type', 'override')
                ->where('adjustment_override_type', $overrideData->type)
                ->whereDate('start_date', $startDate)
                ->whereDate('end_date', $endDate)
                ->whereNull('payroll_id')
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to')
                ->update([
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'payroll_id' => $payRollId,
                    'payroll_status' => 'payroll',
                    'sent_count' => $overridesCount,
                ]);
        }
    }

    private function handleOverrideWeeklyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo)
    {
        /* override history data update */
        ReconOverrideHistory::where('status', 'finalize')
            ->where('user_id', $overrideData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $overridesCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleOverrideDailyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo)
    {
        /* override history data update */
        ReconOverrideHistory::where('status', 'finalize')
            ->where('user_id', $overrideData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $overridesCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleOverrideMonthlyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo)
    {
        /* override history data update */
        ReconOverrideHistory::where('status', 'finalize')
            ->where('user_id', $overrideData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $overridesCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleOverrideBiAndSemiWeeklyFrequency($overrideData, $startDate, $endDate, $overridesCount, $payPeriodFrom, $payPeriodTo)
    {
        /* override history data update */
        ReconOverrideHistory::where('status', 'finalize')
            ->where('user_id', $overrideData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $overridesCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }
}
