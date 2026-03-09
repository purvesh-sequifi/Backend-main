<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
use App\Models\ApprovalsAndRequest;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class ApprovalsAndRequestObserver
{
    const REIMBURSEMENT = [2];
    const FINE_AND_FEE = [5];
    const IGNORE = [7, 8, 9];

    /**
     * Handle the ApprovalsAndRequest "updated" event.
     */
    public function updated(ApprovalsAndRequest $approvalsAndRequest)
    {
        $relevantFields = [
            'amount',
            'status',
            'is_mark_paid',
            'is_next_payroll',
            'pay_period_from',
            'pay_period_to',
            'pay_frequency',
            'user_worker_type'
        ];

        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $approvalsAndRequest->getAttributes()) && $approvalsAndRequest->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $approvalsAndRequest->getAttributes()) && $approvalsAndRequest->isDirty('pay_period_from') || array_key_exists('pay_period_to', $approvalsAndRequest->getAttributes()) && $approvalsAndRequest->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        try {
            DB::beginTransaction();
            if ($approvalsAndRequest->status != 3) {
                $oldFrom = $approvalsAndRequest->getOriginal('pay_period_from');
                $oldTo = $approvalsAndRequest->getOriginal('pay_period_to');

                $payroll = Payroll::where(['user_id' => $approvalsAndRequest->user_id, 'pay_period_from' => $approvalsAndRequest->pay_period_from, 'pay_period_to' => $approvalsAndRequest->pay_period_to, 'pay_frequency' => $approvalsAndRequest->pay_frequency, 'worker_type' => $approvalsAndRequest->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($approvalsAndRequest->position_id) ? $approvalsAndRequest->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($approvalsAndRequest->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $approvalsAndRequest->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $approvalsAndRequest->pay_frequency,
                        'worker_type' => $approvalsAndRequest->user_worker_type,
                        'pay_period_from' => $approvalsAndRequest->pay_period_from,
                        'pay_period_to' => $approvalsAndRequest->pay_period_to,
                        'status' => 1
                    ]);
                }
                $approvalsAndRequest->payroll_id = $payroll->id;
                if ($periodChange) {
                    $approvalsAndRequest->is_mark_paid = 0;
                    $approvalsAndRequest->is_next_payroll = 0;
                }
                $approvalsAndRequest->saveQuietly();

                $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $reimbursementAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 2, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payroll->reimbursement = ($reimbursementAmount ?? 0);
                $payroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                if ($periodChange) {
                    $payroll->is_mark_paid = 0;
                    $payroll->is_next_payroll = 0;
                }
                $payroll->save();
                payrollCalculateNetPay($payroll->id);
                calculateSubtractAmount($payroll->id);

                if ($periodChange) {
                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $approvalsAndRequest->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $approvalsAndRequest->pay_frequency, 'worker_type' => $approvalsAndRequest->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $oldPayroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                            $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $oldPayroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $reimbursementAmount = ApprovalsAndRequest::where(['payroll_id' => $oldPayroll->id, 'adjustment_type_id' => 2, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                            $oldPayroll->reimbursement = ($reimbursementAmount ?? 0);
                            $oldPayroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                            $oldPayroll->save();
                            payrollCalculateNetPay($oldPayroll->id);
                            calculateSubtractAmount($oldPayroll->id);
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $approvalsAndRequest->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'ApprovalsAndRequestObserver',
                'old_value' => json_encode($approvalsAndRequest),
                'error' => json_encode([
                    'approval_id' => $approvalsAndRequest->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the ApprovalsAndRequest "created" event.
     */
    public function created(ApprovalsAndRequest $approvalsAndRequest)
    {
        try {
            DB::beginTransaction();
            if ($approvalsAndRequest->status == 'Accept' && !in_array($approvalsAndRequest->adjustment_type_id, self::IGNORE) && $approvalsAndRequest->pay_period_from && $approvalsAndRequest->pay_period_to && $approvalsAndRequest->pay_frequency && $approvalsAndRequest->user_worker_type) {
                $payroll = Payroll::where(['user_id' => $approvalsAndRequest->user_id, 'pay_period_from' => $approvalsAndRequest->pay_period_from, 'pay_period_to' => $approvalsAndRequest->pay_period_to, 'pay_frequency' => $approvalsAndRequest->pay_frequency, 'worker_type' => $approvalsAndRequest->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($approvalsAndRequest->position_id) ? $approvalsAndRequest->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($approvalsAndRequest->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $approvalsAndRequest->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $approvalsAndRequest->pay_frequency,
                        'worker_type' => $approvalsAndRequest->user_worker_type,
                        'pay_period_from' => $approvalsAndRequest->pay_period_from,
                        'pay_period_to' => $approvalsAndRequest->pay_period_to,
                        'status' => 1
                    ]);
                }

                $approvalsAndRequest->payroll_id = $payroll->id;
                $approvalsAndRequest->saveQuietly();

                $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $reimbursementAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 2, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payroll->reimbursement = ($reimbursementAmount ?? 0);
                $payroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                $payroll->saveQuietly();
                payrollCalculateNetPay($payroll->id);
                calculateSubtractAmount($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $approvalsAndRequest->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'ApprovalsAndRequestObserver',
                'old_value' => json_encode($approvalsAndRequest),
                'error' => json_encode([
                    'approval_id' => $approvalsAndRequest->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the ApprovalsAndRequest "deleted" event.
     */
    public function deleted(ApprovalsAndRequest $approvalsAndRequest)
    {
        try {
            DB::beginTransaction();
            if ($approvalsAndRequest->status == 'Accept' && !in_array($approvalsAndRequest->adjustment_type_id, self::IGNORE)) {
                $payroll = Payroll::where(['user_id' => $approvalsAndRequest->user_id, 'pay_period_from' => $approvalsAndRequest->pay_period_from, 'pay_period_to' => $approvalsAndRequest->pay_period_to, 'pay_frequency' => $approvalsAndRequest->pay_frequency, 'worker_type' => $approvalsAndRequest->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($approvalsAndRequest->position_id) ? $approvalsAndRequest->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($approvalsAndRequest->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $approvalsAndRequest->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $approvalsAndRequest->pay_frequency,
                        'worker_type' => $approvalsAndRequest->user_worker_type,
                        'pay_period_from' => $approvalsAndRequest->pay_period_from,
                        'pay_period_to' => $approvalsAndRequest->pay_period_to,
                        'status' => 1
                    ]);
                }

                $requestAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount');
                $fineAndFeeAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 5, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payrollAdjustmentDetailAmount = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $reimbursementAmount = ApprovalsAndRequest::where(['payroll_id' => $payroll->id, 'adjustment_type_id' => 2, 'status' => 'Accept', 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $payroll->reimbursement = ($reimbursementAmount ?? 0);
                $payroll->adjustment = round(($payrollAdjustmentDetailAmount ?? 0) + ($requestAmount ?? 0) - ($fineAndFeeAmount ?? 0), 2);
                $payroll->saveQuietly();
                payrollCalculateNetPay($payroll->id);
                calculateSubtractAmount($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $approvalsAndRequest->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'ApprovalsAndRequestObserver',
                'old_value' => json_encode($approvalsAndRequest),
                'error' => json_encode([
                    'approval_id' => $approvalsAndRequest->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
