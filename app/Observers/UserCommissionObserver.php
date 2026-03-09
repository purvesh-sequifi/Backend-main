<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use App\Models\UserCommission;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class UserCommissionObserver
{
    /**
     * Handle the UserCommission "updated" event.
     * Mark as paid, Move to next, Move to reconciliation, Onetime payment, Upfront/Commission Amount Update
     */
    public function updated(UserCommission $userCommission)
    {
        $relevantFields = [
            'amount',
            'is_mark_paid',
            'is_next_payroll',
            'is_move_to_recon',
            'pay_period_from',
            'pay_period_to',
            'pay_frequency',
            'user_worker_type',
            'settlement_type'
        ];

        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $userCommission->getAttributes()) && $userCommission->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $userCommission->getAttributes()) && $userCommission->isDirty('pay_period_from') || array_key_exists('pay_period_to', $userCommission->getAttributes()) && $userCommission->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        $settlementTypeChange = false;
        if (array_key_exists('settlement_type', $userCommission->getAttributes()) && $userCommission->isDirty('settlement_type') || array_key_exists('settlement_type', $userCommission->getAttributes()) && $userCommission->isDirty('settlement_type')) {
            $settlementTypeChange = true;
        }

        try {
            DB::beginTransaction();
            $status = $userCommission->status ?? 1;
            $settlementType = $userCommission->settlement_type ?? 'during_m2';
            $reconStatus = $userCommission->recon_status ?? 1;
            if ($settlementType == 'reconciliation' && in_array($reconStatus, [2, 3])) {
                DB::rollBack();
                return;
            }
            if ($settlementType == 'during_m2' && $status == 3) {
                DB::rollBack();
                return;
            }

            if ($settlementTypeChange) {
                $oldSettlementType = $userCommission->getOriginal('settlement_type') ?? 'during_m2';
                if ($oldSettlementType == 'during_m2') { // FROM during_m2 to reconciliation
                    $oldFrom = $userCommission->getOriginal('pay_period_from');
                    $oldTo = $userCommission->getOriginal('pay_period_to');

                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $userCommission->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $userCommission->pay_frequency, 'worker_type' => $userCommission->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $commissionAmount = UserCommission::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                            $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                            $oldPayroll->commission = $finalAmount;
                            $oldPayroll->save();
                            payrollCalculateNetPay($oldPayroll->id);
                        }
                    }

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $userCommission->id, 'payroll_type' => 'commission', 'type' => $userCommission->schema_type, 'adjustment_type' => $userCommission->schema_type, 'status' => 1])->first();
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->delete();
                    }
                } else { // FROM reconciliation to during_m2
                    $payroll = Payroll::where(['user_id' => $userCommission->user_id, 'pay_period_from' => $userCommission->pay_period_from, 'pay_period_to' => $userCommission->pay_period_to, 'pay_frequency' => $userCommission->pay_frequency, 'worker_type' => $userCommission->user_worker_type, 'status' => 1])->first();
                    if (!$payroll) {
                        $positionId = isset($userCommission->position_id) ? $userCommission->position_id : null;
                        if (!$positionId) {
                            $positionId = User::find($userCommission->user_id)?->sub_position_id;
                        }
                        $payroll = Payroll::create([
                            'user_id' => $userCommission->user_id,
                            'position_id' => $positionId,
                            'pay_frequency' => $userCommission->pay_frequency,
                            'worker_type' => $userCommission->user_worker_type,
                            'pay_period_from' => $userCommission->pay_period_from,
                            'pay_period_to' => $userCommission->pay_period_to,
                            'status' => 1
                        ]);
                    }

                    $userCommission->payroll_id = $payroll->id;
                    $userCommission->saveQuietly(); // Use saveQuietly to prevent observer events

                    $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->commission = $finalAmount;
                    $payroll->is_mark_paid = 0;
                    $payroll->is_next_payroll = 0;
                    $payroll->save();

                    payrollCalculateNetPay($payroll->id);
                }
            } else {
                if ($settlementType != 'reconciliation' && $status != 3) {
                    $oldFrom = $userCommission->getOriginal('pay_period_from');
                    $oldTo = $userCommission->getOriginal('pay_period_to');

                    $payroll = Payroll::where(['user_id' => $userCommission->user_id, 'pay_period_from' => $userCommission->pay_period_from, 'pay_period_to' => $userCommission->pay_period_to, 'pay_frequency' => $userCommission->pay_frequency, 'worker_type' => $userCommission->user_worker_type, 'status' => 1])->first();
                    if (!$payroll) {
                        $positionId = isset($userCommission->position_id) ? $userCommission->position_id : null;
                        if (!$positionId) {
                            $positionId = User::find($userCommission->user_id)?->sub_position_id;
                        }
                        $payroll = Payroll::create([
                            'user_id' => $userCommission->user_id,
                            'position_id' => $positionId,
                            'pay_frequency' => $userCommission->pay_frequency,
                            'worker_type' => $userCommission->user_worker_type,
                            'pay_period_from' => $userCommission->pay_period_from,
                            'pay_period_to' => $userCommission->pay_period_to,
                            'status' => 1
                        ]);
                    }

                    $userCommission->payroll_id = $payroll->id;
                    if ($periodChange) {
                        $userCommission->is_mark_paid = 0;
                        $userCommission->is_next_payroll = 0;
                        $userCommission->is_move_to_recon = 0;
                    }
                    $userCommission->saveQuietly(); // Use saveQuietly to prevent observer events

                    $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->commission = $finalAmount;
                    if ($periodChange) {
                        $payroll->is_mark_paid = 0;
                        $payroll->is_next_payroll = 0;
                    }
                    $payroll->save();
                    payrollCalculateNetPay($payroll->id);

                    if ($periodChange) {
                        if ($oldFrom && $oldTo) {
                            $oldPayroll = Payroll::where(['user_id' => $userCommission->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $userCommission->pay_frequency, 'worker_type' => $userCommission->user_worker_type, 'status' => 1])->first();
                            if ($oldPayroll) {
                                $commissionAmount = UserCommission::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                                $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                                $oldPayroll->commission = $finalAmount;
                                $oldPayroll->save();

                                payrollCalculateNetPay($oldPayroll->id);
                            }
                        }

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $userCommission->id, 'payroll_type' => 'commission', 'type' => $userCommission->schema_type, 'adjustment_type' => $userCommission->schema_type, 'status' => 1])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->pay_period_from = $userCommission->pay_period_from;
                            $payrollAdjustmentDetail->pay_period_to = $userCommission->pay_period_to;
                            $payrollAdjustmentDetail->pay_frequency = $userCommission->pay_frequency;
                            $payrollAdjustmentDetail->user_worker_type = $userCommission->user_worker_type;
                            $payrollAdjustmentDetail->is_mark_paid = 0;
                            $payrollAdjustmentDetail->is_next_payroll = 0;
                            $payrollAdjustmentDetail->is_move_to_recon = 0;
                            $payrollAdjustmentDetail->save();
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $userCommission->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'UserCommissionObserver',
                'old_value' => json_encode($userCommission),
                'error' => json_encode([
                    'user_commission_id' => $userCommission->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the UserCommission "created" event.
     */
    public function created(UserCommission $userCommission)
    {
        try {
            DB::beginTransaction();
            $status = $userCommission?->status ?? 1;
            $settlementType = $userCommission?->settlement_type ?? 'during_m2';
            if ($settlementType != 'reconciliation' && $status != 3) {
                $payroll = Payroll::where(['user_id' => $userCommission->user_id, 'pay_period_from' => $userCommission->pay_period_from, 'pay_period_to' => $userCommission->pay_period_to, 'pay_frequency' => $userCommission->pay_frequency, 'worker_type' => $userCommission->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($userCommission->position_id) ? $userCommission->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($userCommission->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $userCommission->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $userCommission->pay_frequency,
                        'worker_type' => $userCommission->user_worker_type,
                        'pay_period_from' => $userCommission->pay_period_from,
                        'pay_period_to' => $userCommission->pay_period_to,
                        'status' => 1
                    ]);
                }

                $userCommission->payroll_id = $payroll->id;
                $userCommission->saveQuietly(); // Use saveQuietly to prevent observer events

                $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                $payroll->commission = $finalAmount;
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
                $payroll->save();

                payrollCalculateNetPay($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $userCommission->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'UserCommissionObserver',
                'old_value' => json_encode($userCommission),
                'error' => json_encode([
                    'user_commission_id' => $userCommission->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the UserCommission "deleted" event.
     */
    public function deleted(UserCommission $userCommission)
    {
        try {
            DB::beginTransaction();
            $status = $userCommission->status ?? 1;
            $settlementType = $userCommission->settlement_type ?? 'during_m2';
            if ($settlementType != 'reconciliation' && $status != 3) {
                $payroll = Payroll::where(['user_id' => $userCommission->user_id, 'pay_period_from' => $userCommission->pay_period_from, 'pay_period_to' => $userCommission->pay_period_to, 'pay_frequency' => $userCommission->pay_frequency, 'worker_type' => $userCommission->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($userCommission->position_id) ? $userCommission->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($userCommission->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $userCommission->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $userCommission->pay_frequency,
                        'worker_type' => $userCommission->user_worker_type,
                        'pay_period_from' => $userCommission->pay_period_from,
                        'pay_period_to' => $userCommission->pay_period_to,
                        'status' => 1
                    ]);
                }

                $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                $payroll->commission = $finalAmount;
                $payroll->save();
                payrollCalculateNetPay($payroll->id);

                $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $userCommission->id, 'payroll_type' => 'commission', 'type' => $userCommission->schema_type, 'adjustment_type' => $userCommission->schema_type, 'status' => 1])->first();
                if ($payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $userCommission->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'UserCommissionObserver',
                'old_value' => json_encode($userCommission),
                'error' => json_encode([
                    'user_commission_id' => $userCommission->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
