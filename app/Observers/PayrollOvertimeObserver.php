<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use App\Models\PayrollOvertime;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class PayrollOvertimeObserver
{
    /**
     * Handle the PayrollOvertime "updated" event.
     */
    public function updated(PayrollOvertime $payrollOvertime)
    {
        $relevantFields = [
            'total',
            'is_mark_paid',
            'is_next_payroll',
            'pay_period_from',
            'pay_period_to',
            'pay_frequency',
            'user_worker_type'
        ];

        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $payrollOvertime->getAttributes()) && $payrollOvertime->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $payrollOvertime->getAttributes()) && $payrollOvertime->isDirty('pay_period_from') || array_key_exists('pay_period_to', $payrollOvertime->getAttributes()) && $payrollOvertime->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        try {
            DB::beginTransaction();
            if ($payrollOvertime->status != 3) {
                $oldFrom = $payrollOvertime->getOriginal('pay_period_from');
                $oldTo = $payrollOvertime->getOriginal('pay_period_to');

                $payroll = Payroll::where(['user_id' => $payrollOvertime->user_id, 'pay_period_from' => $payrollOvertime->pay_period_from, 'pay_period_to' => $payrollOvertime->pay_period_to, 'pay_frequency' => $payrollOvertime->pay_frequency, 'worker_type' => $payrollOvertime->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollOvertime->position_id) ? $payrollOvertime->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollOvertime->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollOvertime->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollOvertime->pay_frequency,
                        'worker_type' => $payrollOvertime->user_worker_type,
                        'pay_period_from' => $payrollOvertime->pay_period_from,
                        'pay_period_to' => $payrollOvertime->pay_period_to,
                        'status' => 1
                    ]);
                }
                $payrollOvertime->payroll_id = $payroll->id;
                if ($periodChange) {
                    $payrollOvertime->is_mark_paid = 0;
                    $payrollOvertime->is_next_payroll = 0;
                }
                $payrollOvertime->saveQuietly();

                $payrollOvertimeAmount = PayrollOvertime::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                $payroll->overtime = ($payrollOvertimeAmount ?? 0);
                if ($periodChange) {
                    $payroll->is_mark_paid = 0;
                    $payroll->is_next_payroll = 0;
                }
                $payroll->save();
                payrollCalculateNetPay($payroll->id);

                if ($periodChange) {
                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $payrollOvertime->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $payrollOvertime->pay_frequency, 'worker_type' => $payrollOvertime->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $payrollOvertimeAmount = PayrollOvertime::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                            $oldPayroll->overtime = ($payrollOvertimeAmount ?? 0);
                            $oldPayroll->save();
                            payrollCalculateNetPay($oldPayroll->id);
                        }
                    }

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $payrollOvertime->id, 'payroll_type' => 'overtime', 'status' => 1])->first();
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->pay_period_from = $payrollOvertime->pay_period_from;
                        $payrollAdjustmentDetail->pay_period_to = $payrollOvertime->pay_period_to;
                        $payrollAdjustmentDetail->pay_frequency = $payrollOvertime->pay_frequency;
                        $payrollAdjustmentDetail->user_worker_type = $payrollOvertime->user_worker_type;
                        $payrollAdjustmentDetail->is_mark_paid = 0;
                        $payrollAdjustmentDetail->is_next_payroll = 0;
                        $payrollAdjustmentDetail->is_move_to_recon = 0;
                        $payrollAdjustmentDetail->save();
                    }
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollOvertime->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'PayrollOvertimeObserver',
                'old_value' => json_encode($payrollOvertime),
                'error' => json_encode([
                    'overtime_id' => $payrollOvertime->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollOvertime "created" event.
     */
    public function created(PayrollOvertime $payrollOvertime)
    {
        try {
            DB::beginTransaction();
            if ($payrollOvertime->status != 3) {
                $payroll = Payroll::where(['user_id' => $payrollOvertime->user_id, 'pay_period_from' => $payrollOvertime->pay_period_from, 'pay_period_to' => $payrollOvertime->pay_period_to, 'pay_frequency' => $payrollOvertime->pay_frequency, 'worker_type' => $payrollOvertime->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollOvertime->position_id) ? $payrollOvertime->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollOvertime->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollOvertime->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollOvertime->pay_frequency,
                        'worker_type' => $payrollOvertime->user_worker_type,
                        'pay_period_from' => $payrollOvertime->pay_period_from,
                        'pay_period_to' => $payrollOvertime->pay_period_to,
                        'status' => 1
                    ]);
                }

                $payrollOvertime->payroll_id = $payroll->id;
                $payrollOvertime->saveQuietly();

                $payrollOvertimeAmount = PayrollOvertime::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                $payroll->overtime = ($payrollOvertimeAmount ?? 0);
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
                $payroll->saveQuietly();
                payrollCalculateNetPay($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollOvertime->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'PayrollOvertimeObserver',
                'old_value' => json_encode($payrollOvertime),
                'error' => json_encode([
                    'overtime_id' => $payrollOvertime->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollOvertime "deleted" event.
     */
    public function deleted(PayrollOvertime $payrollOvertime)
    {
        try {
            DB::beginTransaction();
            if ($payrollOvertime->status != 3) {
                $payroll = Payroll::where(['user_id' => $payrollOvertime->user_id, 'pay_period_from' => $payrollOvertime->pay_period_from, 'pay_period_to' => $payrollOvertime->pay_period_to, 'pay_frequency' => $payrollOvertime->pay_frequency, 'worker_type' => $payrollOvertime->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollOvertime->position_id) ? $payrollOvertime->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollOvertime->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollOvertime->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollOvertime->pay_frequency,
                        'worker_type' => $payrollOvertime->user_worker_type,
                        'pay_period_from' => $payrollOvertime->pay_period_from,
                        'pay_period_to' => $payrollOvertime->pay_period_to,
                        'status' => 1
                    ]);
                }

                $payrollOvertimeAmount = PayrollOvertime::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                $payroll->overtime = ($payrollOvertimeAmount ?? 0);
                $payroll->saveQuietly();
                payrollCalculateNetPay($payroll->id);

                $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $payrollOvertime->id, 'payroll_type' => 'overtime', 'status' => 1])->first();
                if ($payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollOvertime->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'PayrollOvertimeObserver',
                'old_value' => json_encode($payrollOvertime),
                'error' => json_encode([
                    'overtime_id' => $payrollOvertime->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
