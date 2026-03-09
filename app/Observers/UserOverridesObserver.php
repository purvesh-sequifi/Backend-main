<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use App\Models\UserOverrides;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class UserOverridesObserver
{
    /**
     * Handle the UserOverrides "updated" event.
     */
    public function updated(UserOverrides $userOverride)
    {
        // Define fields that should trigger observer processing
        $relevantFields = [
            'amount',
            'is_mark_paid',
            'is_next_payroll',
            'is_move_to_recon',
            'pay_period_from',
            'pay_period_to',
            'pay_frequency',
            'user_worker_type',
            'overrides_settlement_type'
        ];

        // Check if any relevant fields have been updated
        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            // Only check if field exists in the model attributes
            if (array_key_exists($field, $userOverride->getAttributes()) && $userOverride->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        // Only proceed if relevant fields have changed
        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $userOverride->getAttributes()) && $userOverride->isDirty('pay_period_from') || array_key_exists('pay_period_to', $userOverride->getAttributes()) && $userOverride->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        $settlementTypeChange = false;
        if (array_key_exists('overrides_settlement_type', $userOverride->getAttributes()) && $userOverride->isDirty('overrides_settlement_type') || array_key_exists('overrides_settlement_type', $userOverride->getAttributes()) && $userOverride->isDirty('overrides_settlement_type')) {
            $settlementTypeChange = true;
        }

        try {
            DB::beginTransaction();
            $status = $userOverride->status ?? 1;
            $settlementType = $userOverride->overrides_settlement_type ?? 'during_m2';
            $reconStatus = $userOverride->recon_status ?? 1;
            if ($settlementType == 'reconciliation' && in_array($reconStatus, [2, 3])) {
                DB::rollBack();
                return;
            }
            if ($settlementType == 'during_m2' && $status == 3) {
                DB::rollBack();
                return;
            }

            if ($settlementTypeChange) {
                $oldSettlementType = $userOverride->getOriginal('overrides_settlement_type') ?? 'during_m2';
                if ($oldSettlementType == 'during_m2') { // FROM during_m2 to reconciliation
                    $oldFrom = $userOverride->getOriginal('pay_period_from');
                    $oldTo = $userOverride->getOriginal('pay_period_to');

                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $userOverride->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $userOverride->pay_frequency, 'worker_type' => $userOverride->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $overrideAmount = UserOverrides::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                            $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                            $oldPayroll->override = $finalAmount;
                            $oldPayroll->save();

                            payrollCalculateNetPay($oldPayroll->id);
                        }
                    }

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $userOverride->id, 'payroll_type' => 'overrides', 'type' => $userOverride->type, 'adjustment_type' => $userOverride->type, 'status' => 1])->first();
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->delete();
                    }
                } else { // FROM reconciliation to during_m2
                    $payroll = Payroll::where(['user_id' => $userOverride->user_id, 'pay_period_from' => $userOverride->pay_period_from, 'pay_period_to' => $userOverride->pay_period_to, 'pay_frequency' => $userOverride->pay_frequency, 'worker_type' => $userOverride->user_worker_type, 'status' => 1])->first();
                    if (!$payroll) {
                        $positionId = isset($userOverride->position_id) ? $userOverride->position_id : null;
                        if (!$positionId) {
                            $positionId = User::find($userOverride->user_id)?->sub_position_id;
                        }
                        $payroll = Payroll::create([
                            'user_id' => $userOverride->user_id,
                            'position_id' => $positionId,
                            'pay_frequency' => $userOverride->pay_frequency,
                            'worker_type' => $userOverride->user_worker_type,
                            'pay_period_from' => $userOverride->pay_period_from,
                            'pay_period_to' => $userOverride->pay_period_to,
                            'status' => 1
                        ]);
                    }

                    $userOverride->payroll_id = $payroll->id;
                    $userOverride->saveQuietly(); // Use saveQuietly to prevent observer events

                    $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->override = $finalAmount;
                    $payroll->is_mark_paid = 0;
                    $payroll->is_next_payroll = 0;
                    $payroll->save();

                    payrollCalculateNetPay($payroll->id);
                }
            } else {
                if ($settlementType != 'reconciliation' && $status != 3) {
                    $oldFrom = $userOverride->getOriginal('pay_period_from');
                    $oldTo = $userOverride->getOriginal('pay_period_to');

                    $payroll = Payroll::where(['user_id' => $userOverride->user_id, 'pay_period_from' => $userOverride->pay_period_from, 'pay_period_to' => $userOverride->pay_period_to, 'pay_frequency' => $userOverride->pay_frequency, 'worker_type' => $userOverride->user_worker_type, 'status' => 1])->first();
                    if (!$payroll) {
                        $positionId = isset($userOverride->position_id) ? $userOverride->position_id : null;
                        if (!$positionId) {
                            $positionId = User::find($userOverride->user_id)?->sub_position_id;
                        }
                        $payroll = Payroll::create([
                            'user_id' => $userOverride->user_id,
                            'position_id' => $positionId,
                            'pay_frequency' => $userOverride->pay_frequency,
                            'worker_type' => $userOverride->user_worker_type,
                            'pay_period_from' => $userOverride->pay_period_from,
                            'pay_period_to' => $userOverride->pay_period_to,
                            'status' => 1
                        ]);
                    }

                    $userOverride->payroll_id = $payroll->id;
                    if ($periodChange) {
                        $userOverride->is_mark_paid = 0;
                        $userOverride->is_next_payroll = 0;
                        $userOverride->is_move_to_recon = 0;
                    }
                    $userOverride->saveQuietly(); // Use saveQuietly to prevent observer events

                    $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->override = $finalAmount;
                    if ($periodChange) {
                        $payroll->is_mark_paid = 0;
                        $payroll->is_next_payroll = 0;
                    }
                    $payroll->save();
                    payrollCalculateNetPay($payroll->id);

                    if ($periodChange) {
                        if ($oldFrom && $oldTo) {
                            $oldPayroll = Payroll::where(['user_id' => $userOverride->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $userOverride->pay_frequency, 'worker_type' => $userOverride->user_worker_type, 'status' => 1])->first();
                            if ($oldPayroll) {
                                $overrideAmount = UserOverrides::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                                $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                                $oldPayroll->override = $finalAmount;
                                $oldPayroll->save();

                                payrollCalculateNetPay($oldPayroll->id);
                            }
                        }

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $userOverride->id, 'payroll_type' => 'overrides', 'type' => $userOverride->type, 'adjustment_type' => $userOverride->type, 'status' => 1])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->pay_period_from = $userOverride->pay_period_from;
                            $payrollAdjustmentDetail->pay_period_to = $userOverride->pay_period_to;
                            $payrollAdjustmentDetail->pay_frequency = $userOverride->pay_frequency;
                            $payrollAdjustmentDetail->user_worker_type = $userOverride->user_worker_type;
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
                'payroll_id' => $userOverride->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'UserOverridesObserver',
                'old_value' => json_encode($userOverride),
                'error' => json_encode([
                    'user_override_id' => $userOverride->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the UserOverrides "created" event.
     */
    public function created(UserOverrides $userOverride)
    {
        try {
            $status = $userOverride->status ?? 1;
            $settlementType = $userOverride->overrides_settlement_type ?? 'during_m2';
            if ($settlementType != 'reconciliation' && $status != 3) {
                DB::beginTransaction();
                $payroll = Payroll::where(['user_id' => $userOverride->user_id, 'pay_period_from' => $userOverride->pay_period_from, 'pay_period_to' => $userOverride->pay_period_to, 'pay_frequency' => $userOverride->pay_frequency, 'worker_type' => $userOverride->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($userOverride->position_id) ? $userOverride->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($userOverride->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $userOverride->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $userOverride->pay_frequency,
                        'worker_type' => $userOverride->user_worker_type,
                        'pay_period_from' => $userOverride->pay_period_from,
                        'pay_period_to' => $userOverride->pay_period_to,
                        'status' => 1
                    ]);
                }

                $userOverride->payroll_id = $payroll->id;
                $userOverride->saveQuietly(); // Use saveQuietly to prevent observer events

                $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                $payroll->override = $finalAmount;
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
                $payroll->save();

                payrollCalculateNetPay($payroll->id);
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $userOverride->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'UserOverridesObserver',
                'old_value' => json_encode($userOverride),
                'error' => json_encode([
                    'user_override_id' => $userOverride->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the UserOverrides "deleted" event.
     */
    public function deleted(UserOverrides $userOverride)
    {
        try {
            $status = $userOverride->status ?? 1;
            $settlementType = $userOverride->overrides_settlement_type ?? 'during_m2';
            if ($settlementType != 'reconciliation' && $status != 3) {
                DB::beginTransaction();
                $payroll = Payroll::where(['user_id' => $userOverride->user_id, 'pay_period_from' => $userOverride->pay_period_from, 'pay_period_to' => $userOverride->pay_period_to, 'pay_frequency' => $userOverride->pay_frequency, 'worker_type' => $userOverride->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($userOverride->position_id) ? $userOverride->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($userOverride->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $userOverride->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $userOverride->pay_frequency,
                        'worker_type' => $userOverride->user_worker_type,
                        'pay_period_from' => $userOverride->pay_period_from,
                        'pay_period_to' => $userOverride->pay_period_to,
                        'status' => 1
                    ]);
                }

                $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                $payroll->override = $finalAmount;
                $payroll->save();
                payrollCalculateNetPay($payroll->id);

                $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $userOverride->id, 'payroll_type' => 'overrides', 'type' => $userOverride->type, 'adjustment_type' => $userOverride->type, 'status' => 1])->first();
                if ($payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->delete();
                }
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $userOverride->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'UserOverridesObserver',
                'old_value' => json_encode($userOverride),
                'error' => json_encode([
                    'user_override_id' => $userOverride->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
