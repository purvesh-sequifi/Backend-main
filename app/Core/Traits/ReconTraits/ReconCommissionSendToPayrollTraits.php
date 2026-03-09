<?php

namespace App\Core\Traits\ReconTraits;

use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\ReconAdjustment;
use App\Models\ReconCommissionHistory;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserReconciliationWithholding;

trait ReconCommissionSendToPayrollTraits
{
    public function sendToPayrollCommission($commissionFinalizeData, $bodyData)
    {
        foreach ($commissionFinalizeData as $value) {
            $usersData = User::with('positionDetail')->where('id', $value->user_id)->first();
            $this->manageReconCommissionPayFrequency($value, $usersData, $bodyData);

            /* update recon payment status code */
            if ($value->payout == 100 || $value->payout == '100') {
                /* Note: if status is 6 and is_move_to_recon is 0 then this row amount is paid from recon */
                $userReconCommissionData = UserCommission::where('pid', $value->pid)
                    ->where('is_move_to_recon', 1)
                    ->where('status', 6)
                    ->where('user_id', $value->user_id);

                $userReconCommissionData->each(function ($item) {
                    $item->status = 6;
                    $item->is_move_to_recon = 0;
                    $item->save(); // Save the update
                });

                /* user recon withhold paid amount status update */
                UserReconciliationWithholding::where('pid', $value->pid)->where(function ($query) use ($value) {
                    $query->where('closer_id', $value->user_id)
                        ->orWhere('setter_id', $value->setter_id);
                })->update([
                    'status' => 'paid',
                    'finalize_status' => 1,
                    'payroll_to_recon_status' => 1,
                ]);
            }
        }
    }

    public function manageReconCommissionPayFrequency($reconCommissionData, $userData, $bodyData)
    {
        $frequencyTypeId = $userData?->positionDetail?->payFrequency?->frequency_type_id;
        $startDate = $reconCommissionData->start_date;
        $endDate = $reconCommissionData->end_date;

        $commissionSent = ReconCommissionHistory::where('status', 'payroll')
            ->where('start_date', $startDate)
            ->where('is_ineligible', '0')
            ->where('end_date', $endDate)
            ->orderBy('id', 'desc')
            ->first();

        /* Calculate the new sent count */
        $commissionCount = isset($commissionSent->sent_count) ? $commissionSent->sent_count + 1 : 1;
        switch ($frequencyTypeId) {
            case 1:
                $payPeriodFrom = $bodyData['daily']['pay_period_from'];
                $payPeriodTo = $bodyData['daily']['pay_period_to'];
                $this->handleCommissionDailyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 2:
                $payPeriodFrom = $bodyData['weekly']['pay_period_from'];
                $payPeriodTo = $bodyData['weekly']['pay_period_to'];
                $this->handleCommissionWeeklyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 5:
                $payPeriodFrom = $bodyData['monthly']['pay_period_from'];
                $payPeriodTo = $bodyData['monthly']['pay_period_to'];
                $this->handleCommissionMonthlyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::BI_WEEKLY_ID:
                $payPeriodFrom = $bodyData['biweekly']['pay_period_from'];
                $payPeriodTo = $bodyData['biweekly']['pay_period_to'];
                $this->handleCommissionBiAndSemiWeeklyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::SEMI_MONTHLY_ID:
                $payPeriodFrom = $bodyData['semimonthly']['pay_period_from'];
                $payPeriodTo = $bodyData['semimonthly']['pay_period_to'];
                $this->handleCommissionBiAndSemiWeeklyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            default:
                $payPeriodFrom = null;
                $payPeriodTo = null;
        }

        $payData = Payroll::where('user_id', $reconCommissionData->user_id)
            ->where([
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
        if ($payData) {
            $userReconComm = ReconCommissionHistory::where([
                'status' => 'payroll',
                'user_id' => $reconCommissionData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'is_ineligible' => '0',
            ])->first();
            /* update payroll id in recon history table after send to payroll recon data */
            if ($userReconComm) {
                ReconCommissionHistory::where([
                    'status' => 'payroll',
                    'user_id' => $reconCommissionData->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_ineligible' => '0',
                ])->update(['payroll_id' => $payData->id]);
            }

            /* update adjustment Data */
            ReconAdjustment::where('user_id', $reconCommissionData->user_id)
                ->where('pid', $reconCommissionData->pid)
                ->where('payroll_status', 'finalize')
                ->where('adjustment_type', 'commission')
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
                    'sent_count' => $commissionCount,
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

            $userReconComm = ReconCommissionHistory::where([
                'status' => 'payroll',
                'user_id' => $reconCommissionData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'is_ineligible' => '0',
            ])->first();
            if (isset($userReconComm) && $userReconComm != '') {
                ReconCommissionHistory::where([
                    'status' => 'payroll',
                    'user_id' => $reconCommissionData->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                    'is_ineligible' => '0',
                ])->update(['payroll_id' => $payRollId, 'sent_count' => $commissionCount]);
            }

            /* update adjustment Data */
            ReconAdjustment::where('user_id', $reconCommissionData->user_id)
                ->where('pid', $reconCommissionData->pid)
                ->where('payroll_status', 'finalize')
                ->where('adjustment_type', 'commission')
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
                    'sent_count' => $commissionCount,
                ]);
        }
    }

    private function handleCommissionWeeklyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconCommissionHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $commissionCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleCommissionDailyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconCommissionHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $commissionCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleCommissionMonthlyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconCommissionHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $commissionCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleCommissionBiAndSemiWeeklyFrequency($reconCommissionData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconCommissionHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('is_ineligible', '0')
            ->update([
                'status' => 'payroll',
                'sent_count' => $commissionCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }
}
