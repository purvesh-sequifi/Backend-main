<?php

namespace App\Core\Traits\ReconTraits;

use App\Core\Traits\PayRollClawbackTrait;
use App\Models\ClawbackSettlement;
use App\Models\CompanySetting;
use App\Models\FrequencyType;
use App\Models\Payroll;
use App\Models\PositionPayFrequency;
use App\Models\PositionReconciliations;
use App\Models\ReconAdjustment;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait ReconRoutineClawbackTraits
{
    use PayRollClawbackTrait;

    /**
     * Method callReconClawbackRoutine
     *
     * @object $saleData $saleData getting sale-data
     *
     * @param  $saleUserType  $saleUserType this means that sales-user if close or setter
     * @param  $saleUserId  $saleUserId : this is use for user_id
     */
    public function callReconClawbackRoutine($saleData): void
    {
        $pid = $saleData->pid;
        $closer = $saleData->sales_master_process->closer1_id;
        $secondCloser = $saleData->sales_master_process->closer2_id;
        $setter = $saleData->sales_master_process->setter1_id;
        $secondSetter = $saleData->sales_master_process->setter2_id;
        $saleCancelDate = $saleData->date_cancelled;
        $approvalDate = $saleData->customer_signoff;

        /* first closer clawback code */
        if ($closer) {
            $this->createCommissionClawBack($closer, $pid, $saleCancelDate, $approvalDate);

            $reconWithHeldData = UserReconciliationWithholding::where(['pid' => $pid, 'closer_id' => $closer])->first();
            $reconPaidAmount = ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $closer, 'type' => 'recon-commission', 'status' => 'payroll'])->sum('paid_amount') ?? 0;

            /* update status in the withhold table and finalize table */
            $reconWithHeldData?->update([
                'withhold_amount' => $reconPaidAmount,
                'status' => 'clawback',
            ]);

            ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $closer, 'status' => 'payroll'])->update(['status' => 'clawback']);

            /* manage commission history data */
            /* remove finalize history data from recon commission history table */
            ReconCommissionHistory::where(['user_id' => $closer, 'pid' => $pid, 'status' => 'finalize'])->delete();
            ReconciliationFinalizeHistory::where(['user_id' => $closer, 'pid' => $pid, 'status' => 'finalize'])->delete();

            /* delete recon adjustment if not paid from payroll */
            ReconAdjustment::where(['pid' => $pid, 'user_id' => $closer, 'payroll_status' => 'finalize'])/* ->where("payroll_execute_status", "!=", "3") */ ->delete();
        }

        /* second closer clawback code */
        if ($secondCloser) {
            $this->createCommissionClawBack($secondCloser, $pid, $saleCancelDate, $approvalDate);

            $reconWithHeldData = UserReconciliationWithholding::where(['pid' => $pid, 'closer_id' => $secondCloser])->first();
            $reconPaidAmount = ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $secondCloser, 'type' => 'recon-commission', 'status' => 'payroll'])->sum('paid_amount') ?? 0;

            /* update status in the withhold table and finalize table */
            $reconWithHeldData?->update([
                'withhold_amount' => $reconPaidAmount,
                'status' => 'clawback',
            ]);

            ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $secondCloser, 'status' => 'payroll'])->update(['status' => 'clawback']);

            /* manage commission history data */
            /* remove finalize history data from recon commission history table */
            ReconCommissionHistory::where(['user_id' => $secondCloser, 'pid' => $pid, 'status' => 'finalize'])->delete();
            ReconciliationFinalizeHistory::where(['user_id' => $secondCloser, 'pid' => $pid, 'status' => 'finalize'])->delete();

            /* delete recon adjustment if not paid from payroll */
            ReconAdjustment::where(['pid' => $pid, 'user_id' => $secondCloser, 'payroll_status' => 'finalize'])/* ->where("payroll_execute_status", "!=", "3") */ ->delete();
        }

        /* first setter clawback code */
        if ($setter && $closer != $setter) {
            $this->createCommissionClawBack($setter, $pid, $saleCancelDate, $approvalDate);

            $reconWithHeldData = UserReconciliationWithholding::where(['pid' => $pid, 'setter_id' => $setter])->first();
            $reconPaidAmount = ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $setter, 'type' => 'recon-commission', 'status' => 'payroll'])->sum('paid_amount') ?? 0;

            /* update status in the withhold table and finalize table */
            $reconWithHeldData?->update([
                'withhold_amount' => $reconPaidAmount,
                'status' => 'clawback',
            ]);

            ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $setter, 'status' => 'payroll'])->update(['status' => 'clawback']);

            /* manage commission history data */
            /* remove finalize history data from recon commission history table */
            ReconCommissionHistory::where(['user_id' => $setter, 'pid' => $pid, 'status' => 'finalize'])->delete();
            ReconciliationFinalizeHistory::where(['user_id' => $setter, 'pid' => $pid, 'status' => 'finalize'])->delete();

            /* delete recon adjustment if not paid from payroll */
            ReconAdjustment::where(['pid' => $pid, 'user_id' => $setter, 'payroll_status' => 'finalize'])/* ->where("payroll_execute_status", "!=", "3") */ ->delete();
        }

        /* second setter clawback code */
        if ($secondSetter) {
            $this->createCommissionClawBack($secondSetter, $pid, $saleCancelDate, $approvalDate);

            $reconWithHeldData = UserReconciliationWithholding::where(['pid' => $pid, 'setter_id' => $secondSetter])->first();
            $reconPaidAmount = ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $secondSetter, 'type' => 'recon-commission', 'status' => 'payroll'])->sum('paid_amount') ?? 0;

            /* update status in the withhold table and finalize table */
            $reconWithHeldData?->update([
                'withhold_amount' => $reconPaidAmount,
                'status' => 'clawback',
            ]);

            ReconCommissionHistory::where(['pid' => $pid, 'user_id' => $secondSetter, 'status' => 'payroll'])->update(['status' => 'clawback']);

            /* manage commission history data */
            /* remove finalize history data from recon commission history table */
            ReconCommissionHistory::where(['user_id' => $secondSetter, 'pid' => $pid, 'status' => 'finalize'])->delete();
            ReconciliationFinalizeHistory::where(['user_id' => $secondSetter, 'pid' => $pid, 'status' => 'finalize'])->delete();

            /* delete recon adjustment if not paid from payroll */
            ReconAdjustment::where(['pid' => $pid, 'user_id' => $secondSetter, 'payroll_status' => 'finalize'])/* ->where("payroll_execute_status", "!=", "3") */ ->delete();
        }

        /*  working on recon-override clawback */
        $this->reconOverrideClawback($saleData);
    }

    public function reconOverrideClawback($saleData)
    {
        $pid = $saleData->pid;
        $saleCancelDate = $saleData->date_cancelled;
        $approvalDate = $saleData->customer_signoff;

        $reconTotalPaidAmountData = ReconOverrideHistory::select('*', DB::raw('SUM(paid) as paid_amount'))->where(['pid' => $pid, 'status' => 'payroll', 'is_displayed' => '1'])->groupBy('pid', 'user_id', 'overrider', 'type')->get();
        $clawBacks = [];
        foreach ($reconTotalPaidAmountData as $value) {
            $userData = User::find($value->user_id);
            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $value->user_id)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->first();
            $subPositionId = $userData->sub_position_id;
            if ($userOrganizationHistory) {
                $subPositionId = $userOrganizationHistory->sub_position_id;
            }
            $payFrequencyType = PositionPayFrequency::where('position_id', $subPositionId)->first();

            /* create paid amount clawback data */
            $positionReconciliation = PositionReconciliations::where([
                'position_id' => $subPositionId,
                'status' => 1,
                'clawback_settlement' => 'Reconciliation',
            ])->first();

            $companySetting = CompanySetting::where(['type' => 'reconciliation', 'status' => '1'])->first();
            if ($companySetting && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = null;
                $payPeriodFrom = null;
                $payPeriodTo = null;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequencyNew($saleCancelDate, $userData->sub_position_id, $userData->id);
                $payPeriodFrom = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $payPeriodTo = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            $reconPaidAmount = number_format($value->paid_amount, 3, '.', '');
            $data = [
                'user_id' => $value->user_id,
                'sale_user_id' => $value->overrider,
                'position_id' => $subPositionId,
                'type' => 'recon-override',
                'pid' => $pid,
                'adders_type' => $value->type,
                'clawback_amount' => $reconPaidAmount,
                'clawback_type' => $clawbackType,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'is_stop_payroll' => $userData->stop_payroll,
            ];

            $checkClawbackData = ClawbackSettlement::where('pid', $pid)
                ->where('user_id', $value->user_id)
                ->where('sale_user_id', $value->overrider)
                ->where('type', 'recon-override')
                ->where('is_displayed', '1')
                ->where('adders_type', $value->type);

            $paidClawBackAmount = ClawbackSettlement::where('pid', $pid)
                ->where('user_id', $value->user_id)
                ->where('sale_user_id', $value->overrider)
                ->where('type', 'recon-override')
                ->where('adders_type', $value->type)
                ->where('is_displayed', '1')
                ->sum('clawback_amount');

            $check = false;
            $totalClawbackAmount = number_format($reconPaidAmount, 2, '.', '') - number_format($paidClawBackAmount, 2, '.', '');
            if ($totalClawbackAmount) {
                if ($checkClawbackData->exists()) {
                    $checkClawbackData->update($data);
                    $check = true;
                } else {
                    ClawbackSettlement::create($data);
                    $check = true;
                }

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($value->user_id, $subPositionId, 0, $payFrequency, $pid);
                }

                if ($check) {
                    /* update move to recon amount in user commission tables */
                    $moveToReconCommissionQuery = DB::table('user_overrides as user_overrides_move_to_recon')
                        ->select(
                            'user_overrides_move_to_recon.*'
                        )
                        ->where('user_overrides_move_to_recon.pid', $pid)
                        ->where('user_overrides_move_to_recon.type', $value->type)
                        ->where('user_overrides_move_to_recon.sale_user_id', $value->overrider)
                        ->where('user_overrides_move_to_recon.status', 6)
                        ->where('user_overrides_move_to_recon.is_move_to_recon', 1)
                        ->where('user_overrides_move_to_recon.is_displayed', '1')
                        ->where('user_overrides_move_to_recon.user_id', $value->user_id);

                    $moveToReconCommissionResult = new Collection;
                    if ($payFrequencyType->frequency_type_id == FrequencyType::WEEKLY_ID) {
                        $moveToReconCommissionQuery->join('weekly_pay_frequencies as w_p_f', function ($join) {
                            $join->on('w_p_f.pay_period_from', '=', 'user_overrides_move_to_recon.pay_period_from')
                                ->on('w_p_f.pay_period_to', '=', 'user_overrides_move_to_recon.pay_period_to')
                                ->where('w_p_f.closed_status', '=', '1');
                        });
                        $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                    } elseif ($payFrequencyType->frequency_type_id == FrequencyType::MONTHLY_ID) {
                        $moveToReconCommissionQuery->join('monthly_pay_frequencies as m_p_f', function ($join) {
                            $join->on('m_p_f.pay_period_from', '=', 'user_overrides_move_to_recon.pay_period_from')
                                ->on('m_p_f.pay_period_to', '=', 'user_overrides_move_to_recon.pay_period_to')
                                ->where('m_p_f.closed_status', '=', '1');
                        });
                        $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                    } elseif ($payFrequencyType->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                        $moveToReconCommissionQuery->join('additional_pay_frequencies as bw_p_f', function ($join) {
                            $join->on('bw_p_f.pay_period_from', '=', 'user_overrides_move_to_recon.pay_period_from')
                                ->on('bw_p_f.pay_period_to', '=', 'user_overrides_move_to_recon.pay_period_to')
                                ->on('bw_p_f.type', '=', '1')
                                ->where('bw_p_f.closed_status', '=', '1');
                        });
                        $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                    } elseif ($payFrequencyType->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                        $moveToReconCommissionQuery->join('monthly_pay_frequencies as sm_p_f', function ($join) {
                            $join->on('sm_p_f.pay_period_from', '=', 'user_overrides_move_to_recon.pay_period_from')
                                ->on('sm_p_f.pay_period_to', '=', 'user_overrides_move_to_recon.pay_period_to')
                                ->on('sm_p_f.type', '=', '2')
                                ->where('sm_p_f.closed_status', '=', '1');
                        });
                        $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                    }

                    if (isset($moveToReconCommissionResult) && $moveToReconCommissionResult) {
                        $override = UserOverrides::where('pid', $pid)
                            ->where('type', $value->type)
                            ->where('sale_user_id', $value->overrider)
                            ->where('overrides_settlement_type', 'during_m2')
                            ->where('is_displayed', '1')
                            ->where('user_id', $value->user_id)->first();
                        if ($override) {
                            $override->update(['amount' => $reconPaidAmount]);
                            $clawBacks[] = $override->id;
                        }
                    } else {
                        if ($override = UserOverrides::where('pid', $pid)
                            ->where('type', $value->type)
                            ->where('sale_user_id', $value->overrider)
                            ->where('overrides_settlement_type', 'reconciliation')
                            ->where('is_displayed', '1')
                            ->where('user_id', $value->user_id)->first()
                        ) {
                            $override->update(['amount' => $reconPaidAmount]);
                            $clawBacks[] = $override->id;
                        }
                    }
                }
            }
        }

        if (count($reconTotalPaidAmountData) != 0) {
            UserOverrides::where(['pid' => $pid, 'is_displayed' => '1', 'overrides_settlement_type' => 'reconciliation'])->whereNotIn('id', $clawBacks)->delete();
            UserOverrides::where(['pid' => $pid, 'is_displayed' => '1', 'status' => '6', 'is_move_to_recon' => '1'])->whereNotIn('id', $clawBacks)->delete();
        } else {
            if (! ReconOverrideHistory::where(['pid' => $pid, 'status' => 'clawback', 'is_displayed' => '1'])->first()) {
                UserOverrides::where(['pid' => $pid, 'status' => '6', 'is_move_to_recon' => '1', 'is_displayed' => '1'])->delete();
                UserOverrides::where(['pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->delete();
            }
        }

        $reconPayrollPaidData = ReconOverrideHistory::where(['pid' => $pid, 'is_displayed' => '1'])->get();
        $reconPaidAmount = $reconPayrollPaidData->sum('paid_amount');
        $reconPayrollPaidData->each(function ($recon) {
            $recon->update(['status' => 'clawback']);
        });

        /* manage commission history data */
        /* remove finalize history data from recon commission history table */
        ReconOverrideHistory::where(['pid' => $pid, 'status' => 'finalize', 'is_displayed' => '1'])->delete();
        ReconciliationFinalizeHistory::where(['pid' => $pid, 'status' => 'finalize', 'is_displayed' => '1'])->delete();

        /* delete recon adjustment if not paid from payroll */
        ReconAdjustment::where(['pid' => $pid,  'payroll_status' => 'finalize'])/* ->where("payroll_execute_status", "!=", 3) */ ->delete();
    }

    protected function manageReconClawback($salesData, $userId, $userType)
    {
        $pid = $salesData->pid;
        $userData = User::find($userId);
        if ($userId) {
            $paidFinalizeAmount = DB::table('reconciliation_finalize_history As r_f_h_t')
                ->join('weekly_pay_frequencies As w_p_f_t', function ($join) {
                    $join->on('r_f_h_t.pay_period_from', 'w_p_f_t.pay_period_from')
                        ->on('r_f_h_t.pay_period_to', 'w_p_f_t.pay_period_to')
                        ->where('w_p_f_t.closed_status', 1);
                })
                ->select('r_f_h_t.id as id')
                ->where('r_f_h_t.pid', $pid)
                ->where('r_f_h_t.user_id', $userId)
                ->where('r_f_h_t.status', 'payroll')
                ->get();

            $userPosition = $userType == 'closer' ? 2 : 3;
            $clawbackAmount = $paidFinalizeAmount->sum('net_amount');
            if ($paidFinalizeAmount) {
                ClawbackSettlement::create([
                    'user_id' => $userId,
                    'position_id' => $userPosition,
                    'pid' => $pid,
                    'clawback_amount' => $clawbackAmount,
                    'clawback_type' => $userData->reconciliations->clawback_settlement,
                    'pay_period_from' => $userData->reconciliations->clawback_settlement == 'reconciliation' ? null : Carbon::now()->format('Y-m-d'),
                    'pay_period_to' => $userData->reconciliations->clawback_settlement == 'reconciliation' ? null : Carbon::now()->format('Y-m-d'),
                    'is_stop_payroll' => $userData->stop_payroll,
                ]);

                if ($userData->reconciliations->clawback_settlement == 'next payroll') {
                    $date = $salesData->date_cancelled;
                    $payFrequency = $this->payFrequencyNew($date, $userData->sub_position_id, $userData->id);
                    $this->updateReconOverrideClawback($userId, $userPosition, $clawbackAmount, $payFrequency, $pid);
                }
                // Update the status to "clawback" for relevant records
                DB::table('reconciliation_finalize_history')
                    ->whereIn('id', $paidFinalizeAmount->pluck('id'))
                    ->update(['status' => 'clawback']);

                DB::table('reconciliation_finalize_history')
                    ->where('pid', $pid)
                    ->where('user_id', $userId)
                    ->where('status', 'finalize')
                    ->delete();

                $column_name = $userType == 'closer' ? 'closer_id' : 'setter_id';
                DB::table('user_reconciliation_withholds')
                    ->where($column_name, $userId)
                    ->where('pid', $pid)
                    ->update([
                        'withhold_amount' => $clawbackAmount,
                    ]);
            }
        }
    }

    public function updateReconClawback($userID, $position_id, $clawback, $payFrequency, $pid)
    {
        $payRoll = Payroll::where(['user_id' => $userID, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to])->first();
        if ($payRoll) {
            $payRoll->clawback = ($payRoll->clawback + ($clawback));
            $payRoll->save();
        } else {
            PayRoll::create([
                'user_id' => $userID,
                'position_id' => $position_id,
                'reconciliation' => -1 * $clawback,
                // 'commission' => (0 - $clawback),
                'pay_period_from' => $payFrequency->pay_period_from,
                'pay_period_to' => $payFrequency->pay_period_to,
                'status' => 1,
            ]);
        }

        $update = [
            'pay_period_from' => $payFrequency->pay_period_from,
            'pay_period_to' => $payFrequency->pay_period_to,
        ];
        ClawbackSettlement::where(['user_id' => $userID, 'pid' => $pid])->where('is_displayed', '1')->where('status', '!=', '3')->update($update);
    }

    public function createCommissionClawBack($userId, $pid, $saleCancelDate, $approvalDate)
    {
        $userData = User::find($userId);
        $userOrganizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->first();
        $subPositionId = $userData->sub_position_id;
        if ($userOrganizationHistory) {
            $subPositionId = $userOrganizationHistory->sub_position_id;
        }
        $payFrequencyType = PositionPayFrequency::where('position_id', $subPositionId)->first();

        /* create paid amount clawback data */
        $positionReconciliation = PositionReconciliations::where([
            'position_id' => $userData->sub_position_id,
            'status' => 1,
            'clawback_settlement' => 'Reconciliation',
        ])->first();

        $companySetting = CompanySetting::where('type', 'reconciliation')->first();
        if ($companySetting->status == '1' && $positionReconciliation) {
            $clawbackType = 'reconciliation';
            $payFrequency = null;
            $payPeriodFrom = null;
            $payPeriodTo = null;
        } else {
            $clawbackType = 'next payroll';
            $payFrequency = $this->payFrequencyNew($saleCancelDate, $userData->sub_position_id, $userData->id);
            $payPeriodFrom = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
            $payPeriodTo = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
        }

        $reconTotalPaidAmountData = ReconCommissionHistory::selectRaw('SUM(paid_amount) as paid_amount, type, id')
            ->where(['user_id' => $userId, 'status' => 'payroll', 'pid' => $pid, 'is_displayed' => '1'])->groupBy('type')->get();

        $clawBacks = [];
        foreach ($reconTotalPaidAmountData as $value) {
            $reconPaidAmount = $value->paid_amount;
            $data = [
                'user_id' => $userId,
                'position_id' => $userData->position_id,
                'type' => 'recon-commission',
                'pid' => $pid,
                'adders_type' => $value->type,
                'clawback_amount' => $reconPaidAmount,
                'clawback_type' => $clawbackType,
                'pay_period_from' => $payPeriodFrom,
                'pay_period_to' => $payPeriodTo,
                'is_stop_payroll' => $userData->stop_payroll,
            ];

            $checkClawbackData = ClawbackSettlement::where('pid', $pid)
                ->where('adders_type', $value->type)
                ->where('type', 'recon-commission')
                ->where('is_displayed', '1')
                ->where('user_id', $userId);

            $paidClawBackAmount = ClawbackSettlement::where('status', 3)
                ->where('pid', $pid)
                ->where('type', 'recon-commission')
                ->where('user_id', $userId)
                ->where('adders_type', $value->type)
                ->where('is_displayed', '1')
                ->sum('clawback_amount');

            $check = false;
            $totalClawbackAmount = number_format($reconPaidAmount, 2, '.', '') - number_format($paidClawBackAmount, 2, '.', '');
            if ($totalClawbackAmount) {
                if ($checkClawbackData->exists()) {
                    $checkClawbackData->update($data);
                    $check = true;
                } else {
                    ClawbackSettlement::create($data);
                    $check = true;
                }
            }

            if ($check) {
                /* update move to recon amount in user commission tables */
                $moveToReconCommissionQuery = DB::table('user_commission as user_commission_move_to_recon')
                    ->select('user_commission_move_to_recon.*')
                    ->where('user_commission_move_to_recon.pid', $pid)
                    ->where('user_commission_move_to_recon.amount_type', $value->type)
                    ->where('user_commission_move_to_recon.status', 6)
                    ->where('user_commission_move_to_recon.is_move_to_recon', 1)
                    ->where('user_commission_move_to_recon.is_displayed', '1')
                    ->where('user_commission_move_to_recon.user_id', $userId);

                $moveToReconCommissionResult = new Collection;
                if ($payFrequencyType->frequency_type_id == FrequencyType::WEEKLY_ID) {
                    $moveToReconCommissionQuery->join('weekly_pay_frequencies as w_p_f', function ($join) {
                        $join->on('w_p_f.pay_period_from', '=', 'user_commission_move_to_recon.pay_period_from')
                            ->on('w_p_f.pay_period_to', '=', 'user_commission_move_to_recon.pay_period_to')
                            ->where('w_p_f.closed_status', '=', '1');
                    });
                    $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                } elseif ($payFrequencyType->frequency_type_id == FrequencyType::MONTHLY_ID) {
                    $moveToReconCommissionQuery->join('monthly_pay_frequencies as m_p_f', function ($join) {
                        $join->on('m_p_f.pay_period_from', '=', 'user_commission_move_to_recon.pay_period_from')
                            ->on('m_p_f.pay_period_to', '=', 'user_commission_move_to_recon.pay_period_to')
                            ->where('m_p_f.closed_status', '=', '1');
                    });
                    $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                } elseif ($payFrequencyType->frequency_type_id == FrequencyType::BI_WEEKLY_ID) {
                    $moveToReconCommissionQuery->join('additional_pay_frequencies as bw_p_f', function ($join) {
                        $join->on('bw_p_f.pay_period_from', '=', 'user_commission_move_to_recon.pay_period_from')
                            ->on('bw_p_f.pay_period_to', '=', 'user_commission_move_to_recon.pay_period_to')
                            ->on('bw_p_f.type', '=', '1')
                            ->where('bw_p_f.closed_status', '=', '1');
                    });
                    $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                } elseif ($payFrequencyType->frequency_type_id == FrequencyType::SEMI_MONTHLY_ID) {
                    $moveToReconCommissionQuery->join('monthly_pay_frequencies as sm_p_f', function ($join) {
                        $join->on('sm_p_f.pay_period_from', '=', 'user_commission_move_to_recon.pay_period_from')
                            ->on('sm_p_f.pay_period_to', '=', 'user_commission_move_to_recon.pay_period_to')
                            ->on('sm_p_f.type', '=', '2')
                            ->where('sm_p_f.closed_status', '=', '1');
                    });
                    $moveToReconCommissionResult = $moveToReconCommissionQuery->first();
                }

                if (isset($moveToReconCommissionResult) && $moveToReconCommissionResult) {
                    UserCommission::where('id', $moveToReconCommissionResult->id)->update(['amount' => $reconPaidAmount]);
                    $clawBacks[] = $moveToReconCommissionResult->id;
                }
            }
        }

        if (count($reconTotalPaidAmountData) == 0) {
            UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'status' => '6', 'is_move_to_recon' => '1', 'is_displayed' => '1'])->whereIn('id', $clawBacks)->delete();
        }

        // PAYROLL DATA
        if ($clawbackType == 'next payroll') {
            $this->updateClawback($userId, $subPositionId, 0, $payFrequency, $pid);
        }

        /* delete data on the move to recon is not paid */
        $moveToReconCommissionQuery = DB::table('user_commission as user_commission_move_to_recon')
            ->select('user_commission_move_to_recon.*')
            ->where('user_commission_move_to_recon.pid', $pid)
            ->where('user_commission_move_to_recon.status', 6)
            ->where('user_commission_move_to_recon.is_move_to_recon', 1)
            ->where('user_commission_move_to_recon.is_displayed', '1')
            ->where('user_commission_move_to_recon.user_id', $userId);

        $frequencyTableMapping = [
            FrequencyType::WEEKLY_ID => ['table' => 'weekly_pay_frequencies', 'alias' => 'w_p_f', 'type' => null],
            FrequencyType::MONTHLY_ID => ['table' => 'monthly_pay_frequencies', 'alias' => 'm_p_f', 'type' => null],
            FrequencyType::BI_WEEKLY_ID => ['table' => 'additional_pay_frequencies', 'alias' => 'bw_p_f', 'type' => 1],
            FrequencyType::SEMI_MONTHLY_ID => ['table' => 'monthly_pay_frequencies', 'alias' => 'sm_p_f', 'type' => 2],
        ];

        if (isset($frequencyTableMapping[$payFrequencyType->frequency_type_id])) {
            $mapping = $frequencyTableMapping[$payFrequencyType->frequency_type_id];

            $moveToReconCommissionQuery->join("{$mapping['table']} as {$mapping['alias']}", function ($join) use ($mapping) {
                $join->on("{$mapping['alias']}.pay_period_from", '=', 'user_commission_move_to_recon.pay_period_from')
                    ->on("{$mapping['alias']}.pay_period_to", '=', 'user_commission_move_to_recon.pay_period_to')
                    ->where("{$mapping['alias']}.closed_status", '=', '1');

                if (! is_null($mapping['type'])) {
                    $join->on("{$mapping['alias']}.type", '=', DB::raw($mapping['type']));
                }
            });

            $moveToReconCommissionResult = $moveToReconCommissionQuery->get();
        }

        foreach ($moveToReconCommissionResult as $value) {
            $checkPaidAmount = ReconCommissionHistory::where('pid', $value->pid)
                ->where('user_id', $value->user_id)
                ->where('type', $value->amount_type)
                ->where('is_displayed', '1')
                ->where('move_from_payroll', 1);

            if (! $checkPaidAmount->exists()) {
                UserCommission::find($value->id)->delete();
            }
        }
    }
}
