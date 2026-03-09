<?php

namespace App\Observers;

use App\Models\Payroll;
use App\Models\PayrollDeductions;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;
use App\Models\PayrollAdjustmentDetail;

class PayrollDeductionsObserver
{
    /**
     * Handle the PayrollDeductions "updated" event.
     */
    public function updated(PayrollDeductions $payrollDeductions)
    {
        $relevantFields = [
            'total',
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
            if (array_key_exists($field, $payrollDeductions->getAttributes()) && $payrollDeductions->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        // Only proceed if relevant fields have changed
        if (!$hasRelevantChanges) {
            return;
        }

        try {
            DB::beginTransaction();
            if ($payrollDeductions->status != 3) {
                $payroll = Payroll::find($payrollDeductions->payroll_id);
                if ($payroll) {
                    $payrollDeduction = PayrollDeductions::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');
                    $payroll->deduction = round($payrollDeduction, 2);
                    $payroll->saveQuietly();
                    payrollCalculateNetPay($payroll->id);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollDeductions->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'PayrollDeductionsObserver',
                'old_value' => json_encode($payrollDeductions),
                'error' => json_encode([
                    'user_deduction_id' => $payrollDeductions->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollDeductions "created" event.
     */
    public function created(PayrollDeductions $payrollDeductions)
    {
        try {
            DB::beginTransaction();
            if ($payrollDeductions->status != 3) {
                $payroll = Payroll::find($payrollDeductions->payroll_id);
                if ($payroll) {
                    $payrollDeduction = PayrollDeductions::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');
                    $payroll->deduction = round($payrollDeduction, 2);
                    $payroll->saveQuietly();
                    payrollCalculateNetPay($payroll->id);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollDeductions->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'PayrollDeductionsObserver',
                'old_value' => json_encode($payrollDeductions),
                'error' => json_encode([
                    'user_deduction_id' => $payrollDeductions->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the PayrollDeductions "deleted" event.
     */
    public function deleted(PayrollDeductions $payrollDeductions)
    {
        try {
            DB::beginTransaction();
            if ($payrollDeductions->status != 3) {
                $payroll = Payroll::find($payrollDeductions->payroll_id);
                if ($payroll) {
                    $payrollDeduction = PayrollDeductions::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0])->sum('total');
                    $payroll->deduction = round($payrollDeduction, 2);
                    $payroll->saveQuietly();
                    payrollCalculateNetPay($payroll->id);
                }

                $payrollAdjustmentDetail = PayrollAdjustmentDetail::where(['payroll_type_id' => $payrollDeductions->id, 'status' => 1])->first();
                if ($payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->delete();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $payrollDeductions->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'PayrollDeductionsObserver',
                'old_value' => json_encode($payrollDeductions),
                'error' => json_encode([
                    'user_deduction_id' => $payrollDeductions->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
