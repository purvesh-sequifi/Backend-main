<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalsAndRequest;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class PayrollAdjustmentDetailObserver
{
    /**
     * Handle the PayrollAdjustmentDetail "updated" event.
     */
    public function updated(PayrollAdjustmentDetail $payrollAdjustmentDetail)
    {
        $relevantFields = [
            'amount',
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
            if (array_key_exists($field, $payrollAdjustmentDetail->getAttributes()) && $payrollAdjustmentDetail->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        // Only proceed if relevant fields have changed
        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $payrollAdjustmentDetail->getAttributes()) && $payrollAdjustmentDetail->isDirty('pay_period_from') || array_key_exists('pay_period_to', $payrollAdjustmentDetail->getAttributes()) && $payrollAdjustmentDetail->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        try {
            DB::beginTransaction();
            if ($payrollAdjustmentDetail->status != 3) {
                $oldFrom = $payrollAdjustmentDetail->getOriginal('pay_period_from');
                $oldTo = $payrollAdjustmentDetail->getOriginal('pay_period_to');

                $payroll = Payroll::where(['user_id' => $payrollAdjustmentDetail->user_id, 'pay_period_from' => $payrollAdjustmentDetail->pay_period_from, 'pay_period_to' => $payrollAdjustmentDetail->pay_period_to, 'pay_frequency' => $payrollAdjustmentDetail->pay_frequency, 'worker_type' => $payrollAdjustmentDetail->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollAdjustmentDetail->position_id) ? $payrollAdjustmentDetail->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollAdjustmentDetail->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollAdjustmentDetail->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollAdjustmentDetail->pay_frequency,
                        'worker_type' => $payrollAdjustmentDetail->user_worker_type,
                        'pay_period_from' => $payrollAdjustmentDetail->pay_period_from,
                        'pay_period_to' => $payrollAdjustmentDetail->pay_period_to,
                        'status' => 1
                    ]);
                }
                $payrollAdjustmentDetail->payroll_id = $payroll->id;
                if ($periodChange) {
                    $payrollAdjustmentDetail->is_mark_paid = 0;
                    $payrollAdjustmentDetail->is_next_payroll = 0;
                    $payrollAdjustmentDetail->is_move_to_recon = 0;
                }
                $payrollAdjustmentDetail->saveQuietly();

                $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                if ($periodChange) {
                    $payroll->is_mark_paid = 0;
                    $payroll->is_next_payroll = 0;
                }
                $payroll->save();
                payrollCalculateNetPay($payroll->id);

                if ($periodChange) {
                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $payrollAdjustmentDetail->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $payrollAdjustmentDetail->pay_frequency, 'worker_type' => $payrollAdjustmentDetail->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $oldPayroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                            $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $oldPayroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $oldPayroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                            $oldPayroll->save();
                            payrollCalculateNetPay($oldPayroll->id);
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollAdjustmentDetail->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'PayrollAdjustmentDetailObserver',
                'old_value' => json_encode($payrollAdjustmentDetail),
                'error' => json_encode([
                    'user_adjustment_id' => $payrollAdjustmentDetail->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollAdjustmentDetail "created" event.
     */
    public function created(PayrollAdjustmentDetail $payrollAdjustmentDetail)
    {
        try {
            DB::beginTransaction();
            if ($payrollAdjustmentDetail->status != 3) {
                $payroll = Payroll::where(['user_id' => $payrollAdjustmentDetail->user_id, 'pay_period_from' => $payrollAdjustmentDetail->pay_period_from, 'pay_period_to' => $payrollAdjustmentDetail->pay_period_to, 'pay_frequency' => $payrollAdjustmentDetail->pay_frequency, 'worker_type' => $payrollAdjustmentDetail->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollAdjustmentDetail->position_id) ? $payrollAdjustmentDetail->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollAdjustmentDetail->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollAdjustmentDetail->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollAdjustmentDetail->pay_frequency,
                        'worker_type' => $payrollAdjustmentDetail->user_worker_type,
                        'pay_period_from' => $payrollAdjustmentDetail->pay_period_from,
                        'pay_period_to' => $payrollAdjustmentDetail->pay_period_to,
                        'status' => 1
                    ]);
                }

                $payrollAdjustmentDetail->payroll_id = $payroll->id;
                $payrollAdjustmentDetail->saveQuietly();

                $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payroll->adjustment = ($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0);
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
                $payroll->saveQuietly();

                payrollCalculateNetPay($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollAdjustmentDetail->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'PayrollAdjustmentDetailObserver',
                'old_value' => json_encode($payrollAdjustmentDetail),
                'error' => json_encode([
                    'user_adjustment_id' => $payrollAdjustmentDetail->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollAdjustmentDetail "deleted" event.
     */
    public function deleted(PayrollAdjustmentDetail $payrollAdjustmentDetail)
    {
        try {
            DB::beginTransaction();
            if ($payrollAdjustmentDetail->status != 3) {
                $payroll = Payroll::where(['user_id' => $payrollAdjustmentDetail->user_id, 'pay_period_from' => $payrollAdjustmentDetail->pay_period_from, 'pay_period_to' => $payrollAdjustmentDetail->pay_period_to, 'pay_frequency' => $payrollAdjustmentDetail->pay_frequency, 'worker_type' => $payrollAdjustmentDetail->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollAdjustmentDetail->position_id) ? $payrollAdjustmentDetail->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollAdjustmentDetail->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollAdjustmentDetail->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollAdjustmentDetail->pay_frequency,
                        'worker_type' => $payrollAdjustmentDetail->user_worker_type,
                        'pay_period_from' => $payrollAdjustmentDetail->pay_period_from,
                        'pay_period_to' => $payrollAdjustmentDetail->pay_period_to,
                        'status' => 1
                    ]);
                }

                $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                $payroll->saveQuietly();

                payrollCalculateNetPay($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollAdjustmentDetail->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'PayrollAdjustmentDetailObserver',
                'old_value' => json_encode($payrollAdjustmentDetail),
                'error' => json_encode([
                    'user_adjustment_id' => $payrollAdjustmentDetail->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
