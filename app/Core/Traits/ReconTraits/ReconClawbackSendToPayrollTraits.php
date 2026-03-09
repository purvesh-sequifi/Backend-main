<?php

namespace App\Core\Traits\ReconTraits;

use App\Models\ClawbackSettlement;
use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\ReconAdjustment;
use App\Models\ReconClawbackHistory;
use App\Models\User;
use App\Models\UserCommission;

trait ReconClawbackSendToPayrollTraits
{
    public function sendToPayrollClawback($clawbackFinalizeData, $bodyData)
    {
        foreach ($clawbackFinalizeData as $value) {
            $usersData = User::with('positionDetail')->where('id', $value->user_id)->first();
            $this->manageReconClawbackPayFrequency($value, $usersData, $bodyData);

            /* update recon payment status code */
            if ($value->percentage == 100) {
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
            }
        }
    }

    public function manageReconClawbackPayFrequency($reconClawbackData, $userData, $bodyData)
    {
        $frequencyTypeId = $userData?->positionDetail?->payFrequency?->frequency_type_id;
        $startDate = $reconClawbackData->start_date;
        $endDate = $reconClawbackData->end_date;

        $commissionSent = ReconClawbackHistory::where('status', 'payroll')
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->orderBy('id', 'desc')
            ->first();

        /* Calculate the new sent count */
        $commissionCount = isset($commissionSent->sent_count) ? $commissionSent->sent_count + 1 : 1;
        switch ($frequencyTypeId) {
            case 1:
                $payPeriodFrom = $bodyData['daily']['pay_period_from'];
                $payPeriodTo = $bodyData['daily']['pay_period_to'];
                $this->handleClawbackDailyFrequency($reconClawbackData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 2:
                $payPeriodFrom = $bodyData['weekly']['pay_period_from'];
                $payPeriodTo = $bodyData['weekly']['pay_period_to'];
                $this->handleClawbackWeeklyFrequency($reconClawbackData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case 5:
                $payPeriodFrom = $bodyData['monthly']['pay_period_from'];
                $payPeriodTo = $bodyData['monthly']['pay_period_to'];
                $this->handleClawbackMonthlyFrequency($reconClawbackData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::BI_WEEKLY_ID:
                $payPeriodFrom = $bodyData['biweekly']['pay_period_from'];
                $payPeriodTo = $bodyData['biweekly']['pay_period_to'];
                $this->handleClawbackBiAndSemiWeeklyFrequency($reconClawbackData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::SEMI_MONTHLY_ID:
                $payPeriodFrom = $bodyData['semimonthly']['pay_period_from'];
                $payPeriodTo = $bodyData['semimonthly']['pay_period_to'];
                $this->handleClawbackBiAndSemiWeeklyFrequency($reconClawbackData, $startDate, $endDate, $commissionCount, $payPeriodFrom, $payPeriodTo);
                break;
            default:
                $payPeriodFrom = null;
                $payPeriodTo = null;
        }

        if ($payData = Payroll::where(['user_id' => $reconClawbackData->user_id, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo])->first()) {
            $payrollId = $payData->id;
        } else {
            $payroll_data = Payroll::create([
                'user_id' => $userData->id,
                'position_id' => $userData->sub_position_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'status' => 1,
            ]);
            $payrollId = $payroll_data->id;
        }

        $userReconComm = ReconClawbackHistory::where([
            'status' => 'payroll',
            'user_id' => $reconClawbackData->user_id,
            'pay_period_from' => $payPeriodFrom,
            'pay_period_to' => $payPeriodTo,
        ])->first();
        /* update payroll id in recon history table after send to payroll recon data */
        if ($userReconComm) {
            ReconClawbackHistory::where([
                'status' => 'payroll',
                'user_id' => $reconClawbackData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->update(['payroll_id' => $payrollId]);
        }

        $totalClawbackDue = ClawbackSettlement::where([
            'user_id' => $userData->id,
            'clawback_type' => 'reconciliation',
            'pid' => $reconClawbackData->pid,
            // 'status' => '1',
            'status' => '3',
            'payroll_id' => 0,
        ])->first();

        if ($totalClawbackDue) {
            $totalClawbackDue->update([
                'payroll_id' => $payrollId,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'recon_status' => 3,
            ]);
        }

        $moveToReconClawbackDue = ClawbackSettlement::where([
            'user_id' => $userData->id,
            'clawback_type' => 'next payroll',
            'pid' => $reconClawbackData->pid,
            'status' => 6,
            'is_move_to_recon' => 1,
        ])->first();
        if ($moveToReconClawbackDue) {
            $moveToReconClawbackDue->update([
                'payroll_id' => $payrollId,
                'is_move_to_recon' => 0,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
        }

        ReconAdjustment::where('user_id', $reconClawbackData->user_id)
            ->where('pid', $reconClawbackData->pid)
            ->where('payroll_status', 'finalize')
            ->where('adjustment_type', 'clawback')
            ->where('adjustment_override_type', $reconClawbackData->type)
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->whereNull('payroll_id')
            ->whereNull('pay_period_from')
            ->whereNull('pay_period_to')
            ->update([
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'payroll_id' => $payrollId,
                'payroll_status' => 'payroll',
                'sent_count' => $commissionCount,
            ]);
    }

    private function handleClawbackWeeklyFrequency($reconClawbackData, $startDate, $endDate, $reconClawbackCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Clawback history data update */
        ReconClawbackHistory::where('status', 'finalize')
            ->where('user_id', $reconClawbackData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $reconClawbackCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleClawbackDailyFrequency($reconCommissionData, $startDate, $endDate, $reconClawbackCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconClawbackHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $reconClawbackCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleClawbackMonthlyFrequency($reconCommissionData, $startDate, $endDate, $reconClawbackCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconClawbackHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $reconClawbackCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleClawbackBiAndSemiWeeklyFrequency($reconCommissionData, $startDate, $endDate, $reconClawbackCount, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconClawbackHistory::where('status', 'finalize')
            ->where('user_id', $reconCommissionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'sent_count' => $reconClawbackCount,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }
}
