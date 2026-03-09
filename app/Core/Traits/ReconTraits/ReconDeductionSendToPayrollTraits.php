<?php

namespace App\Core\Traits\ReconTraits;

use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\PayrollDeductions;
use App\Models\ReconDeductionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait ReconDeductionSendToPayrollTraits
{
    public function sendToPayrollDeduction($deductionFinalizeData, $bodyData)
    {
        foreach ($deductionFinalizeData as $value) {
            $usersData = User::with('positionDetail')->where('id', $value->user_id)->first();
            /* UPdate deductions data in deduction table */
            $userData = User::with(['positionDetail.payFrequency.frequencyType'])
                ->where('id', $value->user_id)
                ->get(['id', 'sub_position_id'])
                ->map(function ($user) {
                    // Collect pay frequencies, ensuring no null values
                    return $user->positionDetail?->payFrequency?->frequencyType?->name;
                });
            $payFrequencyTypes = $userData->unique();
            PayrollDeductions::where('user_id', $value->user_id)
                ->where('is_move_to_recon', 1)->where('status', 3)
                ->where('is_move_to_recon_paid', 0)
                ->update([
                    'is_move_to_recon_paid' => 1,
                ]);
            $deductionQuery = DB::table('payroll_deductions As p_d_t')
                ->join('users', 'users.id', '=', 'p_d_t.user_id')
                ->select(
                    'p_d_t.id AS p_d_t_id',
                    'p_d_t.user_id AS p_d_t_user_id',
                    DB::raw('SUM(p_d_t.total) AS total_p_d_t_amount')
                )
                ->where('p_d_t.status', 3)
                ->where('p_d_t.is_move_to_recon', 1)
                ->where('p_d_t.is_move_to_recon_paid', 0)
                ->whereBetween('p_d_t.pay_period_from', [$value->start_date, $value->end_date])
                ->whereBetween('p_d_t.pay_period_to', [$value->start_date, $value->end_date])
                ->groupBy('p_d_t.user_id');

            foreach ($payFrequencyTypes as $frequency) {
                $query = clone $deductionQuery;

                if ($frequency === 'Weekly') {
                    $query->join('weekly_pay_frequencies as w_p_f_move_to_recon_payroll_deduction', function ($join) {
                        $join->on('w_p_f_move_to_recon_payroll_deduction.pay_period_from', '=', 'p_d_t.pay_period_from')
                            ->on('w_p_f_move_to_recon_payroll_deduction.pay_period_to', '=', 'p_d_t.pay_period_to')
                            ->where('w_p_f_move_to_recon_payroll_deduction.closed_status', '=', 1);
                    });
                } elseif ($frequency === 'Monthly') {
                    $query->join('monthly_pay_frequencies as m_p_f_move_to_recon_payroll_deduction', function ($join) {
                        $join->on('m_p_f_move_to_recon_payroll_deduction.pay_period_from', '=', 'p_d_t.pay_period_from')
                            ->on('m_p_f_move_to_recon_payroll_deduction.pay_period_to', '=', 'p_d_t.pay_period_to')
                            ->where('m_p_f_move_to_recon_payroll_deduction.closed_status', '=', 1);
                    });
                }
                $query->update([
                    'is_move_to_recon_paid' => 1,
                ]);
            }
            $this->manageReconDeductionPayFrequency($value, $usersData, $bodyData);
        }
    }

    public function manageReconDeductionPayFrequency($reconDeductionData, $userData, $bodyData)
    {
        $frequencyTypeId = $userData?->positionDetail?->payFrequency?->frequency_type_id;
        $startDate = $reconDeductionData->start_date;
        $endDate = $reconDeductionData->end_date;

        /* Calculate the new sent count */
        switch ($frequencyTypeId) {
            case 1:
                $payPeriodFrom = $bodyData['daily']['pay_period_from'];
                $payPeriodTo = $bodyData['daily']['pay_period_to'];
                $this->handleDeductionDailyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo);
                break;
            case 2:
                $payPeriodFrom = $bodyData['weekly']['pay_period_from'];
                $payPeriodTo = $bodyData['weekly']['pay_period_to'];
                $this->handleDeductionWeeklyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo);
                break;
            case 5:
                $payPeriodFrom = $bodyData['monthly']['pay_period_from'];
                $payPeriodTo = $bodyData['monthly']['pay_period_to'];
                $this->handleDeductionMonthlyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::BI_WEEKLY_ID:
                $payPeriodFrom = $bodyData['biweekly']['pay_period_from'];
                $payPeriodTo = $bodyData['biweekly']['pay_period_to'];
                $this->handleDeductionBiAndSemiWeeklyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo);
                break;
            case FrequencyType::SEMI_MONTHLY_ID:
                $payPeriodFrom = $bodyData['semimonthly']['pay_period_from'];
                $payPeriodTo = $bodyData['semimonthly']['pay_period_to'];
                $this->handleDeductionBiAndSemiWeeklyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo);
                break;
            default:
                $payPeriodFrom = null;
                $payPeriodTo = null;
        }

        $payData = Payroll::where('user_id', $reconDeductionData->user_id)
            ->where([
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
        if ($payData) {
            $userReconComm = ReconDeductionHistory::where([
                'status' => 'payroll',
                'user_id' => $reconDeductionData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
            /* update payroll id in recon history table after send to payroll recon data */
            if ($userReconComm) {
                ReconDeductionHistory::where([
                    'status' => 'payroll',
                    'user_id' => $reconDeductionData->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                ])->update(['payroll_id' => $payData->id]);
            }
        } else {
            $payroll_data = Payroll::create([
                'user_id' => $userData->id,
                'position_id' => $userData->sub_position_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'status' => 1,
            ]);
            $payRollId = $payroll_data->id;

            $userReconComm = ReconDeductionHistory::where([
                'status' => 'payroll',
                'user_id' => $reconDeductionData->user_id,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ])->first();
            if (isset($userReconComm) && $userReconComm != '') {
                ReconDeductionHistory::where([
                    'status' => 'payroll',
                    'user_id' => $reconDeductionData->user_id,
                    'pay_period_from' => $payPeriodFrom,
                    'pay_period_to' => $payPeriodTo,
                ])->update(['payroll_id' => $payRollId]);
            }
        }
    }

    private function handleDeductionWeeklyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo)
    {
        /* Clawback history data update */
        ReconDeductionHistory::where('status', 'finalize')
            ->where('user_id', $reconDeductionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleDeductionDailyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconDeductionHistory::where('status', 'finalize')
            ->where('user_id', $reconDeductionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleDeductionMonthlyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconDeductionHistory::where('status', 'finalize')
            ->where('user_id', $reconDeductionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }

    private function handleDeductionBiAndSemiWeeklyFrequency($reconDeductionData, $startDate, $endDate, $payPeriodFrom, $payPeriodTo)
    {
        /* Commission history data update */
        ReconDeductionHistory::where('status', 'finalize')
            ->where('user_id', $reconDeductionData->user_id)
            ->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->update([
                'status' => 'payroll',
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
            ]);
    }
}
