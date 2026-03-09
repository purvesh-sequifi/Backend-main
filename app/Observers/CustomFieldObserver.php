<?php

namespace App\Observers;

use App\Models\Payroll;
use App\Models\CustomField;
use Illuminate\Support\Facades\DB;
use App\Models\PayrollObserversLog;

class CustomFieldObserver
{
    /**
     * Handle the CustomField "updated" event.
     */
    public function updated(CustomField $customField)
    {
        $relevantFields = [
            'value',
            'payroll_id',
            'is_mark_paid',
            'is_next_payroll',
            'pay_frequency',
            'user_worker_type'
        ];

        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $customField->getAttributes()) && $customField->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        if (!$hasRelevantChanges) {
            return;
        }

        $payrollChange = false;
        if (array_key_exists('payroll_id', $customField->getAttributes()) && $customField->isDirty('payroll_id') && $customField->getOriginal('value') != $customField->value) {
            $payrollChange = true;
        }

        try {
            DB::beginTransaction();
            $payroll = Payroll::where(['id' => $customField->payroll_id])->first();
            if (!$payroll) {
                DB::rollBack();
                return;
            }

            $customFieldSum = CustomField::where(['payroll_id' => $payroll->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('value');
            $payroll->custom_payment = ($customFieldSum ?? 0);
            if ($payrollChange) {
                $payroll->is_mark_paid = 0;
                $payroll->is_next_payroll = 0;
            }
            $payroll->saveQuietly();
            payrollCalculateNetPay($payroll->id);

            if ($payrollChange) {
                $oldPayrollId = $customField->getOriginal('payroll_id');
                $oldPayroll = Payroll::where(['id' => $oldPayrollId, 'status' => 1])->first();
                if ($oldPayroll) {
                    $oldCustomFieldSum = CustomField::where(['payroll_id' => $oldPayroll->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('value');
                    $oldPayroll->custom_payment = ($oldCustomFieldSum ?? 0);
                    $oldPayroll->saveQuietly();
                    payrollCalculateNetPay($oldPayroll->id);
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $customField->payroll_id ?? 0,
                'action' => 'updated',
                'observer' => 'CustomFieldObserver',
                'old_value' => json_encode($customField),
                'error' => json_encode([
                    'custom_field_id' => $customField->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the CustomField "created" event.
     */
    public function created(CustomField $customField)
    {
        try {
            DB::beginTransaction();
            $payroll = Payroll::where(['id' => $customField->payroll_id])->first();
            if (!$payroll) {
                DB::rollBack();
                return;
            }
            $customFieldSum = CustomField::where(['payroll_id' => $payroll->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('value');
            $payroll->custom_payment = ($customFieldSum ?? 0);
            $payroll->is_mark_paid = 0;
            $payroll->is_next_payroll = 0;
            $payroll->saveQuietly();

            payrollCalculateNetPay($payroll->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $customField->payroll_id ?? 0,
                'action' => 'created',
                'observer' => 'CustomFieldObserver',
                'old_value' => json_encode($customField),
                'error' => json_encode([
                    'custom_field_id' => $customField->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }

    /**
     * Handle the CustomField "deleted" event.
     */
    public function deleted(CustomField $customField)
    {
        try {
            DB::beginTransaction();
            $payroll = Payroll::where(['id' => $customField->payroll_id])->first();
            if (!$payroll) {
                DB::rollBack();
                return;
            }

            $customFieldSum = CustomField::where(['payroll_id' => $payroll->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('value');
            $payroll->custom_payment = ($customFieldSum ?? 0);
            $payroll->saveQuietly();

            payrollCalculateNetPay($payroll->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            PayrollObserversLog::create([
                'payroll_id' => $customField->payroll_id ?? 0,
                'action' => 'deleted',
                'observer' => 'CustomFieldObserver',
                'old_value' => json_encode($customField),
                'error' => json_encode([
                    'custom_field_id' => $customField->id ?? 0,
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ])
            ]);
        }
    }
}
