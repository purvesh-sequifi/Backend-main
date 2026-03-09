<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use App\Models\UserOverrides;
use App\Models\UserCommission;
use App\Models\ClawbackSettlement;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class ClawbackSettlementObserver
{
    /**
     * Handle the ClawbackSettlement "updated" event.
     */
    public function updated(ClawbackSettlement $clawBackSettlement)
    {
        // Define fields that should trigger observer processing
        $relevantFields = [
            'clawback_amount',
            'is_mark_paid',
            'is_next_payroll',
            'is_move_to_recon',
            'pay_period_from',
            'pay_period_to',
            'pay_frequency',
            'user_worker_type'
        ];

        // Check if any relevant fields have been updated
        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            // Only check if field exists in the model attributes
            if (array_key_exists($field, $clawBackSettlement->getAttributes()) && $clawBackSettlement->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        // Only proceed if relevant fields have changed
        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $clawBackSettlement->getAttributes()) && $clawBackSettlement->isDirty('pay_period_from') || array_key_exists('pay_period_to', $clawBackSettlement->getAttributes()) && $clawBackSettlement->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        $settlementTypeChange = false;
        if (array_key_exists('clawback_type', $clawBackSettlement->getAttributes()) && $clawBackSettlement->isDirty('clawback_type') || array_key_exists('clawback_type', $clawBackSettlement->getAttributes()) && $clawBackSettlement->isDirty('clawback_type')) {
            $settlementTypeChange = true;
        }

        try {
            DB::beginTransaction();
            $status = $clawBackSettlement->status ?? 1;
            $settlementType = $clawBackSettlement->clawback_type ?? 'next payroll';
            $reconStatus = $clawBackSettlement->recon_status ?? 1;
            if ($settlementType == 'reconciliation' && in_array($reconStatus, [2, 3])) {
                DB::rollBack();
                return;
            }
            if ($settlementType == 'next payroll' && $status == 3) {
                DB::rollBack();
                return;
            }

            if ($settlementTypeChange) {
                $oldSettlementType = $clawBackSettlement->getOriginal('clawback_type') ?? 'next payroll';
                if ($oldSettlementType == 'next payroll') { // FROM next payroll to reconciliation
                    $oldFrom = $clawBackSettlement->getOriginal('pay_period_from');
                    $oldTo = $clawBackSettlement->getOriginal('pay_period_to');

                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $clawBackSettlement->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $clawBackSettlement->pay_frequency, 'worker_type' => $clawBackSettlement->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $oldType = $clawBackSettlement->getOriginal('type') ?? 'commission';
                            if ($oldType == 'commission') {
                                $commissionAmount = UserCommission::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                                $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                                $oldPayroll->commission = $finalAmount;
                                $oldPayroll->save();
                            } else {
                                $overrideAmount = UserOverrides::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                                $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                                $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                                $oldPayroll->override = $finalAmount;
                                $oldPayroll->save();
                            }

                            payrollCalculateNetPay($oldPayroll->id);
                        }
                    }

                    if ($clawBackSettlement->type == 'commission') {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackSettlement->id, 'payroll_type' => 'commission', 'type' => 'clawback', 'adjustment_type' => $clawBackSettlement->schema_type, 'status' => 1])->first();
                    } else {
                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackSettlement->id, 'payroll_type' => 'overrides', 'type' => 'clawback', 'adjustment_type' => $clawBackSettlement->adders_type, 'status' => 1])->first();
                    }
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->delete();
                    }
                } else { // FROM reconciliation to next payroll
                    $payroll = Payroll::where(['user_id' => $clawBackSettlement->user_id, 'pay_period_from' => $clawBackSettlement->pay_period_from, 'pay_period_to' => $clawBackSettlement->pay_period_to, 'pay_frequency' => $clawBackSettlement->pay_frequency, 'worker_type' => $clawBackSettlement->user_worker_type, 'status' => 1])->first();
                    if (!$payroll) {
                        $positionId = isset($clawBackSettlement->position_id) ? $clawBackSettlement->position_id : null;
                        if (!$positionId) {
                            $positionId = User::find($clawBackSettlement->user_id)?->sub_position_id;
                        }
                        $payroll = Payroll::create([
                            'user_id' => $clawBackSettlement->user_id,
                            'position_id' => $positionId,
                            'pay_frequency' => $clawBackSettlement->pay_frequency,
                            'worker_type' => $clawBackSettlement->user_worker_type,
                            'pay_period_from' => $clawBackSettlement->pay_period_from,
                            'pay_period_to' => $clawBackSettlement->pay_period_to,
                            'status' => 1
                        ]);
                    }

                    $clawBackSettlement->payroll_id = $payroll->id;
                    $clawBackSettlement->saveQuietly(); // Use saveQuietly to prevent observer events

                    $type = $clawBackSettlement?->type ?? 'commission';
                    if ($type == 'commission') {
                        $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                        $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                        $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                        $payroll->commission = $finalAmount;
                        $payroll->is_mark_paid = 0;
                        $payroll->is_next_payroll = 0;
                        $payroll->save();
                    } else {
                        $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                        $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                        $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                        $payroll->override = $finalAmount;
                        $payroll->is_mark_paid = 0;
                        $payroll->is_next_payroll = 0;
                        $payroll->save();
                    }

                    payrollCalculateNetPay($payroll->id);
                }
            } else {
                if ($settlementType != 'reconciliation' && $status != 3) {
                    $oldFrom = $clawBackSettlement->getOriginal('pay_period_from');
                    $oldTo = $clawBackSettlement->getOriginal('pay_period_to');

                    $payroll = Payroll::where(['user_id' => $clawBackSettlement->user_id, 'pay_period_from' => $clawBackSettlement->pay_period_from, 'pay_period_to' => $clawBackSettlement->pay_period_to, 'pay_frequency' => $clawBackSettlement->pay_frequency, 'worker_type' => $clawBackSettlement->user_worker_type, 'status' => 1])->first();
                    if (!$payroll) {
                        $positionId = isset($clawBackSettlement->position_id) ? $clawBackSettlement->position_id : null;
                        if (!$positionId) {
                            $positionId = User::find($clawBackSettlement->user_id)?->sub_position_id;
                        }
                        $payroll = Payroll::create([
                            'user_id' => $clawBackSettlement->user_id,
                            'position_id' => $positionId,
                            'pay_frequency' => $clawBackSettlement->pay_frequency,
                            'worker_type' => $clawBackSettlement->user_worker_type,
                            'pay_period_from' => $clawBackSettlement->pay_period_from,
                            'pay_period_to' => $clawBackSettlement->pay_period_to,
                            'status' => 1
                        ]);
                    }

                    $clawBackSettlement->payroll_id = $payroll->id;
                    if ($periodChange) {
                        $clawBackSettlement->is_mark_paid = 0;
                        $clawBackSettlement->is_next_payroll = 0;
                        $clawBackSettlement->is_move_to_recon = 0;
                    }
                    $clawBackSettlement->saveQuietly(); // Use saveQuietly to prevent observer events

                    $type = $clawBackSettlement?->type ?? 'commission';
                    if ($type == 'commission') {
                        $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                        $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                        $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                        $payroll->commission = $finalAmount;
                        if ($periodChange) {
                            $payroll->is_mark_paid = 0;
                            $payroll->is_next_payroll = 0;
                        }
                        $payroll->save();
                    } else {
                        $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                        $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                        $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                        $payroll->override = $finalAmount;
                        if ($periodChange) {
                            $payroll->is_mark_paid = 0;
                            $payroll->is_next_payroll = 0;
                        }
                        $payroll->save();
                    }
                    payrollCalculateNetPay($payroll->id);

                    if ($periodChange) {
                        if ($oldFrom && $oldTo) {
                            $oldPayroll = Payroll::where(['user_id' => $clawBackSettlement->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $clawBackSettlement->pay_frequency, 'worker_type' => $clawBackSettlement->user_worker_type, 'status' => 1])->first();
                            if ($oldPayroll) {
                                $oldType = $clawBackSettlement->getOriginal('type') ?? 'commission';
                                if ($oldType == 'commission') {
                                    $commissionAmount = UserCommission::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                                    $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                                    $oldPayroll->commission = $finalAmount;
                                    $oldPayroll->save();
                                } else {
                                    $overrideAmount = UserOverrides::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                                    $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                                    $oldPayroll->override = $finalAmount;
                                    $oldPayroll->save();
                                }

                                payrollCalculateNetPay($oldPayroll->id);
                            }
                        }

                        if ($clawBackSettlement->type == 'commission') {
                            $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackSettlement->id, 'payroll_type' => 'commission', 'type' => 'clawback', 'adjustment_type' => $clawBackSettlement->schema_type, 'status' => 1])->first();
                        } else {
                            $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackSettlement->id, 'payroll_type' => 'overrides', 'type' => 'clawback', 'adjustment_type' => $clawBackSettlement->adders_type, 'status' => 1])->first();
                        }
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->pay_period_from = $clawBackSettlement->pay_period_from;
                            $payrollAdjustmentDetail->pay_period_to = $clawBackSettlement->pay_period_to;
                            $payrollAdjustmentDetail->pay_frequency = $clawBackSettlement->pay_frequency;
                            $payrollAdjustmentDetail->user_worker_type = $clawBackSettlement->user_worker_type;
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
                'payroll_id' => $clawBackSettlement->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'ClawbackSettlementObserver',
                'old_value' => json_encode($clawBackSettlement),
                'error' => json_encode([
                    'user_clawback_id' => $clawBackSettlement->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the ClawbackSettlement "created" event.
     */
    public function created(ClawbackSettlement $clawBackSettlement)
    {
        try {
            DB::beginTransaction();
            $status = $clawBackSettlement->status ?? 1;
            $settlementType = $clawBackSettlement->clawback_type ?? 'next payroll';
            if ($settlementType != 'reconciliation' && $status != 3) {
                $payroll = Payroll::where(['user_id' => $clawBackSettlement->user_id, 'pay_period_from' => $clawBackSettlement->pay_period_from, 'pay_period_to' => $clawBackSettlement->pay_period_to, 'pay_frequency' => $clawBackSettlement->pay_frequency, 'worker_type' => $clawBackSettlement->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($clawBackSettlement->position_id) ? $clawBackSettlement->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($clawBackSettlement->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $clawBackSettlement->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $clawBackSettlement->pay_frequency,
                        'worker_type' => $clawBackSettlement->user_worker_type,
                        'pay_period_from' => $clawBackSettlement->pay_period_from,
                        'pay_period_to' => $clawBackSettlement->pay_period_to,
                        'status' => 1
                    ]);
                }

                $clawBackSettlement->payroll_id = $payroll->id;
                $clawBackSettlement->saveQuietly(); // Use saveQuietly to prevent observer events

                $type = $clawBackSettlement?->type ?? 'commission';
                if ($type == 'commission') {
                    $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->commission = $finalAmount;
                } else {
                    $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->override = $finalAmount;
                }
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
                $payroll->save();

                payrollCalculateNetPay($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $clawBackSettlement->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'ClawbackSettlementObserver',
                'old_value' => json_encode($clawBackSettlement),
                'error' => json_encode([
                    'user_clawback_id' => $clawBackSettlement->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the ClawbackSettlement "deleted" event.
     */
    public function deleted(ClawbackSettlement $clawBackSettlement)
    {
        try {
            DB::beginTransaction();
            $status = $clawBackSettlement->status ?? 1;
            $settlementType = $clawBackSettlement->clawback_type ?? 'next payroll';
            if ($settlementType != 'reconciliation' && $status != 3) {
                $payroll = Payroll::where(['user_id' => $clawBackSettlement->user_id, 'pay_period_from' => $clawBackSettlement->pay_period_from, 'pay_period_to' => $clawBackSettlement->pay_period_to, 'pay_frequency' => $clawBackSettlement->pay_frequency, 'worker_type' => $clawBackSettlement->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($clawBackSettlement->position_id) ? $clawBackSettlement->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($clawBackSettlement->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $clawBackSettlement->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $clawBackSettlement->pay_frequency,
                        'worker_type' => $clawBackSettlement->user_worker_type,
                        'pay_period_from' => $clawBackSettlement->pay_period_from,
                        'pay_period_to' => $clawBackSettlement->pay_period_to,
                        'status' => 1
                    ]);
                }

                $type = $clawBackSettlement?->type ?? 'commission';
                if ($type == 'commission') {
                    $commissionAmount = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'commission', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($commissionAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->commission = $finalAmount;
                } else {
                    $overrideAmount = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'overrides_settlement_type' => 'during_m2', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                    $clawBackAmount = ClawbackSettlement::where(['payroll_id' => $payroll->id, 'status' => 1, 'type' => 'overrides', 'clawback_type' => 'next payroll', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('clawback_amount');
                    $finalAmount = ($overrideAmount ?? 0) - ($clawBackAmount ?? 0);
                    $payroll->override = $finalAmount;
                }
                $payroll->save();
                payrollCalculateNetPay($payroll->id);

                if ($clawBackSettlement->type == 'commission') {
                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackSettlement->id, 'payroll_type' => 'commission', 'type' => 'clawback', 'adjustment_type' => $clawBackSettlement->schema_type, 'status' => 1])->first();
                } else {
                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $clawBackSettlement->id, 'payroll_type' => 'overrides', 'type' => 'clawback', 'adjustment_type' => $clawBackSettlement->adders_type, 'status' => 1])->first();
                }
                if ($payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $clawBackSettlement->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'ClawbackSettlementObserver',
                'old_value' => json_encode($clawBackSettlement),
                'error' => json_encode([
                    'user_clawback_id' => $clawBackSettlement->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
