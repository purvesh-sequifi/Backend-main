<?php

namespace Database\Seeders;

use App\Models\CustomField;
use App\Models\CustomFieldHistory;
use App\Models\PayrollHistory;
use Illuminate\Database\Seeder;

class SaveCustomFieldDataToCustomFieldHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payrollHistories = PayrollHistory::get();
        foreach ($payrollHistories as $key => $payrollHistory) {
            $customFields = CustomField::where(['payroll_id' => $payrollHistory->payroll_id, 'user_id' => $payrollHistory->user_id])->get();
            foreach ($customFields as $key => $customField) {
                $customFieldHistory = CustomFieldHistory::where(['payroll_id' => $customField->payroll_id, 'user_id' => $customField->user_id, 'column_id' => $customField->column_id])->first();
                if ($customFieldHistory == null) {
                    $customFieldHistory = new CustomFieldHistory;
                }

                $customFieldHistory->user_id = $customField->user_id;
                $customFieldHistory->payroll_id = $customField->payroll_id;
                $customFieldHistory->column_id = $customField->column_id;
                $customFieldHistory->value = $customField->value;
                $customFieldHistory->comment = $customField->comment;
                $customFieldHistory->approved_by = $customField->approved_by ?? 0;
                $customFieldHistory->is_mark_paid = $customField->is_mark_paid;
                $customFieldHistory->is_next_payroll = $customField->is_next_payroll;
                $customFieldHistory->pay_period_from = $customField->pay_period_from;
                $customFieldHistory->pay_period_to = $customField->pay_period_to;
                if ($customFieldHistory->save()) {
                    $customField = CustomField::find($customField->id);
                    $customField->delete();
                }
            }
        }
    }
}
