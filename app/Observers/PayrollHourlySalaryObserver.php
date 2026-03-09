<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollHourlySalary;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class PayrollHourlySalaryObserver
{
    /**
     * Handle the PayrollHourlySalary "updated" event.
     */
    public function updated(PayrollHourlySalary $payrollHourlySalary)
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
            if (array_key_exists($field, $payrollHourlySalary->getAttributes()) && $payrollHourlySalary->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        if (!$hasRelevantChanges) {
            return;
        }

        $periodChange = false;
        if (array_key_exists('pay_period_from', $payrollHourlySalary->getAttributes()) && $payrollHourlySalary->isDirty('pay_period_from') || array_key_exists('pay_period_to', $payrollHourlySalary->getAttributes()) && $payrollHourlySalary->isDirty('pay_period_to')) {
            $periodChange = true;
        }

        try {
            DB::beginTransaction();
            if ($payrollHourlySalary->status != 3) {
                $oldFrom = $payrollHourlySalary->getOriginal('pay_period_from');
                $oldTo = $payrollHourlySalary->getOriginal('pay_period_to');

                $payroll = Payroll::where(['user_id' => $payrollHourlySalary->user_id, 'pay_period_from' => $payrollHourlySalary->pay_period_from, 'pay_period_to' => $payrollHourlySalary->pay_period_to, 'pay_frequency' => $payrollHourlySalary->pay_frequency, 'worker_type' => $payrollHourlySalary->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollHourlySalary->position_id) ? $payrollHourlySalary->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollHourlySalary->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollHourlySalary->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollHourlySalary->pay_frequency,
                        'worker_type' => $payrollHourlySalary->user_worker_type,
                        'pay_period_from' => $payrollHourlySalary->pay_period_from,
                        'pay_period_to' => $payrollHourlySalary->pay_period_to,
                        'status' => 1
                    ]);
                }
                $payrollHourlySalary->payroll_id = $payroll->id;
                if ($periodChange) {
                    $payrollHourlySalary->is_mark_paid = 0;
                    $payrollHourlySalary->is_next_payroll = 0;
                }
                $payrollHourlySalary->saveQuietly();

                $payrollHourlySalaryAmount = PayrollHourlySalary::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                $payroll->hourly_salary = ($payrollHourlySalaryAmount ?? 0);
                if ($periodChange) {
                    $payroll->is_mark_paid = 0;
                    $payroll->is_next_payroll = 0;
                }
                $payroll->save();
                payrollCalculateNetPay($payroll->id);

                if ($periodChange) {
                    if ($oldFrom && $oldTo) {
                        $oldPayroll = Payroll::where(['user_id' => $payrollHourlySalary->user_id, 'pay_period_from' => $oldFrom, 'pay_period_to' => $oldTo, 'pay_frequency' => $payrollHourlySalary->pay_frequency, 'worker_type' => $payrollHourlySalary->user_worker_type, 'status' => 1])->first();
                        if ($oldPayroll) {
                            $payrollHourlySalaryAmount = PayrollHourlySalary::where(['payroll_id' => $oldPayroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                            $oldPayroll->hourly_salary = ($payrollHourlySalaryAmount ?? 0);
                            $oldPayroll->save();
                            payrollCalculateNetPay($oldPayroll->id);
                        }
                    }

                    $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $payrollHourlySalary->id, 'payroll_type' => 'hourlysalary', 'status' => 1])->first();
                    if ($payrollAdjustmentDetail) {
                        $payrollAdjustmentDetail->pay_period_from = $payrollHourlySalary->pay_period_from;
                        $payrollAdjustmentDetail->pay_period_to = $payrollHourlySalary->pay_period_to;
                        $payrollAdjustmentDetail->pay_frequency = $payrollHourlySalary->pay_frequency;
                        $payrollAdjustmentDetail->user_worker_type = $payrollHourlySalary->user_worker_type;
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
                'payroll_id' => $payrollHourlySalary->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'PayrollHourlySalaryObserver',
                'old_value' => json_encode($payrollHourlySalary),
                'error' => json_encode([
                    'hourly_salary_id' => $payrollHourlySalary->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollHourlySalary "created" event.
     */
    public function created(PayrollHourlySalary $payrollHourlySalary)
    {
        try {
            DB::beginTransaction();
            if ($payrollHourlySalary->status != 3) {
                $payroll = Payroll::where(['user_id' => $payrollHourlySalary->user_id, 'pay_period_from' => $payrollHourlySalary->pay_period_from, 'pay_period_to' => $payrollHourlySalary->pay_period_to, 'pay_frequency' => $payrollHourlySalary->pay_frequency, 'worker_type' => $payrollHourlySalary->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollHourlySalary->position_id) ? $payrollHourlySalary->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollHourlySalary->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollHourlySalary->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollHourlySalary->pay_frequency,
                        'worker_type' => $payrollHourlySalary->user_worker_type,
                        'pay_period_from' => $payrollHourlySalary->pay_period_from,
                        'pay_period_to' => $payrollHourlySalary->pay_period_to,
                        'status' => 1
                    ]);
                }

                $payrollHourlySalary->payroll_id = $payroll->id;
                $payrollHourlySalary->saveQuietly();

                $payrollHourlySalaryAmount = PayrollHourlySalary::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                $payroll->hourly_salary = ($payrollHourlySalaryAmount ?? 0);
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
                $payroll->saveQuietly();
                payrollCalculateNetPay($payroll->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollHourlySalary->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'PayrollHourlySalaryObserver',
                'old_value' => json_encode($payrollHourlySalary),
                'error' => json_encode([
                    'hourly_salary_id' => $payrollHourlySalary->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollHourlySalary "deleted" event.
     */
    public function deleted(PayrollHourlySalary $payrollHourlySalary)
    {
        try {
            DB::beginTransaction();
            if ($payrollHourlySalary->status != 3) {
                $payroll = Payroll::where(['user_id' => $payrollHourlySalary->user_id, 'pay_period_from' => $payrollHourlySalary->pay_period_from, 'pay_period_to' => $payrollHourlySalary->pay_period_to, 'pay_frequency' => $payrollHourlySalary->pay_frequency, 'worker_type' => $payrollHourlySalary->user_worker_type, 'status' => 1])->first();
                if (!$payroll) {
                    $positionId = isset($payrollHourlySalary->position_id) ? $payrollHourlySalary->position_id : null;
                    if (!$positionId) {
                        $positionId = User::find($payrollHourlySalary->user_id)?->sub_position_id;
                    }
                    $payroll = Payroll::create([
                        'user_id' => $payrollHourlySalary->user_id,
                        'position_id' => $positionId,
                        'pay_frequency' => $payrollHourlySalary->pay_frequency,
                        'worker_type' => $payrollHourlySalary->user_worker_type,
                        'pay_period_from' => $payrollHourlySalary->pay_period_from,
                        'pay_period_to' => $payrollHourlySalary->pay_period_to,
                        'status' => 1
                    ]);
                }

                $payrollHourlySalaryAmount = PayrollHourlySalary::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->sum('total');
                $payroll->hourly_salary = ($payrollHourlySalaryAmount ?? 0);
                $payroll->saveQuietly();
                payrollCalculateNetPay($payroll->id);

                $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $payrollHourlySalary->id, 'payroll_type' => 'hourlysalary', 'status' => 1])->first();
                if ($payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollHourlySalary->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'PayrollHourlySalaryObserver',
                'old_value' => json_encode($payrollHourlySalary),
                'error' => json_encode([
                    'hourly_salary_id' => $payrollHourlySalary->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
