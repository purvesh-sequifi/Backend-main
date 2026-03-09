<?php

use App\Models\User;
use App\Models\Payroll;
use App\Models\CustomField;
use App\Models\UserSchedule;
use Illuminate\Http\Request;
use App\Models\PayrollSsetup;
use App\Models\FrequencyType;
use App\Models\UserOverrides;
use App\Models\UserAttendance;
use App\Models\UserCommission;
use Illuminate\Support\Carbon;
use App\Models\PayrollHistory;
use App\Models\PayrollOvertime;
use App\Models\UserWagesHistory;
use App\Models\PayrollDeductions;
use App\Models\DailyPayFrequency;
use App\Models\ClawbackSettlement;
use App\Models\UserScheduleDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\WeeklyPayFrequency;
use App\Models\CustomFieldHistory;
use App\Models\ApprovalsAndRequest;
use App\Models\MonthlyPayFrequency;
use App\Models\PayrollHourlySalary;
use App\Models\ReconOverrideHistory;
use App\Models\UserAttendanceDetail;
use Illuminate\Support\Facades\Auth;
use App\Models\AdditionalPayFrequency;
use App\Models\AdvancePaymentSetting;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\GroupPermissions;
use App\Models\LegacyWeeklySheet;
use App\Models\ReconCommissionHistory;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollCommon;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollHourlySalaryLock;
use App\Models\PayrollOvertimeLock;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionDeductionSetting;
use App\Models\PositionReconciliations;
use App\Models\PositionsDeductionLimit;
use App\Models\ReconAdjustment;
use App\Models\ReconAdjustmentLock;
use Illuminate\Support\Facades\Validator;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\ReconciliationFinalizeHistoryLock;
use App\Models\ReconClawbackHistory;
use App\Models\ReconClawbackHistoryLock;
use App\Models\ReconCommissionHistoryLock;
use App\Models\ReconDeductionHistory;
use App\Models\ReconDeductionHistoryLock;
use App\Models\ReconOverrideHistoryLock;
use App\Models\SchedulingApprovalSetting;
use App\Models\UserCommissionLock;
use App\Models\UserDeductionHistory;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationCommissionLock;
use App\Http\Controllers\API\Schedule\ScheduleController;
use App\Models\CompanyProfile;
use App\Models\OneTimePayments;
use App\Models\PositionWage;
use App\Models\UserOrganizationHistory;
use App\Models\W2PayrollTaxDeduction;
use App\Traits\EmailNotificationTrait;

if (!function_exists('payrollRemoveDuplicateData')) {
    function payrollRemoveDuplicateData(Request $request)
    {
        $payFrequency = $request->pay_frequency;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $workerType = strtolower($request->worker_type);

        // ALWAYS REMOVE LATEST PAYROLL AND NOT THE OLDEST
        $duplicates = DB::table('payrolls as p1')->select('p1.id')
            ->when($payFrequency == FrequencyType::DAILY_PAY_ID, function ($query) use ($payPeriodFrom, $payPeriodTo, $payFrequency, $workerType) {
                $query->join('payrolls as p2', function ($join) {
                    $join->on('p1.user_id', 'p2.user_id')
                        ->on('p1.id', '>', 'p2.id');
                })->whereBetween('p1.pay_period_from', [$payPeriodFrom, $payPeriodTo])
                    ->whereBetween('p1.pay_period_to', [$payPeriodFrom, $payPeriodTo])
                    ->whereColumn('p1.pay_period_from', 'p1.pay_period_to')
                    ->where(['p1.pay_frequency' => $payFrequency, 'p1.worker_type' => $workerType]);
            }, function ($query) use ($payPeriodFrom, $payPeriodTo, $payFrequency, $workerType) {
                $query->join('payrolls as p2', function ($join) {
                    $join->on('p1.pay_period_from', 'p2.pay_period_from')
                        ->on('p1.user_id', 'p2.user_id')
                        ->on('p1.id', '>', 'p2.id');
                })->where([
                    'p1.pay_period_from' => $payPeriodFrom,
                    'p1.pay_period_to' => $payPeriodTo,
                    'p1.pay_frequency' => $payFrequency,
                    'p1.worker_type' => $workerType
                ]);
            })->get()->pluck('id');

        // REMOVE DUPLICATE RECORD BY ID
        if (sizeof($duplicates) != 0) {
            $payrollUsers = Payroll::whereIn('id', $duplicates)->pluck('user_id')->toArray();
            Payroll::whereIn('id', $duplicates)->delete();
            CustomField::whereIn('payroll_id', $duplicates)->delete();

            $param = [
                "pay_frequency" => $payFrequency,
                "worker_type" => $workerType,
                "pay_period_from" => $payPeriodFrom,
                "pay_period_to" => $payPeriodTo
            ];
            $payrolls = Payroll::applyFrequencyFilter($param)->whereIn('user_id', $payrollUsers)->pluck('id')->toArray();
            foreach ($payrolls as $payroll) {
                reCalculatePayrollData($payroll, $param);
            }
        }
    }
}

if (!function_exists('reCalculatePayrollData')) {
    function reCalculatePayrollData($payrollId, $param)
    {
        $payroll = Payroll::find($payrollId);
        if ($payroll && $payroll->status == 1 && !$payroll->is_onetime_payment) {
            if ($payroll->is_next_payroll) {
                PayrollHourlySalary::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                PayrollOvertime::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                UserCommission::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                UserOverrides::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1, 'overrides_settlement_type' => 'during_m2'])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                ClawbackSettlement::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1, 'clawback_type' => 'next payroll'])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                PayrollAdjustmentDetail::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                ApprovalsAndRequest::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->where('status', '!=', 'Paid')->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);
                PayrollDeductions::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_next_payroll' => 1]);

                $updateData = [
                    'hourly_salary' => 0,
                    'overtime' => 0,
                    'commission' => 0,
                    'override' => 0,
                    'adjustment' => 0,
                    'reimbursement' => 0,
                    'custom_payment' => 0,
                    'deduction' => 0,
                    'gross_pay' => 0,
                    'net_pay' => 0,
                    'subtract_amount' => 0,
                ];
                $payroll->update($updateData);
            } else if ($payroll->is_mark_paid) {
                PayrollHourlySalary::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                PayrollOvertime::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                UserCommission::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                UserOverrides::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1, 'overrides_settlement_type' => 'during_m2'])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                ClawbackSettlement::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1, 'clawback_type' => 'next payroll'])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                PayrollAdjustmentDetail::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                ApprovalsAndRequest::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->where('status', '!=', 'Paid')->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);
                PayrollDeductions::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id, 'is_mark_paid' => 1]);

                $updateData = [
                    'hourly_salary' => 0,
                    'overtime' => 0,
                    'commission' => 0,
                    'override' => 0,
                    'adjustment' => 0,
                    'reimbursement' => 0,
                    'custom_payment' => 0,
                    'deduction' => 0,
                    'gross_pay' => 0,
                    'net_pay' => 0,
                    'subtract_amount' => 0,
                ];
                $payroll->update($updateData);
            } else {
                PayrollHourlySalary::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id]);
                PayrollOvertime::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id]);
                UserCommission::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id]);
                UserOverrides::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1, 'overrides_settlement_type' => 'during_m2'])->update(['payroll_id' => $payroll->id]);
                ClawbackSettlement::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1, 'clawback_type' => 'next payroll'])->update(['payroll_id' => $payroll->id]);
                PayrollAdjustmentDetail::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id]);
                ApprovalsAndRequest::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 'Accept'])->update(['payroll_id' => $payroll->id]);
                PayrollDeductions::applyFrequencyFilter($param)->where(['user_id' => $payroll->user_id, 'status' => 1])->update(['payroll_id' => $payroll->id]);

                $hourlySalarySum = PayrollHourlySalary::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('total');
                $overtimeSum = PayrollOvertime::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('total');
                $commissionSum = UserCommission::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $overrideSum = UserOverrides::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0, 'overrides_settlement_type' => 'during_m2'])->sum('amount');
                $clawBackData = ClawbackSettlement::selectRaw('
                    SUM(CASE WHEN type = "commission" THEN COALESCE(clawback_amount, 0) ELSE 0 END) as commission_claw_back_sum,
                    SUM(CASE WHEN type = "overrides" THEN COALESCE(clawback_amount, 0) ELSE 0 END) as override_claw_back_sum
                ')->where([
                    'payroll_id' => $payroll->id,
                    'status' => 1,
                    'is_next_payroll' => 0,
                    'is_mark_paid' => 0,
                    'is_onetime_payment' => 0,
                    'clawback_type' => 'next payroll'
                ])->whereIn('type', ['commission', 'overrides'])->first();
                $commissionClawBackSum = $clawBackData->commission_claw_back_sum ?? 0;
                $overrideClawBackSum = $clawBackData->override_claw_back_sum ?? 0;

                $adjustmentSum = PayrollAdjustmentDetail::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('amount');
                $approvalData = ApprovalsAndRequest::selectRaw('
                    SUM(CASE WHEN adjustment_type_id NOT IN (2, 5, 7, 8, 9) THEN COALESCE(amount, 0) ELSE 0 END) as approvals_and_request_sum,
                    SUM(CASE WHEN adjustment_type_id = 5 THEN COALESCE(amount, 0) ELSE 0 END) as fine_and_fee_sum,
                    SUM(CASE WHEN adjustment_type_id = 2 THEN COALESCE(amount, 0) ELSE 0 END) as reimbursement_sum
                ')->where([
                    'payroll_id' => $payroll->id,
                    'status' => 'Accept',
                    'is_next_payroll' => 0,
                    'is_mark_paid' => 0,
                    'is_onetime_payment' => 0
                ])->first();
                $approvalsAndRequestSum = $approvalData->approvals_and_request_sum ?? 0;
                $fineAndFeeSum = $approvalData->fine_and_fee_sum ?? 0;
                $reimbursementSum = $approvalData->reimbursement_sum ?? 0;

                $deductionSum = PayrollDeductions::where(['payroll_id' => $payroll->id, 'status' => 1, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('total');
                $customFieldSum = CustomField::where(['payroll_id' => $payroll->id, 'is_next_payroll' => 0, 'is_mark_paid' => 0, 'is_onetime_payment' => 0])->sum('value');

                $finalCommissionSum = $commissionSum - $commissionClawBackSum;
                $finalOverrideSum = $overrideSum - $overrideClawBackSum;
                $finalAdjustmentSum = ($adjustmentSum + $approvalsAndRequestSum) - $fineAndFeeSum;
                $grossPaySum = $hourlySalarySum + $overtimeSum + $finalCommissionSum + $finalOverrideSum + $finalAdjustmentSum + $customFieldSum;
                $netPaySum = ($hourlySalarySum + $overtimeSum + $finalCommissionSum + $finalOverrideSum + $finalAdjustmentSum + $reimbursementSum + $customFieldSum) - $deductionSum;

                $updateData = [
                    'hourly_salary' => $hourlySalarySum,
                    'overtime' => $overtimeSum,
                    'commission' => $finalCommissionSum,
                    'override' => $finalOverrideSum,
                    'adjustment' => $finalAdjustmentSum,
                    'reimbursement' => $reimbursementSum,
                    'custom_payment' => $customFieldSum,
                    'deduction' => $deductionSum,
                    'gross_pay' => $grossPaySum,
                    'net_pay' => $netPaySum,
                    'subtract_amount' => $reimbursementSum
                ];
                $payroll->update($updateData);
            }
        }
    }
}

if (!function_exists('payrollRemoveZeroData')) {
    function payrollRemoveZeroData(Request $request)
    {
        $payFrequency = $request->pay_frequency;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $workerType = strtolower($request->worker_type);

        // Build date filter based on pay frequency (same logic as applyFrequencyFilter)
        if ($payFrequency == \App\Models\FrequencyType::DAILY_PAY_ID) {
            // For daily pay, match records within the date range
            $dateFilter = "pay_period_from BETWEEN '{$payPeriodFrom}' AND '{$payPeriodTo}' 
                          AND pay_period_to BETWEEN '{$payPeriodFrom}' AND '{$payPeriodTo}' 
                          AND pay_period_from = pay_period_to";
        } else {
            // For other frequencies, match exact dates
            $dateFilter = "pay_period_from = '{$payPeriodFrom}' AND pay_period_to = '{$payPeriodTo}'";
        }

        $deletedCount = DB::statement("
            DELETE FROM payrolls
            WHERE id NOT IN (
                SELECT DISTINCT payroll_id FROM (
                    SELECT payroll_id FROM payroll_hourly_salary WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND total IS NOT NULL AND total != 0
                    UNION SELECT payroll_id FROM payroll_overtimes WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND total IS NOT NULL AND total != 0
                    UNION SELECT payroll_id FROM user_commission WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND amount IS NOT NULL AND amount != 0
                    UNION SELECT payroll_id FROM clawback_settlements WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND clawback_amount IS NOT NULL AND clawback_amount != 0
                    UNION SELECT payroll_id FROM user_overrides WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND amount IS NOT NULL AND amount != 0
                    UNION SELECT payroll_id FROM approvals_and_requests WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND adjustment_type_id NOT IN (7,8,9) AND amount IS NOT NULL AND amount != 0
                    UNION SELECT payroll_id FROM payroll_adjustment_details WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND amount IS NOT NULL AND amount != 0
                    UNION SELECT payroll_id FROM payroll_deductions WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND total IS NOT NULL AND total != 0
                    UNION SELECT payroll_id FROM custom_field WHERE payroll_id IS NOT NULL AND is_onetime_payment = 0 AND value IS NOT NULL AND value != 0
                ) AS has_data
            ) AND {$dateFilter} AND pay_frequency = '{$payFrequency}' AND worker_type = '{$workerType}'
        ");

        return [
            'success' => true,
            'message' => 'Payroll cleanup completed successfully',
            'deleted_count' => $deletedCount
        ];
    }
}

if (!function_exists('formatRedline')) {
    function formatRedline($redline, $redlineType)
    {
        if (!$redlineType) {
            return null;
        }

        if (in_array($redlineType, config("global_vars.REDLINE_TYPE_ARRAY"))) {
            return $redline . " Per Watt";
        } else {
            return $redline . " " . ucwords($redlineType);
        }
    }
}

if (!function_exists('formatCommission')) {
    function formatCommission($companyType, $commissionAmount = null, $commissionType = null)
    {
        if (!$commissionType) {
            return null;
        }

        if ($companyType == CompanyProfile::SOLAR_COMPANY_TYPE || $companyType == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            if (in_array($commissionType, config("global_vars.REDLINE_TYPE_ARRAY"))) {
                return $commissionAmount . " Per Watt";
            } else if ($commissionType == "per sale") {
                return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " Per Sale";
            } else if ($commissionType == "per kw") {
                if ($companyType == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                    return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " Per Sq Ft";
                } else {
                    return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " Per KW";
                }
            }
        } else if (in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($commissionType == "per sale") {
                return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " Per Sale";
            } else if ($commissionType == "percent") {
                return abs((float) $commissionAmount) . " %";
            }
        } else if ($companyType == CompanyProfile::TURF_COMPANY_TYPE) {
            if ($commissionType == "per sale") {
                return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " Per Sale";
            } else if ($commissionType == "percent") {
                return abs((float) $commissionAmount) . " %";
            } else if ($commissionType == "per kw") {
                return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " Per Sq Ft";
            }
        }

        return "$ " . exportNumberFormat(abs((float) $commissionAmount)) . " " . ucwords($commissionType);
    }
}

if (!function_exists('formatOverride')) {
    function formatOverride($companyType, $overrideAmount = null, $overrideType = null)
    {
        if (!$overrideType) {
            return null;
        }

        if ($companyType == CompanyProfile::SOLAR_COMPANY_TYPE || in_array($companyType, CompanyProfile::PEST_COMPANY_TYPE)) {
            if ($overrideType == "per sale") {
                return "$ " . exportNumberFormat(abs((float) $overrideAmount)) . " Per Sale";
            } else if ($overrideType == "percent") {
                return abs((float) $overrideAmount) . " %";
            } else if ($overrideType == "per kw") {
                return "$ " . exportNumberFormat(abs((float) $overrideAmount)) . " Per KW";
            }
        } else if ($companyType == CompanyProfile::TURF_COMPANY_TYPE || $companyType == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            if ($overrideType == "per sale") {
                return "$ " . exportNumberFormat(abs((float) $overrideAmount)) . " Per Sale";
            } else if ($overrideType == "percent") {
                return abs((float) $overrideAmount) . " %";
            } else if ($overrideType == "per kw") {
                return "$ " . exportNumberFormat(abs((float) $overrideAmount)) . " Per Sq Ft";
            }
        }

        return "$ " . exportNumberFormat(abs((float) $overrideAmount)) . " " . ucwords($overrideType);
    }
}

if (!function_exists('getTotalHoursFromArray')) {
    function getTotalHoursFromArray($hours)
    {
        $totalMinutes = 0;
        foreach ($hours as $time) {
            [$hours, $minutes] = explode(':', $time);
            $totalMinutes += ($hours * 60) + $minutes;
        }

        $totalHours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;
        return sprintf('%02d:%02d', $totalHours, $remainingMinutes);
    }
}

if (!function_exists('adjustmentColumn')) {
    function adjustmentColumn($data, $adjustments, $type)
    {
        $adjustment = NULL;
        if ($type == "commission") {
            $adjustment = $adjustments->first(function ($item) use ($data) {
                return $item->pid == $data->pid && $item->payroll_type_id == $data->id && $item->user_id == $data->user_id && $item->type == $data->payroll_type && $item->adjustment_type == $data->schema_type;
            });
        } else if ($type == "override") {
            $adjustment = $adjustments->first(function ($item) use ($data) {
                return $item->pid == $data->pid && $item->payroll_type_id == $data->id && $item->user_id == $data->user_id && $item->sale_user_id == $data->sale_user_id && $item->type == $data->payroll_type && $item->adjustment_type == $data->type;
            });
        } else if ($type == "adjustment") {
            $adjustment = $adjustments;
        } else if ($type == "deduction") {
            $adjustment = $adjustments->first(function ($item) use ($data) {
                return $item->cost_center_id == $data->cost_center_id && $item->payroll_type_id == $data->id;
            });
        } else if ($type == "hourlysalary") {
            $adjustment = $adjustments->first(function ($item) use ($data) {
                return $item->salary_overtime_date == $data->date && $item->payroll_type_id == $data->id;
            });
        } else if ($type == "overtime") {
            $adjustment = $adjustments->first(function ($item) use ($data) {
                return $item->salary_overtime_date == $data->date && $item->payroll_type_id == $data->id;
            });
        }

        $image = null;
        if ($adjustment?->payrollCommentedBy?->image && $adjustment?->payrollCommentedBy?->image != "Employee_profile/default-user.png") {
            $image = s3_getTempUrl(config("app.domain_name") . "/" . $adjustment?->payrollCommentedBy?->image);
        }

        return [
            "adjustment_amount" => $adjustment?->amount ?? 0, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "adjustment_by" => $adjustment?->payrollCommentedBy?->first_name . " " . $adjustment?->payrollCommentedBy?->last_name ?? "Super Admin", // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "adjustment_comment" => $adjustment?->comment ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "adjustment_id" => $adjustment?->id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "image" => $image ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "position_id" => $adjustment?->payrollCommentedBy?->position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "sub_position_id" => $adjustment?->payrollCommentedBy?->sub_position_id ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "is_manager" => $adjustment?->payrollCommentedBy?->is_manager ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
            "is_super_admin" => $adjustment?->payrollCommentedBy?->is_super_admin ?? null, // SOLAR, PEST, TURF, FIBER, MORTGAGE
        ];
    }
}

if (!function_exists('getUserData')) {
    function getUserData($userId)
    {
        $user = User::select("first_name", "last_name", "office_id", "stop_payroll", "position_id", "sub_position_id", "is_manager", "is_super_admin")
            ->with("payrollSubPosition", "office")->where("id", $userId)->first();
        $image = NULL;
        if ($user && $user->image && $user->image != "Employee_profile/default-user.png") {
            $image = s3_getTempUrl(config("app.domain_name") . "/" . $user->image);
        }

        return [
            "image" => $image,
            "first_name" => $user?->first_name,
            "last_name" => $user?->last_name,
            "office_name" => $user?->office?->office_name,
            "is_stop_payroll" => $user?->stop_payroll,
            "sub_position_name" => $user?->payrollSubPosition?->position_name,
            "position_id" => $user?->position_id,
            "sub_position_id" => $user?->sub_position_id,
            "is_manager" => $user?->is_manager,
            "is_super_admin" => $user?->is_super_admin
        ];
    }
}

if (!function_exists('getReconciliationPositions')) {
    function getReconciliationPositions($payPeriodTo)
    {
        $subQuery = PositionReconciliations::select(
            'id',
            'position_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY position_id ORDER BY effective_date DESC, id DESC) as rn')
        )->where('effective_date', '<=', $payPeriodTo);
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
        $effectiveId = $results->pluck('id')->toArray();
        $positionEffectiveDate = PositionReconciliations::whereIn('id', $effectiveId)->pluck('position_id')->toArray();

        $subQuery = PositionReconciliations::select(
            'id',
            'position_id',
            'effective_date',
            DB::raw('ROW_NUMBER() OVER (PARTITION BY position_id ORDER BY effective_date DESC, id DESC) as rn')
        )->whereNull('effective_date')->whereNotIn("position_id", $positionEffectiveDate);
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))->mergeBindings($subQuery->getQuery())->select('id')->where('rn', 1);
        $nonEffectiveId = $results->pluck('id')->toArray();

        $positionArray = array_merge($effectiveId, $nonEffectiveId);
        return PositionReconciliations::whereIn('id', $positionArray)->where('status', 1)->pluck('position_id')->toArray();
    }
}

if (!function_exists('checkPayrollUserForError')) {
    function checkPayrollUserForError($payrollRecord, $evereeStatus)
    {
        $isError = 0;
        $errorMessage = "";
        if ($evereeStatus) {
            if (!$payrollRecord?->payrollUser?->onboardProcess) {
                $isError = 1;
                $errorMessage = "This user has not completed their onboarding process in Sequifi, leaving some information missing for payroll processing. Sequifi will now skip this user and proceed with the rest of the payroll.";
            } else if (strtolower($payrollRecord?->payrollUser->worker_type) == 'w2' && $payrollRecord?->payrollUser?->everee_embed_onboard_profile != 1) {
                $isError = 1;
                $errorMessage = "This user has not completed their onboarding process in Sequifi, leaving some information missing for payroll processing. Sequifi will now skip this user and proceed with the rest of the payroll.";
            } else if (empty($payrollRecord?->payrollUser?->everee_workerId)) {
                $isError = 1;
                $errorMessage = "Everee worker ID is missing for this user. Sequifi will now skip this user and proceed with the rest of the payroll.";
            }

            if (!$isError) {
                $errorMessage = checkEvereeErrorStructured($payrollRecord?->user_id);
                if ($errorMessage) {
                    $isError = 1;
                }
            }
        }

        return [$isError, $errorMessage];
    }
}

if (!function_exists('payrollUserDataCommon')) {
    function payrollUserDataCommon($user)
    {
        if (!$user) {
            return NULL;
        }

        $image = NULL;
        if ($user && $user->image && $user->image != "Employee_profile/default-user.png") {
            $image = s3_getTempUrl(config("app.domain_name") . "/" . $user->image);
        }

        return [
            "image" => $image,
            "first_name" => $user?->first_name,
            "last_name" => $user?->last_name,
            "position_id" => $user?->position_id,
            "sub_position_id" => $user?->sub_position_id,
            "is_manager" => $user?->is_manager,
            "is_super_admin" => $user?->is_super_admin,
            "is_stop_payroll" => $user?->stop_payroll
        ];
    }
}

if (!function_exists('trimMultiSpace')) {
    function trimMultiSpace($text)
    {
        $text = preg_replace('/[\t\n\r\0\x0B]/', '', $text);
        $text = preg_replace('/([\s])\1+/', ' ', $text);
        $text = trim($text);
        return $text;
    }
}

if (!function_exists('requestApprovalPrefix')) {
    function requestApprovalPrefix($adjustmentTypeId)
    {
        $prefix = 'O';
        if ($adjustmentTypeId == 1) {
            $prefix = 'PD';
        } else if ($adjustmentTypeId == 2) {
            $prefix = 'R';
        } else if ($adjustmentTypeId == 3) {
            $prefix = 'B';
        } else if ($adjustmentTypeId == 4) {
            $prefix = 'A';
        } else if ($adjustmentTypeId == 5) {
            $prefix = 'FF';
        } else if ($adjustmentTypeId == 6) {
            $prefix = 'I';
        } else if ($adjustmentTypeId == 7) {
            $prefix = 'L';
        } else if ($adjustmentTypeId == 8) {
            $prefix = 'PT';
        } else if ($adjustmentTypeId == 9) {
            $prefix = 'TA';
        } else if ($adjustmentTypeId == 10) {
            $prefix = 'C';
        } else if ($adjustmentTypeId == 11) {
            $prefix = 'OV';
        }

        return $prefix;
    }
}

if (!function_exists('oneTimePaymentPrefix')) {
    function oneTimePaymentPrefix($adjustmentTypeId)
    {
        $prefix = 'OTO';
        if ($adjustmentTypeId == 1) {
            $prefix = 'OTPD';
        } else if ($adjustmentTypeId == 2) {
            $prefix = 'OTR';
        } else if ($adjustmentTypeId == 3) {
            $prefix = 'OTB';
        } else if ($adjustmentTypeId == 4) {
            $prefix = 'OTA';
        } else if ($adjustmentTypeId == 6) {
            $prefix = 'OTI';
        } else if ($adjustmentTypeId == 10) {
            $prefix = 'OTC';
        } else if ($adjustmentTypeId == 11) {
            $prefix = 'OTOV';
        } else if ($adjustmentTypeId == 12) {
            $prefix = 'OTPR';
        }

        return $prefix;
    }
}

if (!function_exists('createOrUpdateUserSchedules')) {
    function createOrUpdateUserSchedules($userId, $officeId, $clockIn, $clockOut, $adjustmentDate, $lunch)
    {
        if (!empty($lunch) && !is_null($lunch) &&  $lunch != 'None') {
            $lunch = $lunch . ' Mins';
        }
        $userSchedule = UserSchedule::where('user_id', $userId)->first();
        if (!$userSchedule) {
            $userSchedule = UserSchedule::create(['user_id' => $userId, 'scheduled_by' => Auth::user()->id]);
        }
        $scheduleDate = Carbon::parse($clockIn);
        $dayNumber = $scheduleDate->dayOfWeekIso;
        $checkUserScheduleDetail = UserScheduleDetail::where(['schedule_id' => $userSchedule->id, 'office_id' => $officeId])->whereDate('schedule_from', $adjustmentDate)->first();
        if (!$checkUserScheduleDetail) {
            $scheduleDetailData = [
                'schedule_id' => $userSchedule->id,
                'office_id' => $officeId,
                'schedule_from' => $clockIn,
                'schedule_to' => $clockOut,
                'lunch_duration' => $lunch,
                'work_days' => $dayNumber,
                'repeated_batch' => 0,
                'user_attendance_id' => null
            ];
            UserScheduleDetail::create($scheduleDetailData);
        }
    }
}

if (!function_exists('checkUsedDay')) {
    function checkUsedDay($id, $startDate, $endDate, $requestDay)
    {
        $error = [];
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);
        if ($start->isSameDay($end)) {
            $approvalData = ApprovalsAndRequest::where(['adjustment_type_id' => 8, 'user_id' => $id])
                ->whereDate('start_date', '<=', $startDate)
                ->whereDate('end_date', '>=', $startDate)
                ->where('status', '!=', 'Declined')->get();
            $pto = 0;
            foreach ($approvalData as $approval) {
                $pto += $approval->pto_hours_perday;
            }
            if ($approvalData && ($pto + $requestDay) > 8) {
                $error[] = $start->format('m/d/y') . ' request cannot be created because the PTO hours exceed 8.';
            }
        } else {
            foreach ($start->daysUntil($end) as $date) {
                $approvalData = ApprovalsAndRequest::where(['adjustment_type_id' => 8, 'user_id' => $id])
                    ->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date)
                    ->where('status', '!=', 'Declined')
                    ->get();
                $pto = 0;
                foreach ($approvalData as $approval) {
                    $pto += $approval->pto_hours_perday;
                }
                if ($approvalData && ($pto + $requestDay) > 8) {
                    $error[] = $date->format('m/d/y') . ' request cannot be created because the PTO hours exceed 8.';
                }
            }
        }
        return $error;
    }
}

if (!function_exists('calculatePTOs')) {
    function calculatePTOs($userId = null, $date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }
        if (!$userId) {
            $userId = Auth::user()->id;
        }

        $totalUsedPTOHours = 0;
        $totalPTOHours = 0;
        $date = Carbon::parse($date);
        $user = UserWagesHistory::where('user_id', $userId)->where('pto_hours_effective_date', '<=', $date)->orderBy('pto_hours_effective_date', 'DESC')->first();
        if ($user->unused_pto_expires == 'Monthly' || $user->unused_pto_expires == 'Expires Monthly') {
            $totalPTOHours = $user->pto_hours;
            $startDate = $date->copy()->startOfMonth()->toDateString();
            $endDate = $date->copy()->endOfMonth()->toDateString();
            $userPTOs = ApprovalsAndRequest::where('user_id', $userId)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($userPTOs as $pto) {
                $ptoStartDate = Carbon::parse($pto->start_date);
                $ptoEndDate = Carbon::parse($pto->end_date);
                if ($ptoEndDate->lt($startDate) || $ptoStartDate->gt($endDate)) {
                    continue; // Skip PTOs outside the current month
                }

                $overlapStart = $ptoStartDate->gt($startDate) ? $ptoStartDate : $startDate;
                $overlapEnd = $ptoEndDate->lt($endDate) ? $ptoEndDate : $endDate;
                $days = $overlapStart->diffInDays($overlapEnd) + 1;
                $totalUsedPTOHours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Annually' || $user->unused_pto_expires == 'Expires Annually') {
            $startDate = $date->copy()->startOfYear()->toDateString();
            $endDate = $date->copy()->endOfYear()->toDateString();

            $ptoStartDate = Carbon::parse($user->created_at)->lt($date->copy()->startOfYear()) ? $date->copy()->startOfYear() : Carbon::parse($user->created_at);
            $monthCount = $ptoStartDate->diffInMonths($date);
            $totalPTOHours = $user->pto_hours * ($monthCount + 1);
            $userPTOs = ApprovalsAndRequest::where('user_id', $userId)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })
                ->orderBy('start_date', 'ASC')->get(['start_date', 'end_date', 'pto_hours_perday']);
            foreach ($userPTOs as $pto) {
                $ptoStartDate = Carbon::parse($pto->start_date);
                $ptoEndDate = Carbon::parse($pto->end_date);
                if ($ptoEndDate->lt($startDate) || $ptoStartDate->gt($endDate)) {
                    continue; // Skip PTOs outside the current month
                }
                $overlapStart = $ptoStartDate->gt($startDate) ? $ptoStartDate : $startDate;
                $overlapEnd = $ptoEndDate->lt($endDate) ? $ptoEndDate : $endDate;
                $days = $overlapStart->diffInDays($overlapEnd) + 1;
                $totalUsedPTOHours += $days * $pto->pto_hours_perday;
            }
        } elseif ($user->unused_pto_expires == 'Accrues Continuously' || $user->unused_pto_expires == 'Expires Accrues Continuously') {
            $monthCount =  Carbon::parse($user->created_at)->diffInMonths($date);
            $totalPTOHours = $user->pto_hours * ($monthCount + 1);
            $userPTOs = ApprovalsAndRequest::where('user_id', $userId)
                ->where('adjustment_type_id', 8)
                ->where('status', '!=', 'Declined')
                ->get(['start_date', 'end_date', 'pto_hours_perday']);

            foreach ($userPTOs as $pto) {
                $ptoStartDate = Carbon::parse($pto->start_date);
                $ptoEndDate = Carbon::parse($pto->end_date);
                $days = $ptoStartDate->diffInDays($ptoEndDate) + 1;
                $totalUsedPTOHours += $days * $pto->pto_hours_perday;
            }
        }

        return [
            'total_ptos' => (int)$totalPTOHours,
            'total_user_ptos' => (int)$totalUsedPTOHours,
            'total_remaining_ptos' => (int)$totalPTOHours - $totalUsedPTOHours
        ];
    }
}

if (!function_exists('approvedTimeAdjustment')) {
    function approvedTimeAdjustment($approvalsAndRequest, $userId)
    {
        $userId = $approvalsAndRequest->user_id;
        $adjustmentDate = $approvalsAndRequest->adjustment_date;
        if ($userId && $adjustmentDate) {
            $userAttendance = UserAttendance::where(['user_id' => $userId, 'date' => $adjustmentDate])->first();
            if ($userAttendance) {
                UserAttendanceDetail::where(['user_attendance_id' => $userAttendance->id])->delete();
                UserAttendanceDetail::create([
                    'user_attendance_id' => isset($userAttendance->id) ? $userAttendance->id : null,
                    'adjustment_id' => isset($approvalsAndRequest->id) ? $approvalsAndRequest->id : 0,
                    "attendance_date" => isset($approvalsAndRequest->adjustment_date) ? $approvalsAndRequest->adjustment_date : null,
                    "entry_type" => 'Adjustment',
                    'created_by' => $userId
                ]);
            } else {
                $userAttendance = UserAttendance::create([
                    'user_id' => $userId,
                    'date' => $adjustmentDate
                ]);

                UserAttendanceDetail::create([
                    'user_attendance_id' => isset($userAttendance->id) ? $userAttendance->id : null,
                    'adjustment_id' => isset($approvalsAndRequest->id) ? $approvalsAndRequest->id : 0,
                    "attendance_date" => isset($approvalsAndRequest->adjustment_date) ? $approvalsAndRequest->adjustment_date : null,
                    "entry_type" => 'Adjustment',
                    'created_by' => $userId
                ]);
            }

            $userCheckIn = isset($approvalsAndRequest->clock_in) ? $approvalsAndRequest->clock_in : null;
            $userCheckOut = isset($approvalsAndRequest->clock_out) ? $approvalsAndRequest->clock_out : null;
            $clockIn = Carbon::parse($userCheckIn);
            $clockOut = Carbon::parse($userCheckOut);
            $diffInSeconds = $clockIn->diffInSeconds($clockOut);
            $lunchBreak = isset($approvalsAndRequest->lunch_adjustment) ? $approvalsAndRequest->lunch_adjustment : 0;
            $breakTime = isset($approvalsAndRequest->break_adjustment) ? $approvalsAndRequest->break_adjustment : 0;
            if (!is_null($lunchBreak)) {
                $lunchBreak = gmdate('H:i:s', $lunchBreak * 60);
            }
            if (!is_null($breakTime)) {
                $breakTime = gmdate('H:i:s', $breakTime * 60);
            }

            $lunchDuration = Carbon::parse($lunchBreak)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
            $breakDuration = Carbon::parse($breakTime)->diffInSeconds(Carbon::parse('00:00:00')); // 30 minutes
            $totalWorkedSeconds = $diffInSeconds - ($lunchDuration + $breakDuration);
            $finalTimeDifference = gmdate('H:i:s', $totalWorkedSeconds);

            $attendanceData = [];
            $attendanceData['id'] = $approvalsAndRequest->id;
            $attendanceData['user_id'] = $approvalsAndRequest->user_id;
            $attendanceData['current_time'] = $finalTimeDifference;
            $attendanceData['lunch_time'] = $lunchBreak;
            $attendanceData['break_time'] = $breakTime;
            $attendanceData['date'] = $approvalsAndRequest->adjustment_date;
            if (!empty($attendanceData['current_time'])) {
                (new ScheduleController())->payroll_wages_create($attendanceData);
            }
        }
    }
}

if (!function_exists('checkPayrollStatus')) {
    function checkPayrollStatus($frequencyTypeId, $workerType, $payPeriodFrom, $payPeriodTo)
    {
        $param = [
            "pay_frequency" => $frequencyTypeId,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];
        $check = Payroll::applyFrequencyFilter($param, ["status" => 2])->whereIn("finalize_status", [1, 2])->count();
        if ($check) {
            return true;
        }
        return false;
    }
}

if (!function_exists('updatePayrollStatus')) {
    function updatePayrollStatus($request, $column, $where, $isMarkPaid = 0, $isMoveToNext = 0)
    {
        $validator = Validator::make($request->all(), [
            "type" => "required|in:pid,worker",
            "payroll_id" => "required_if:type,worker|array",
            "pid" => "required_if:type,pid|array",
            "select_type" => "required|in:this_page,all_pages",
            "pay_period_from" => "required",
            "pay_period_to" => "required",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "change-status",
                "error" => $validator->errors()
            ], 400);
        }

        $type = $request->type;
        $payrollIds = $request->payroll_id;
        $pid = $request->pid;
        $selectType = $request->select_type;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $payFrequency = $request->pay_frequency;
        $workerType = $request->worker_type;

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        $payroll = Payroll::applyFrequencyFilter($param, ["status" => "2"])->first();
        if ($payroll) {
            return response()->json([
                "ApiName" => "change-status",
                "status"  => false,
                "message" => "The payroll has already been finalized. No changes can be made after finalization."
            ], 400);
        }

        if ($type == "pid") {
            DB::beginTransaction();
            try {
                $userCommissions = UserCommission::applyFrequencyFilter($param)->where($where)->where(['is_move_to_recon' => 0])->when($selectType == "this_page", function ($q) use ($pid) {
                    $q->whereIn('pid', $pid);
                })->get();
                foreach ($userCommissions as $userCommission) {
                    $userCommission->is_mark_paid = $isMarkPaid;
                    $userCommission->is_next_payroll = $isMoveToNext;
                    $userCommission->save();
                }

                $userOverrides = UserOverrides::applyFrequencyFilter($param)->where($where)->where(['is_move_to_recon' => 0])->when($selectType == 'this_page', function ($q) use ($pid) {
                    $q->whereIn('pid', $pid);
                })->get();
                foreach ($userOverrides as $userOverride) {
                    $userOverride->is_mark_paid = $isMarkPaid;
                    $userOverride->is_next_payroll = $isMoveToNext;
                    $userOverride->save();
                }

                $clawBackSettlements = ClawbackSettlement::applyFrequencyFilter($param)->where($where)->where(['is_move_to_recon' => 0])->when($selectType == 'this_page', function ($q) use ($pid) {
                    $q->whereIn('pid', $pid);
                })->get();
                foreach ($clawBackSettlements as $clawBackSettlement) {
                    $clawBackSettlement->is_mark_paid = $isMarkPaid;
                    $clawBackSettlement->is_next_payroll = $isMoveToNext;
                    $clawBackSettlement->save();
                }

                $payrollAdjustmentDetails = PayrollAdjustmentDetail::applyFrequencyFilter($param)->where($where)->where(['is_move_to_recon' => 0])->when($selectType == 'this_page', function ($q) use ($pid) {
                    $q->whereIn('pid', $pid);
                })->get();
                foreach ($payrollAdjustmentDetails as $payrollAdjustmentDetail) {
                    $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                    $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                    $payrollAdjustmentDetail->save();
                }

                $reconCommissionHistories = ReconCommissionHistory::applyFrequencyFilter($param)->where($where)->when($selectType == 'this_page', function ($q) use ($pid) {
                    $q->whereIn('pid', $pid);
                })->get();
                foreach ($reconCommissionHistories as $reconCommissionHistory) {
                    $reconCommissionHistory->is_mark_paid = $isMarkPaid;
                    $reconCommissionHistory->is_next_payroll = $isMoveToNext;
                    $reconCommissionHistory->save();
                }

                $reconOverrideHistories = ReconOverrideHistory::applyFrequencyFilter($param)->where($where)->when($selectType == 'this_page', function ($q) use ($pid) {
                    $q->whereIn('pid', $pid);
                })->get();
                foreach ($reconOverrideHistories as $reconOverrideHistory) {
                    $reconOverrideHistory->is_mark_paid = $isMarkPaid;
                    $reconOverrideHistory->is_next_payroll = $isMoveToNext;
                    $reconOverrideHistory->save();
                }

                DB::commit();
                return response()->json([
                    "ApiName" => "change-status",
                    "status"  => true,
                    "message" => "Payroll marked as paid successfully"
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json([
                    "ApiName" => "change-status",
                    "status"  => false,
                    "message" => $e->getMessage()
                ], 400);
            }
        } else {
            $data = Payroll::applyFrequencyFilter($param)->when($selectType == "this_page", function ($q) use ($payrollIds) {
                $q->whereIn('id', $payrollIds);
            })->where($where)->get();

            DB::beginTransaction();
            try {
                foreach ($data as $payroll) {
                    $hourlySalaries = PayrollHourlySalary::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($hourlySalaries as $hourlySalary) {
                        $hourlySalary->is_mark_paid = $isMarkPaid;
                        $hourlySalary->is_next_payroll = $isMoveToNext;
                        $hourlySalary->save();
                    }

                    $overtimes = PayrollOvertime::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($overtimes as $overtime) {
                        $overtime->is_mark_paid = $isMarkPaid;
                        $overtime->is_next_payroll = $isMoveToNext;
                        $overtime->save();
                    }

                    $userCommissions = UserCommission::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->where(['is_move_to_recon' => 0])->get();
                    foreach ($userCommissions as $userCommission) {
                        $userCommission->is_mark_paid = $isMarkPaid;
                        $userCommission->is_next_payroll = $isMoveToNext;
                        $userCommission->save();
                    }

                    $userOverrides = UserOverrides::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->where(['is_move_to_recon' => 0])->get();
                    foreach ($userOverrides as $userOverride) {
                        $userOverride->is_mark_paid = $isMarkPaid;
                        $userOverride->is_next_payroll = $isMoveToNext;
                        $userOverride->save();
                    }

                    $clawBacks = ClawbackSettlement::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->where(['is_move_to_recon' => 0])->get();
                    foreach ($clawBacks as $clawBack) {
                        $clawBack->is_mark_paid = $isMarkPaid;
                        $clawBack->is_next_payroll = $isMoveToNext;
                        $clawBack->save();
                    }

                    $approvals = ApprovalsAndRequest::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($approvals as $approval) {
                        $approval->is_mark_paid = $isMarkPaid;
                        $approval->is_next_payroll = $isMoveToNext;
                        $approval->save();
                    }

                    $adjustmentDetails = PayrollAdjustmentDetail::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->where(['is_move_to_recon' => 0])->get();
                    foreach ($adjustmentDetails as $detail) {
                        $detail->is_mark_paid = $isMarkPaid;
                        $detail->is_next_payroll = $isMoveToNext;
                        $detail->save();
                    }

                    $deductions = PayrollDeductions::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->where(['is_move_to_recon' => 0])->get();
                    foreach ($deductions as $deduction) {
                        $deduction->is_mark_paid = $isMarkPaid;
                        $deduction->is_next_payroll = $isMoveToNext;
                        $deduction->save();
                    }

                    $reconFinalizeHistories = ReconciliationFinalizeHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($reconFinalizeHistories as $reconFinalizeHistory) {
                        $reconFinalizeHistory->is_mark_paid = $isMarkPaid;
                        $reconFinalizeHistory->is_next_payroll = $isMoveToNext;
                        $reconFinalizeHistory->save();
                    }

                    $reconCommissionHistories = ReconCommissionHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($reconCommissionHistories as $reconCommissionHistory) {
                        $reconCommissionHistory->is_mark_paid = $isMarkPaid;
                        $reconCommissionHistory->is_next_payroll = $isMoveToNext;
                        $reconCommissionHistory->save();
                    }

                    $reconOverrideHistories = ReconOverrideHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($reconOverrideHistories as $reconOverrideHistory) {
                        $reconOverrideHistory->is_mark_paid = $isMarkPaid;
                        $reconOverrideHistory->is_next_payroll = $isMoveToNext;
                        $reconOverrideHistory->save();
                    }

                    $customFields = CustomField::where(["payroll_id" => $payroll->id])->where($where)->get();
                    foreach ($customFields as $customField) {
                        $customField->is_mark_paid = $isMarkPaid;
                        $customField->is_next_payroll = $isMoveToNext;
                        $customField->save();
                    }

                    $hourlySalaries = PayrollHourlySalary::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($hourlySalaries > 0) {
                        continue;
                    }

                    $overtimes = PayrollOvertime::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($overtimes > 0) {
                        continue;
                    }

                    $userCommissions = UserCommission::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(function ($q) use ($column) {
                        $q->where([$column => 1])->orWhere(['is_move_to_recon' => 1]);
                    })->count();
                    if ($userCommissions > 0) {
                        continue;
                    }

                    $userOverrides = UserOverrides::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(function ($q) use ($column) {
                        $q->where([$column => 1])->orWhere(['is_move_to_recon' => 1]);
                    })->count();
                    if ($userOverrides > 0) {
                        continue;
                    }

                    $clawBacks = ClawbackSettlement::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(function ($q) use ($column) {
                        $q->where([$column => 1])->orWhere(['is_move_to_recon' => 1]);
                    })->count();
                    if ($clawBacks > 0) {
                        continue;
                    }

                    $approvals = ApprovalsAndRequest::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($approvals > 0) {
                        continue;
                    }

                    $adjustmentDetails = PayrollAdjustmentDetail::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(function ($q) use ($column) {
                        $q->where([$column => 1])->orWhere(['is_move_to_recon' => 1]);
                    })->count();
                    if ($adjustmentDetails > 0) {
                        continue;
                    }

                    $deductions = PayrollDeductions::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(function ($q) use ($column) {
                        $q->where([$column => 1])->orWhere(['is_move_to_recon' => 1]);
                    })->count();
                    if ($deductions > 0) {
                        continue;
                    }

                    $reconFinalizeHistories = ReconciliationFinalizeHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($reconFinalizeHistories > 0) {
                        continue;
                    }

                    $reconCommissionHistories = ReconCommissionHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($reconCommissionHistories > 0) {
                        continue;
                    }

                    $reconOverrideHistories = ReconOverrideHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($reconOverrideHistories > 0) {
                        continue;
                    }

                    $customFields = CustomField::where(["payroll_id" => $payroll->id])->where([$column => 1])->count();
                    if ($customFields > 0) {
                        continue;
                    }

                    $payrollRecord = Payroll::find($payroll->id);
                    $payrollRecord->is_mark_paid = $isMarkPaid;
                    $payrollRecord->is_next_payroll = $isMoveToNext;
                    $payrollRecord->save();
                }

                DB::commit();
                return response()->json([
                    "ApiName" => "change-status",
                    "status"  => true,
                    "message" => "Payroll marked as paid successfully"
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json([
                    "ApiName" => "change-status",
                    "status"  => false,
                    "message" => $e->getMessage()
                ], 400);
            }
        }
    }
}

if (!function_exists('updateSinglePayrollStatus')) {
    function updateSinglePayrollStatus($request, $isMarkPaid = 0, $isMoveToNext = 0)
    {
        $validator = Validator::make($request->all(), [
            "data" => "required|array",
            "data.*.id" => "required",
            "data.*.operation_type" => "required",
            "pay_period_from" => "required",
            "pay_period_to" => "required",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "change-single-status",
                "error" => $validator->errors()
            ], 400);
        }

        $data = $request->data;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $payFrequency = $request->pay_frequency;
        $workerType = $request->worker_type;

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        $payroll = Payroll::applyFrequencyFilter($param, ["status" => "2"])->first();
        if ($payroll) {
            return response()->json([
                "ApiName" => "change-status",
                "status"  => false,
                "message" => "The payroll has already been finalized. No changes can be made after finalization."
            ], 400);
        }

        try {
            DB::beginTransaction();
            foreach ($data as $item) {
                if ($item['operation_type'] == "salary") {
                    $salaryDetail = PayrollHourlySalary::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($salaryDetail) {
                        $salaryDetail->is_mark_paid = $isMarkPaid;
                        $salaryDetail->is_next_payroll = $isMoveToNext;
                        $salaryDetail->save();

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                            "user_id" => $salaryDetail->user_id,
                            "type" => "hourlysalary",
                            "payroll_type" => "hourlysalary",
                            "is_onetime_payment" => "0"
                        ])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                            $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                            $payrollAdjustmentDetail->save();
                        }

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $salaryDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "overtime") {
                    $overtimeDetail = PayrollOvertime::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($overtimeDetail) {
                        $overtimeDetail->is_mark_paid = $isMarkPaid;
                        $overtimeDetail->is_next_payroll = $isMoveToNext;
                        $overtimeDetail->save();

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                            "user_id" => $overtimeDetail->user_id,
                            "type" => "overtime",
                            "payroll_type" => "overtime",
                            "is_onetime_payment" => "0"
                        ])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                            $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                            $payrollAdjustmentDetail->save();
                        }

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $overtimeDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "commission") {
                    $commissionDetail = UserCommission::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($commissionDetail) {
                        $commissionDetail->is_mark_paid = $isMarkPaid;
                        $commissionDetail->is_next_payroll = $isMoveToNext;
                        $commissionDetail->save();

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                            "pid" => $commissionDetail->pid,
                            "payroll_type" => "commission",
                            "type" => $commissionDetail->schema_type,
                            "user_id" => $commissionDetail->user_id,
                            "adjustment_type" => $commissionDetail->schema_type,
                            "is_onetime_payment" => "0"
                        ])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                            $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                            $payrollAdjustmentDetail->save();
                        }

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $commissionDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "override") {
                    $overrideDetail = UserOverrides::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($overrideDetail) {
                        $overrideDetail->is_mark_paid = $isMarkPaid;
                        $overrideDetail->is_next_payroll = $isMoveToNext;
                        $overrideDetail->save();

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                            "pid" => $overrideDetail->pid,
                            "payroll_type" => "overrides",
                            "type" => $overrideDetail->type,
                            "user_id" => $overrideDetail->user_id,
                            "sale_user_id" => $overrideDetail->sale_user_id,
                            "adjustment_type" => $overrideDetail->type,
                            "is_onetime_payment" => "0"
                        ])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                            $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                            $payrollAdjustmentDetail->save();
                        }

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $overrideDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "clawback") {
                    $clawBackDetail = ClawbackSettlement::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($clawBackDetail) {
                        $clawBackDetail->is_mark_paid = $isMarkPaid;
                        $clawBackDetail->is_next_payroll = $isMoveToNext;
                        $clawBackDetail->save();

                        if ($clawBackDetail->type == "commission") {
                            $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                                "pid" => $clawBackDetail->pid,
                                "payroll_type" => "commission",
                                "type" => "clawback",
                                "user_id" => $clawBackDetail->user_id,
                                "adjustment_type" => $clawBackDetail->schema_type,
                                "is_onetime_payment" => "0"
                            ])->first();
                            if ($payrollAdjustmentDetail) {
                                $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                                $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                                $payrollAdjustmentDetail->save();
                            }
                        } else {
                            $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                                "pid" => $clawBackDetail->pid,
                                "payroll_type" => "overrides",
                                "type" => "clawback",
                                "user_id" => $clawBackDetail->user_id,
                                "sale_user_id" => $clawBackDetail->sale_user_id,
                                "adjustment_type" => $clawBackDetail->type,
                                "is_onetime_payment" => "0"
                            ])->first();
                            if ($payrollAdjustmentDetail) {
                                $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                                $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                                $payrollAdjustmentDetail->save();
                            }
                        }

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $clawBackDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "adjustment") {
                    $adjustmentDetail = PayrollAdjustmentDetail::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($adjustmentDetail) {
                        $adjustmentDetail->is_mark_paid = $isMarkPaid;
                        $adjustmentDetail->is_next_payroll = $isMoveToNext;
                        $adjustmentDetail->save();

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $adjustmentDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "request_approval" || $item['operation_type'] == "reimbursement") {
                    $requestApprovalDetail = ApprovalsAndRequest::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($requestApprovalDetail) {
                        $requestApprovalDetail->is_mark_paid = $isMarkPaid;
                        $requestApprovalDetail->is_next_payroll = $isMoveToNext;
                        $requestApprovalDetail->save();

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $requestApprovalDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                } else if ($item['operation_type'] == "deduction") {
                    $deductionDetail = PayrollDeductions::where(["id" => $item['id'], "is_onetime_payment" => "0"])->first();
                    if ($deductionDetail) {
                        $deductionDetail->is_mark_paid = $isMarkPaid;
                        $deductionDetail->is_next_payroll = $isMoveToNext;
                        $deductionDetail->save();

                        $payrollAdjustmentDetail = PayrollAdjustmentDetail::applyFrequencyFilter($param, [
                            "user_id" => $deductionDetail->user_id,
                            "cost_center_id" => $deductionDetail->cost_center_id,
                            "payroll_type" => "deduction",
                            "is_onetime_payment" => "0"
                        ])->first();
                        if ($payrollAdjustmentDetail) {
                            $payrollAdjustmentDetail->is_mark_paid = $isMarkPaid;
                            $payrollAdjustmentDetail->is_next_payroll = $isMoveToNext;
                            $payrollAdjustmentDetail->save();
                        }

                        if (!$isMarkPaid && !$isMoveToNext) {
                            $payroll = Payroll::where(["id" => $deductionDetail->payroll_id])->first();
                            if ($payroll) {
                                $payroll->is_mark_paid = $isMarkPaid;
                                $payroll->is_next_payroll = $isMoveToNext;
                                $payroll->save();
                            }
                        }
                    }
                }
            }

            DB::commit();
            return response()->json([
                "status" => true,
                "ApiName" => "change-single-status",
                "message" => "Successfully."
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "ApiName" => "change-single-status",
                "error" => $e->getMessage()
            ], 400);
        }
    }
}

if (!function_exists('undoAllStatus')) {
    function undoAllStatus($request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required",
            "pay_period_to" => "required",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => false,
                "ApiName" => "change-status",
                "error" => $validator->errors()
            ], 400);
        }

        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $payFrequency = $request->pay_frequency;
        $workerType = $request->worker_type;

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        $payroll = Payroll::applyFrequencyFilter($param, ["status" => "2"])->first();
        if ($payroll) {
            return response()->json([
                "ApiName" => "change-status",
                "status"  => false,
                "message" => "The payroll has already been finalized. No changes can be made after finalization."
            ], 400);
        }

        $data = Payroll::applyFrequencyFilter($param)->where(['is_onetime_payment' => 0])->get();
        DB::beginTransaction();
        try {
            foreach ($data as $payroll) {
                $hourlySalaries = PayrollHourlySalary::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($hourlySalaries as $hourlySalary) {
                    $hourlySalary->is_mark_paid = 0;
                    $hourlySalary->is_next_payroll = 0;
                    $hourlySalary->save();
                }

                $overtimes = PayrollOvertime::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($overtimes as $overtime) {
                    $overtime->is_mark_paid = 0;
                    $overtime->is_next_payroll = 0;
                    $overtime->save();
                }

                $userCommissions = UserCommission::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($userCommissions as $userCommission) {
                    $userCommission->is_mark_paid = 0;
                    $userCommission->is_next_payroll = 0;
                    $userCommission->is_move_to_recon = 0;
                    $userCommission->save();
                }

                $userOverrides = UserOverrides::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($userOverrides as $userOverride) {
                    $userOverride->is_mark_paid = 0;
                    $userOverride->is_next_payroll = 0;
                    $userOverride->is_move_to_recon = 0;
                    $userOverride->save();
                }

                $clawBacks = ClawbackSettlement::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($clawBacks as $clawBack) {
                    $clawBack->is_mark_paid = 0;
                    $clawBack->is_next_payroll = 0;
                    $clawBack->is_move_to_recon = 0;
                    $clawBack->save();
                }

                $approvals = ApprovalsAndRequest::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($approvals as $approval) {
                    $approval->is_mark_paid = 0;
                    $approval->is_next_payroll = 0;
                    $approval->save();
                }

                $adjustmentDetails = PayrollAdjustmentDetail::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($adjustmentDetails as $detail) {
                    $detail->is_mark_paid = 0;
                    $detail->is_next_payroll = 0;
                    $detail->is_move_to_recon = 0;
                    $detail->save();
                }

                $deductions = PayrollDeductions::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($deductions as $deduction) {
                    $deduction->is_mark_paid = 0;
                    $deduction->is_next_payroll = 0;
                    $deduction->is_move_to_recon = 0;
                    $deduction->save();
                }

                $reconFinalizeHistories = ReconciliationFinalizeHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($reconFinalizeHistories as $reconFinalizeHistory) {
                    $reconFinalizeHistory->is_mark_paid = 0;
                    $reconFinalizeHistory->is_next_payroll = 0;
                    $reconFinalizeHistory->save();
                }

                $reconCommissionHistories = ReconCommissionHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($reconCommissionHistories as $reconCommissionHistory) {
                    $reconCommissionHistory->is_mark_paid = 0;
                    $reconCommissionHistory->is_next_payroll = 0;
                    $reconCommissionHistory->save();
                }

                $reconOverrideHistories = ReconOverrideHistory::applyFrequencyFilter($param, ["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($reconOverrideHistories as $reconOverrideHistory) {
                    $reconOverrideHistory->is_mark_paid = 0;
                    $reconOverrideHistory->is_next_payroll = 0;
                    $reconOverrideHistory->save();
                }

                $customFields = CustomField::where(["payroll_id" => $payroll->id])->where(['is_onetime_payment' => 0])->get();
                foreach ($customFields as $customField) {
                    $customField->is_mark_paid = 0;
                    $customField->is_next_payroll = 0;
                    $customField->save();
                }

                $payrollRecord = Payroll::find($payroll->id);
                $payrollRecord->is_mark_paid = 0;
                $payrollRecord->is_next_payroll = 0;
                $payrollRecord->save();
            }

            DB::commit();
            return response()->json([
                "ApiName" => "change-status",
                "status"  => true,
                "message" => "Undo all status successfully!!"
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                "ApiName" => "change-status",
                "status"  => false,
                "message" => $e->getMessage()
            ], 400);
        }
    }
}

if (!function_exists('getPayrollDataSummary')) {
    function getPayrollDataSummary(Request $request, $payrollClass)
    {
        $workerType = $request->worker_type;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $frequencyTypeId = $request->frequency_type_id;

        $param = [
            "pay_frequency" => $frequencyTypeId,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        if ($frequencyTypeId == FrequencyType::WEEKLY_ID) {
            $class = WeeklyPayFrequency::class;
        } else if ($frequencyTypeId == FrequencyType::MONTHLY_ID) {
            $class = MonthlyPayFrequency::class;
        } else if ($frequencyTypeId == FrequencyType::BI_WEEKLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
        } else if ($frequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
            $class = AdditionalPayFrequency::class;
            $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
        } else if ($frequencyTypeId == FrequencyType::DAILY_PAY_ID) {
            $class = DailyPayFrequency::class;
        }

        if (isset($class)) {
            $payFrequency = $class::query();
            if ($frequencyTypeId == FrequencyType::BI_WEEKLY_ID || $frequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
                $payFrequency = $payFrequency->where('type', $type)->where('pay_period_from', '<', $payPeriodFrom)->orderBy('pay_period_from', 'DESC')->first();
            } else {
                $payFrequency = $payFrequency->where('pay_period_from', '<', $payPeriodFrom)->orderBy('pay_period_from', 'DESC')->first();
            }

            if (!$payFrequency) {
                $newClass = Payroll::class;
                $priviesStartDate = null;
                $priviesEndDate = null;
            } else {
                if ($payFrequency->closed_status == '1') {
                    $newClass = PayrollHistory::class;
                } else {
                    $newClass = Payroll::class;
                }
                $priviesStartDate = $payFrequency->pay_period_from;
                $priviesEndDate = $payFrequency->pay_period_to;
            }
        }

        if ($payPeriodFrom) {
            $payrollData = $payrollClass::query()->select(
                DB::raw('sum(commission) as commission'),
                DB::raw('sum(override) as override'),
                DB::raw('sum(adjustment) as adjustment'),
                DB::raw('sum(reimbursement) as reimbursement'),
                DB::raw('sum(reconciliation) as reconciliation'),
                DB::raw('sum(deduction) as deduction'),
                DB::raw('sum(net_pay) as net_pay'),
                DB::raw('sum(hourly_salary) as hourly_salary'),
                DB::raw('sum(overtime) as overtime')
            )->applyFrequencyFilter($param);
            if ($payrollClass == Payroll::class) {
                $payrollData = $payrollData->where('is_stop_payroll', '0');
            }
            $payrollData = $payrollData->first();
            if ($payrollClass == Payroll::class) {
                $payrollIds = $payrollClass::query()->applyFrequencyFilter($param, ['is_stop_payroll' => '0', 'is_mark_paid' => 0, 'is_next_payroll' => 0])->pluck('id')->toArray();
                $sumCustomField = CustomField::whereIn('payroll_id', $payrollIds)->sum('value');

                $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
                $payrollCustomFields = PayrollSsetup::where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->where('status', 1)->orderBy('id', 'Asc')->get();
                $payrollCustomFields->transform(function ($payrollCustomFields) use ($payrollIds) {
                    $customFieldValue = CustomField::where('column_id', $payrollCustomFields->id)->whereIn('payroll_id', $payrollIds)->sum('value');
                    return [
                        'id' => $payrollCustomFields->id,
                        'field_name' => $payrollCustomFields->field_name ?? "",
                        'value' => $customFieldValue
                    ];
                });
            } else {
                $payrollIds = $payrollClass::query()->applyFrequencyFilter($param)->pluck('payroll_id')->toArray();
                $sumCustomField = CustomFieldHistory::whereIn('payroll_id', $payrollIds)->where(['is_mark_paid' => '0', 'is_next_payroll' => '0'])->sum('value');

                $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
                $payrollCustomFields = PayrollSsetup::where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->where('status', 1)->orderBy('id', 'Asc')->get();
                $payrollCustomFields->transform(function ($payrollCustomFields) use ($payrollIds) {
                    $customFieldValue = CustomFieldHistory::where(['column_id' => $payrollCustomFields->id, 'is_mark_paid' => '0', 'is_next_payroll' => '0'])->whereIn('payroll_id', $payrollIds)->sum('value');
                    return [
                        'id' => $payrollCustomFields->id,
                        'field_name' => $payrollCustomFields->field_name ?? "",
                        'value' => $customFieldValue
                    ];
                });
            }
            $commission = $payrollData->commission;
            $override = $payrollData->override;
            $adjustment = $payrollData->adjustment;
            $reimbursement = $payrollData->reimbursement;
            $reconciliation = $payrollData->reconciliation;
            $deduction = (0 - $payrollData->deduction);
            $totalCustomFields = $sumCustomField;
            $hourly_salary  = $payrollData->hourly_salary;
            $overtime  = $payrollData->overtime;
            $payrollSum = $payrollData->net_pay;

            if ($payrollClass == Payroll::class) {
                $payRollByStates = $payrollClass::query()->select('states.name', DB::raw('sum(net_pay) as net_pay'))
                    ->leftJoin('users', 'users.id', 'payrolls.user_id')->leftJoin('states', 'states.id', 'users.state_id')
                    ->applyFrequencyFilter($param)->where(['payrolls.is_stop_payroll' => '0']);
            } else {
                $payRollByStates = $payrollClass::query()->select('states.name', DB::raw('sum(net_pay) as net_pay'))
                    ->leftJoin('users', 'users.id', 'payroll_history.user_id')->leftJoin('states', 'states.id', 'users.state_id')
                    ->applyFrequencyFilter($param);
            }
            $payRollByStates = $payRollByStates->groupBy('users.state_id')->get();
            $stateName = [];
            $stateTotal = 0;
            foreach ($payRollByStates as $payRollByState) {
                $stateTotal += $payRollByState->net_pay;
                $stateName[] = [
                    'state' => $payRollByState->name,
                    'total' => round($payRollByState->net_pay, 3),
                    'locationCustomField' => 0,
                    'state_total' => round($payRollByState->net_pay, 3)
                ];
            }

            if ($payrollClass == Payroll::class) {
                $payRollByPosition = $payrollClass::query()->with('positionDetail')->select('position_id', DB::raw('SUM(payrolls.net_pay) AS netPayTotal'), 'payrolls.id as payroll_id')
                    ->applyFrequencyFilter($param)->where(['is_stop_payroll' => '0']);
            } else {
                $payRollByPosition = $payrollClass::query()->with('positionDetail')->select('position_id', DB::raw('SUM(payroll_history.net_pay) AS netPayTotal'), 'payroll_history.id as payroll_id')
                    ->applyFrequencyFilter($param);
            }
            $payRollByPosition = $payRollByPosition->groupBy('position_id')->get();
            $payRollByPositionTotal = 0;
            $payRollByPosition = $payRollByPosition->transform(function ($position) use (&$payRollByPositionTotal) {
                $payRollByPositionTotal += $position->netPayTotal;
                return [
                    'position_name' => $position?->positionDetail?->position_name,
                    'total' => round($position->netPayTotal, 3),
                    'positionCustomField' => 0,
                    'position_total' => round($position->netPayTotal, 3)
                ];
            });
        }

        $priviesTotalPayroll = 0;
        if ($priviesStartDate) {
            $param = [
                "pay_frequency" => $frequencyTypeId,
                "worker_type" => $workerType,
                "pay_period_from" => $priviesStartDate,
                "pay_period_to" => $priviesEndDate
            ];
            $pervPayrollData = $newClass::query();
            $pervPayrollData = $pervPayrollData->select(DB::raw('SUM(net_pay) as net_pay'))->applyFrequencyFilter($param);
            if ($newClass == Payroll::class) {
                $pervPayrollData = $pervPayrollData->where('is_stop_payroll', '0');
            }
            $pervPayrollData = $pervPayrollData->first();
            $priviesTotalPayroll = $pervPayrollData->net_pay;
        }

        $currentPayroll = round($payrollSum, 3);
        $priviesPayroll = round($priviesTotalPayroll, 3);
        if ($currentPayroll && $priviesPayroll)
            $percentage = (($currentPayroll - $priviesPayroll) / $currentPayroll) * 100;
        else if ($currentPayroll)
            $percentage = 100;
        else if ($priviesPayroll)
            $percentage = -100;
        else
            $percentage = 0;

        $payroll['commission'] = round(($commission), 3);
        $payroll['override'] = round($override, 3);
        $payroll['adjustment'] = round($adjustment, 3);
        $payroll['reimbursement'] = round($reimbursement, 3);
        $payroll['reconciliation'] = round($reconciliation, 3);
        $payroll['deduction'] = round($deduction, 3);
        $payroll['hourly_salary'] = round($hourly_salary, 3);
        $payroll['overtime'] = round($overtime, 3);
        $payroll['custom_fields'] = $payrollCustomFields;
        $payroll['total_custom_fields'] = round($totalCustomFields, 3);
        $payroll['total_payroll_percentage'] = round($percentage, 3);
        $payroll['total_payroll'] = $payrollSum;
        $data['payroll'] = $payroll;
        $data['payroll_by_location'] = $stateName;
        $data['payroll_by_location_total_percentage'] = round($percentage, 3);
        $data['payroll_by_location_total'] = round($stateTotal, 3);
        $data['payroll_by_position'] = $payRollByPosition;
        $data['total_payroll_by_position_percentage'] = round($percentage, 3);
        $data['total_payroll_by_position'] = round($payRollByPositionTotal, 3);
        return response()->json([
            'ApiName' => 'Summary Payroll Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data
        ]);
    }
}

if (!function_exists('statusForClosePayroll')) {
    /**
     * Check if payroll can be closed for the given period
     * 
     * Validates that all active workers have been processed (marked as paid, moved to next payroll, or one-time payment).
     * Workers with stop_payroll = 1 are automatically excluded from validation since they will be 
     * deleted during the close process.
     * 
     * @param array $param Payroll parameters (pay_period_from, pay_period_to, pay_frequency, worker_type)
     * @return int 1 if can close, 0 if cannot close
     */
    function statusForClosePayroll($param)
    {
        if (Carbon::parse($param['pay_period_to'] . " 23:59:59")->isFuture()) {
            return 0;
        }

        // Exclude workers with stop_payroll = 1 from validation
        // These workers will be automatically deleted during the close process
        $payrollInfo = Payroll::whereHas('payrollUser', function ($q) {
            $q->where('stop_payroll', 0); // Only validate active workers
        })->applyFrequencyFilter($param)->selectRaw(
            "COUNT(*) as total_users,
            SUM(CASE WHEN is_mark_paid = 1 OR is_next_payroll = 1 OR is_onetime_payment = 1 THEN 1 ELSE 0 END) as processed_users"
        )->first();

        if ($payrollInfo && $payrollInfo->total_users == $payrollInfo->processed_users) {
            return 1;
        }
        return 0;
    }
}

if (!function_exists('calculateDeduction')) {
    function calculateDeduction($user, $param, $payroll = null)
    {
        $startDate = $param['pay_period_from'];
        $endDate = $param['pay_period_to'];
        $payFrequencyTypeId = $param['pay_frequency'];
        $workerType = $param['worker_type'];

        if ($payFrequencyTypeId == FrequencyType::DAILY_PAY_ID) {
            $subPositionId = $user->sub_position_id;
            if ($subPositionId) {
                $deductionStatus = PositionCommissionDeductionSetting::where(['position_id' => $subPositionId, 'status' => 1])->first();
                if ($deductionStatus) {
                    $positionDeductionLimit = PositionsDeductionLimit::where(['position_id' => $subPositionId])->first();
                    $totalDeduction = 0;
                    $deductionLimit = NULL;
                    if ($positionDeductionLimit) {
                        $deductionLimit = $positionDeductionLimit->limit_ammount;
                    }

                    $deductionRecords = [];
                    $deductions = PositionCommissionDeduction::where(['position_id' => $subPositionId])->get();
                    foreach ($deductions as $deduction) {
                        $deductionAmount = 0;
                        $payPeriods = getDatesFromToToday($deduction->pay_period_from);
                        foreach ($payPeriods as $payPeriod) {
                            $userDeductionHistory = UserDeductionHistory::select('user_id', 'cost_center_id', 'amount_par_paycheque')->with('costcenter:id,name,status')
                                ->whereHas('costcenter', function ($q) {
                                    $q->where('status', 1);
                                })->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->where('effective_date', '<=', $payPeriod)->orderBy('effective_date', 'DESC')->first();

                            if ($userDeductionHistory) {
                                $totalDeduction += $userDeductionHistory->amount_par_paycheque;
                                $deductionAmount += $userDeductionHistory->amount_par_paycheque;
                            }
                        }

                        $outstandingDeduction = PayrollDeductions::select('id', 'outstanding', 'cost_center_id')
                            ->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])
                            ->where('pay_period_to', '<', $startDate)->sum('outstanding');
                        $totalDeduction += isset($outstandingDeduction) ? round($outstandingDeduction, 2) : 0;
                        $deductionAmount += isset($outstandingDeduction) ? round($outstandingDeduction, 2) : 0;
                        $paidDeduction = PayrollDeductionLock::applyFrequencyFilter($param, ['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->sum('total');
                        if ($paidDeduction) {
                            $totalDeduction -= $paidDeduction;
                            $deductionAmount -= $paidDeduction;
                        }

                        $deductionRecords[] = [
                            'cost_center_id' => $deduction->cost_center_id,
                            'amount' => round($deductionAmount ?? 0, 2),
                            'limit' => $deductionLimit
                        ];
                    }

                    if (is_numeric($deductionLimit)) {
                        $userCommission = isset($payroll->commission) ? $payroll->commission : 0;
                        $userOverride = isset($payroll->override) ? $payroll->override : 0;
                        $totalSum = $userCommission + $userOverride;
                        $subTotal = (($totalSum) <= 0) ? 0 : round(($totalSum) * ($deductionLimit / 100), 2);
                        $subTotal = ($totalDeduction < $subTotal) ? $totalDeduction : $subTotal;
                    } else {
                        $subTotal = round($totalDeduction, 2);
                    }

                    foreach ($deductionRecords as $deductionRecord) {
                        $total = ($totalDeduction > 0) ? round($subTotal * ($deductionRecord['amount'] / $totalDeduction), 2) : 0;
                        $outstanding = $deductionRecord['amount'] - $total;

                        $payrollDeduction = PayrollDeductions::where(['pay_period_from' => $startDate, 'user_id' => $user->id, 'cost_center_id' => $deductionRecord['cost_center_id'], 'status' => 1])->first();
                        if ($payrollDeduction) {
                            $payrollDeduction->payroll_id = isset($payroll->id) ? $payroll->id : 0;
                            $payrollDeduction->amount = round($deductionRecord['amount'], 2);
                            $payrollDeduction->limit = $deductionRecord['limit'];
                            $payrollDeduction->total = round($total, 2);
                            $payrollDeduction->outstanding = round($outstanding, 2);
                            $payrollDeduction->subtotal = round($subTotal, 2);
                            $payrollDeduction->is_stop_payroll = $user->stop_payroll;
                            $payrollDeduction->user_worker_type = $user->worker_type;
                            $payrollDeduction->pay_frequency = $payFrequencyTypeId;
                            $payrollDeduction->save();
                        } else {
                            PayrollDeductions::create([
                                'pay_period_from' => $startDate,
                                'user_id' => $user->id,
                                'cost_center_id' => $deductionRecord['cost_center_id'],
                                'payroll_id' => isset($payroll->id) ? $payroll->id : 0,
                                'pay_period_to' => $endDate,
                                'amount' => round($deductionRecord['amount'], 2),
                                'limit' => $deductionRecord['limit'],
                                'total' => round($total, 2),
                                'outstanding' => round($outstanding, 2),
                                'subtotal' => round($subTotal, 2),
                                'is_stop_payroll' => $user->stop_payroll,
                                'user_worker_type' => $user->worker_type,
                                'pay_frequency' => $payFrequencyTypeId
                            ]);
                        }
                    }
                }
            }
        } else {
            $subPositionId = $user->sub_position_id;
            if ($subPositionId) {
                $deductionStatus = PositionCommissionDeductionSetting::where(['position_id' => $subPositionId, 'status' => 1])->first();
                if ($deductionStatus) {
                    $positionDeductionLimit = PositionsDeductionLimit::where(['position_id' => $subPositionId])->first();
                    $totalDeduction = 0;
                    $deductionLimit = NULL;
                    if ($positionDeductionLimit) {
                        $deductionLimit = $positionDeductionLimit->limit_ammount;
                    }
                    $deductionRecords = [];
                    $deductions = PositionCommissionDeduction::where(['position_id' => $subPositionId])->get();
                    foreach ($deductions as $deduction) {
                        $check = false;
                        if ($payFrequencyTypeId == FrequencyType::WEEKLY_ID) {
                            $weekly = WeeklyPayFrequency::when($user->worker_type == 'w2', function ($q) {
                                $q->where('w2_closed_status', 0);
                            })->when($user->worker_type == '1099', function ($q) {
                                $q->where('closed_status', 0);
                            })->where('pay_period_from', '>=', $deduction->pay_period_from)->orderBy('pay_period_from', 'ASC')->first();
                            if ($weekly && $weekly->pay_period_from == $startDate) {
                                $check = true;
                            }
                        } else if ($payFrequencyTypeId == FrequencyType::MONTHLY_ID) {
                            $month = MonthlyPayFrequency::when($user->worker_type == 'w2', function ($q) {
                                $q->where('w2_closed_status', 0);
                            })->when($user->worker_type == '1099', function ($q) {
                                $q->where('closed_status', 0);
                            })->where('pay_period_from', '>=', $deduction->pay_period_from)->orderBy('pay_period_from', 'ASC')->first();
                            if ($month && $month->pay_period_from == $startDate) {
                                $check = true;
                            }
                        } else if ($payFrequencyTypeId == FrequencyType::BI_WEEKLY_ID || $payFrequencyTypeId == FrequencyType::SEMI_MONTHLY_ID) {
                            $additionalFrequency = AdditionalPayFrequency::when($user->worker_type == 'w2', function ($q) {
                                $q->where('w2_closed_status', 0);
                            })->when($user->worker_type == '1099', function ($q) {
                                $q->where('closed_status', 0);
                            })->when($payFrequencyTypeId == FrequencyType::BI_WEEKLY_ID, function ($q) {
                                $q->where('type', '1');
                            })->when($payFrequencyTypeId == FrequencyType::SEMI_MONTHLY_ID, function ($q) {
                                $q->where('type', '2');
                            })->where('pay_period_from', '>=', $deduction->pay_period_from)->orderBy('pay_period_from', 'ASC')->first();
                            if ($additionalFrequency && $additionalFrequency->pay_period_from == $startDate) {
                                $check = true;
                            }
                        }

                        if (!$check) {
                            continue;
                        }

                        $userDeductionHistory = UserDeductionHistory::select('user_id', 'cost_center_id', 'amount_par_paycheque')->with('costcenter:id,name,status')
                            ->whereHas('costcenter', function ($q) {
                                $q->where('status', 1);
                            })->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->where('effective_date', '<=', $endDate)->orderBy('effective_date', 'DESC')->first();

                        $outstandingDeduction = PayrollDeductions::select('id', 'outstanding', 'cost_center_id')
                            ->where(['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->where('pay_period_from', '!=', $startDate)->where('pay_period_to', '!=', $endDate)->orderBy('id', 'DESC')->first();
                        $previousOutstanding = isset($outstandingDeduction->outstanding) ? round($outstandingDeduction->outstanding, 2) : 0;
                        if ($userDeductionHistory) {
                            $totalDeduction += $userDeductionHistory->amount_par_paycheque + (($previousOutstanding > 0) ? $previousOutstanding : 0);
                            $deductionAmount = $userDeductionHistory->amount_par_paycheque + (($previousOutstanding > 0) ? $previousOutstanding : 0);
                            $paidDeduction = PayrollDeductionLock::applyFrequencyFilter($param, ['user_id' => $user->id, 'cost_center_id' => $deduction->cost_center_id])->sum('total');
                            if ($paidDeduction) {
                                $totalDeduction -= $paidDeduction;
                                $deductionAmount -= $paidDeduction;
                            }
                        } else {
                            $totalDeduction += (($previousOutstanding > 0) ? $previousOutstanding : 0);
                            $deductionAmount = (($previousOutstanding > 0) ? $previousOutstanding : 0);
                        }

                        if ($deductionAmount > 0) {
                            $deductionRecords[] = [
                                'cost_center_id' => $deduction->cost_center_id,
                                'amount' => round($deductionAmount ?? 0, 2),
                                'limit' => $deductionLimit
                            ];
                        }
                    }

                    if (is_numeric($deductionLimit)) {
                        $userCommission = isset($payroll->commission) ? $payroll->commission : 0;
                        $userOverride = isset($payroll->override) ? $payroll->override : 0;
                        $totalSum = $userCommission + $userOverride;
                        $subTotal = (($totalSum) <= 0) ? 0 : round(($totalSum) * ($deductionLimit / 100), 2);
                        $subTotal = ($totalDeduction < $subTotal) ? $totalDeduction : $subTotal;
                    } else {
                        $subTotal = round($totalDeduction, 2);
                    }

                    foreach ($deductionRecords as $deductionRecord) {
                        $total = ($totalDeduction > 0) ? round($subTotal * ($deductionRecord['amount'] / $totalDeduction), 2) : 0;
                        $outstanding = $deductionRecord['amount'] - $total;

                        $payrollDeduction = PayrollDeductions::applyFrequencyFilter($param, ['user_id' => $user->id, 'cost_center_id' => $deductionRecord['cost_center_id'], 'status' => 1])->first();
                        if ($payrollDeduction) {
                            $payrollDeduction->payroll_id = isset($payroll->id) ? $payroll->id : 0;
                            $payrollDeduction->amount = round($deductionRecord['amount'], 2);
                            $payrollDeduction->limit = $deductionRecord['limit'];
                            $payrollDeduction->total = round($total, 2);
                            $payrollDeduction->outstanding = round($outstanding, 2);
                            $payrollDeduction->subtotal = round($subTotal, 2);
                            $payrollDeduction->is_stop_payroll = $user->stop_payroll;
                            $payrollDeduction->user_worker_type = $workerType;
                            $payrollDeduction->pay_frequency = $payFrequencyTypeId;
                            $payrollDeduction->save();
                        } else {
                            PayrollDeductions::create([
                                'pay_period_from' => $startDate,
                                'pay_period_to' => $endDate,
                                'user_id' => $user->id,
                                'cost_center_id' => $deductionRecord['cost_center_id'],
                                'payroll_id' => isset($payroll->id) ? $payroll->id : 0,
                                'amount' => round($deductionRecord['amount'], 2),
                                'limit' => $deductionRecord['limit'],
                                'total' => round($total, 2),
                                'outstanding' => round($outstanding, 2),
                                'subtotal' => round($subTotal, 2),
                                'is_stop_payroll' => $user->stop_payroll,
                                'user_worker_type' => $workerType,
                                'pay_frequency' => $payFrequencyTypeId
                            ]);
                        }
                    }
                }
            }
        }
    }
}

if (!function_exists('calculateSalary')) {
    /**
        * Calculate salary for a user based on pay period and frequency.
        *
        * This function calculates and stores the salary for a user for a specific
        * pay period. It handles both 1099 and W2 worker types and only processes
        * salary-type wages. The function will skip calculation if:
        * - Pay period is closed
        * - User data is incomplete (missing organization history, position wage, etc.)
        * - Position wage status is inactive
        *
        * @param User $user The user to calculate salary for
        * @param array $param Array containing:
        *   - 'pay_period_from' (string): Start date of pay period (Y-m-d format)
        *   - 'pay_period_to' (string): End date of pay period (Y-m-d format)
        *   - 'pay_frequency' (int): Pay frequency type ID (use FrequencyType constants)
        *   - 'worker_type' (string): Worker type ('1099' or 'w2')
        * @return bool Returns true on success, false on failure
        * @throws \Throwable Throws exception on database errors
        *
        * @example
        * $user = User::find(1);
        * $param = [
        *     'pay_period_from' => '2024-01-01',
        *     'pay_period_to' => '2024-01-15',
        *     'pay_frequency' => FrequencyType::BI_WEEKLY_ID,
        *     'worker_type' => 'w2'
        * ];
        * calculateSalary($user, $param);
    */
    function calculateSalary(User $user, array $param): void
    {
        try {
            DB::beginTransaction();
            $userId = $user->id;
            $startDate = $param['pay_period_from'];
            $endDate = $param['pay_period_to'];
            $payFrequencyTypeId = $param['pay_frequency'];
            $workerType = $param['worker_type'];

            if (!$payFrequencyTypeId) {
                DB::rollBack();
                Log::error('Pay frequency type is required');
                return;
            }

            if ($payFrequencyTypeId === FrequencyType::DAILY_PAY_ID) {
                DB::rollBack();
                Log::error('Daily pay frequency is not supported');
                return;
            }

            $userOrganizationHistory = UserOrganizationHistory::where('user_id', $userId)->where('effective_date', '<=', $startDate)->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
            if (!$userOrganizationHistory) {
                DB::rollBack();
                Log::error('User organization history not found');
                return;
            }

            $subPositionId = $userOrganizationHistory->sub_position_id;
            if (!$subPositionId) {
                DB::rollBack();
                Log::error('Sub position id not found');
                return;
            }

            $positionWage = PositionWage::where('position_id', $subPositionId)->where('effective_date', '<=', $startDate)->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
            if (!$positionWage) {
                $positionWage = PositionWage::where('position_id', $subPositionId)->whereNull('effective_date')->orderBy('id', 'desc')->first();
            }

            if (!$positionWage) {
                DB::rollBack();
                Log::error('Position wage not found');
                return;
            }

            if ($positionWage && $positionWage->wages_status == 0) {
                DB::rollBack();
                Log::error('Position wage status is 0');
                return;
            }

            $userWage = UserWagesHistory::where('effective_date', '<=', $startDate)->where('user_id', $userId)->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
            if ($userWage && $userWage->pay_type === 'Salary' && $endDate >= $user->period_of_agreement_start_date) {
                $date = date('Y-m-d');
                $stopPayroll = $user->stop_payroll;

                $frequencyConfig = getFrequencyClassByType($payFrequencyTypeId);
                $class = $frequencyConfig['class'];
                $type = $frequencyConfig['type'];

                if (!isset($class)) {
                    DB::rollBack();
                    Log::error('Class not found');
                    return;
                }

                $frequency = $class::query();
                $frequency = $frequency->where(["pay_period_from" => $startDate, "pay_period_to" => $endDate]);
                if ($payFrequencyTypeId === FrequencyType::BI_WEEKLY_ID || $payFrequencyTypeId === FrequencyType::SEMI_MONTHLY_ID) {
                    $frequency = $frequency->where('type', $type);
                }
                $frequency = $frequency->first();
                if (!$frequency) {
                    DB::rollBack();
                    Log::error('Frequency not found');
                    return;
                }

                if ($workerType === 'w2' && $frequency->w2_closed_status == 1) {
                    DB::rollBack();
                    Log::error('Worker type is w2 and frequency is closed');
                    return;
                }

                if ($workerType === '1099' && $frequency->closed_status == 1) {
                    DB::rollBack();
                    Log::error('Worker type is 1099 and frequency is closed');
                    return;
                }

                $totalRate = $userWage->pay_rate ?? 0;
                PayrollHourlySalary::updateOrCreate(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'pay_frequency' => $payFrequencyTypeId, 'user_worker_type' => $workerType, 'status' => 1], [
                    'position_id' => $subPositionId,
                    'date' => $date,
                    'salary' => $totalRate,
                    'total' => $totalRate,
                    'is_stop_payroll' => $stopPayroll
                ]);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Calculate salary failed: ' . $e->getMessage());
            return;
        }
    }
}

if (!function_exists('getFrequencyClassByType')) {
    /**
        * Get the frequency class and type based on frequency type ID.
        *
        * Maps frequency type IDs to their corresponding Eloquent model classes
        * and type constants. Used to determine which model to query for pay
        * frequency information.
        *
        * @param int $payFrequencyTypeId The frequency type ID (FrequencyType constant)
        * @return array Returns array with 'class' and 'type' keys:
        *   - 'class' (string|null): Fully qualified class name or null if unknown
        *   - 'type' (string|null): Type constant for bi-weekly/semi-monthly or null
        *
        * @example
        * $config = getFrequencyClassByType(FrequencyType::WEEKLY_ID);
        * // Returns: ['class' => WeeklyPayFrequency::class, 'type' => null]
    */
    function getFrequencyClassByType(int $payFrequencyTypeId): array
    {
        return match ($payFrequencyTypeId) {
            FrequencyType::WEEKLY_ID => [
                'class' => WeeklyPayFrequency::class,
                'type' => null,
            ],
            FrequencyType::MONTHLY_ID => [
                'class' => MonthlyPayFrequency::class,
                'type' => null,
            ],
            FrequencyType::BI_WEEKLY_ID => [
                'class' => AdditionalPayFrequency::class,
                'type' => AdditionalPayFrequency::BI_WEEKLY_TYPE,
            ],
            FrequencyType::SEMI_MONTHLY_ID => [
                'class' => AdditionalPayFrequency::class,
                'type' => AdditionalPayFrequency::SEMI_MONTHLY_TYPE,
            ],
            default => ['class' => null, 'type' => null],
        };
    }
}


if (!function_exists('moveToNextPayrollData')) {
    function moveToNextPayrollData($param, $nextPeriod)
    {
        $finalPayrolls = Payroll::with('payrollUser')->applyFrequencyFilter($param)->get();
        $tablesToUpdate = ['UserCommission', 'UserOverrides', 'ClawbackSettlement',  'ApprovalsAndRequest', 'PayrollAdjustmentDetail', 'PayrollHourlySalary', 'PayrollOvertime', 'CustomField'];
        foreach ($finalPayrolls as $finalPayroll) {
            PayrollDeductions::where(['payroll_id' => $finalPayroll->id, 'is_next_payroll' => 1, 'is_onetime_payment' => 0])->update(['outstanding' => DB::raw('COALESCE(amount, 0) - COALESCE(total, 0)')]);

            if ($finalPayroll->is_next_payroll || $finalPayroll?->payrollUser?->stop_payroll) {
                $where = ['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0];
            } else {
                $where = ['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0, 'is_next_payroll' => 1];
            }

            foreach ($tablesToUpdate as $table) {
                $fullClassName = 'App\Models\\' . $table;
                $modelInstance = new $fullClassName;
                // 1️⃣ Freeze IDs to avoid chunk mutation issues
                $ids = $modelInstance::where($where)->pluck('id');

                $modelInstance::whereIn('id', $ids)->orderBy('id')
                    ->chunkById(5, function ($records) use ($table, $nextPeriod, $param, $finalPayroll) {
                        try {
                            DB::transaction(function () use ($records, $table, $nextPeriod, $param, $finalPayroll) {
                                foreach ($records as $modelDetail) {
                                    $nextFromDate = $nextPeriod['pay_period_from'];
                                    $nextToDate = $nextPeriod['pay_period_to'];

                                    $nextParam = [
                                        'pay_frequency' => $param['pay_frequency'],
                                        'worker_type' => $param['worker_type'],
                                        'pay_period_from' => $nextFromDate,
                                        'pay_period_to' => $nextToDate
                                    ];

                                    // 2️⃣ Lock payroll creation to avoid duplicates
                                    $nextPayroll = Payroll::applyFrequencyFilter($nextParam, [
                                        'user_id' => $finalPayroll->user_id,
                                        'status' => 1,
                                        'finalize_status' => 0
                                    ])->lockForUpdate()->first();

                                    if (!$nextPayroll) {
                                        $nextPayroll = Payroll::create([
                                            'user_id' => $finalPayroll->user_id,
                                            'pay_frequency' => $finalPayroll->pay_frequency,
                                            'worker_type' => $finalPayroll->worker_type,
                                            'position_id' => $finalPayroll->position_id,
                                            'pay_period_from' => $nextFromDate,
                                            'pay_period_to' => $nextToDate,
                                            'status' => 1
                                        ]);
                                    }

                                    // 3️⃣ Handle CustomField safely
                                    if ($table === 'CustomField') {
                                        $amount = (float) ($modelDetail->value ?? 0);
                                        $existing = CustomField::where([
                                            'payroll_id' => $nextPayroll->id,
                                            'column_id'  => $modelDetail->column_id
                                        ])->lockForUpdate()->first();

                                        if ($existing) {
                                            $amount = $amount + ($existing->value ?? 0);
                                        }

                                        $modelDetail->value = $amount;
                                    }

                                    // 4️⃣ Common mutations (safe)
                                    $modelDetail->payroll_id = $nextPayroll->id;
                                    $modelDetail->is_next_payroll = 0;
                                    $modelDetail->pay_period_from = $nextFromDate;
                                    $modelDetail->pay_period_to = $nextToDate;

                                    // 5️⃣ ref_id protection
                                    if (isset($modelDetail->ref_id) && (empty($modelDetail->ref_id) || $modelDetail->ref_id == 0)) {
                                        $common = PayrollCommon::updateOrCreate(
                                            [
                                                'orig_payfrom' => $finalPayroll->pay_period_from,
                                                'orig_payto' => $finalPayroll->pay_period_to
                                            ]
                                        );

                                        $modelDetail->ref_id = $common->id;
                                    }

                                    $modelDetail->save();
                                }
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in moveToNextPayrollData', [
                                'table' => $table,
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });
            }
        }

        Payroll::applyFrequencyFilter($param, ['is_next_payroll' => 1])->delete();
        Payroll::whereHas('payrollUser', function ($q) {
            $q->where('stop_payroll', '1');
        })->applyFrequencyFilter($param)->delete();

        return [
            'status' => true,
            'message' => 'Payroll moved to next pay period successfully'
        ];
    }
}

if (!function_exists('markAsPaidPayrollData')) {
    function markAsPaidPayrollData($param, $nextPeriod, $paymentDetail = [])
    {
        $advanceSetting = AdvancePaymentSetting::first();
        $finalPayrolls = Payroll::with('payrollUser')->applyFrequencyFilter($param, ['is_onetime_payment' => 0])->get();
        foreach ($finalPayrolls as $finalPayroll) {
            processPayrollData($finalPayroll, $nextPeriod, $advanceSetting, $paymentDetail);
        }

        return [
            'status' => true,
            'message' => 'Payroll marked as paid successfully'
        ];
    }
}

if (!function_exists('processPayrollData')) {
    function processPayrollData($finalPayroll, $nextPeriod, $advanceSetting, $paymentDetail)
    {
        // Wrap all database operations in a transaction to prevent race conditions
        // This ensures all writes are atomic and pay stub generation reads consistent data
        // DB::transaction() automatically handles:
        //   - COMMIT: if closure completes successfully
        //   - ROLLBACK: if exception is thrown inside closure
        try {
            DB::transaction(function () use ($finalPayroll, $nextPeriod, $advanceSetting, $paymentDetail) {
                PayrollDeductions::where(['payroll_id' => $finalPayroll->id, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])->update(['outstanding' => DB::raw('COALESCE(amount, 0) - COALESCE(total, 0)')]);
                PayrollHistory::updateOrCreate(['payroll_id' => $finalPayroll->id], [
                    'user_id' => $finalPayroll->user_id,
                    'position_id' => $finalPayroll->position_id,
                    'hourly_salary' => $finalPayroll->hourly_salary,
                    'overtime' => $finalPayroll->overtime,
                    'commission' => $finalPayroll->commission,
                    'override' => $finalPayroll->override,
                    'reimbursement' => $finalPayroll->reimbursement,
                    'clawback' => $finalPayroll->clawback,
                    'deduction' => $finalPayroll->deduction,
                    'adjustment' => $finalPayroll->adjustment,
                    'reconciliation' => $finalPayroll->reconciliation,
                    'custom_payment' => $finalPayroll->custom_payment,
                    'net_pay' => $finalPayroll->net_pay,
                    'pay_period_from' => $finalPayroll->pay_period_from,
                    'pay_period_to' => $finalPayroll->pay_period_to,
                    'status' => '3',
                    'pay_frequency_date' => $finalPayroll->created_at,
                    'worker_type' => $finalPayroll->worker_type,
                    'pay_frequency' => $finalPayroll->pay_frequency,
                    'everee_status' => isset($paymentDetail['everee_status']) ? $paymentDetail['everee_status'] : 0,
                    'everee_payment_status' => isset($paymentDetail['everee_status']) ? $paymentDetail['everee_status'] : 0,
                    'pay_type' => isset($paymentDetail['pay_type']) ? $paymentDetail['pay_type'] : 'Manualy',
                    'everee_external_id' => isset($paymentDetail['everee_external_id']) ? $paymentDetail['everee_external_id'] : NULL,
                    'everee_paymentId' => isset($paymentDetail['everee_paymentId']) ? $paymentDetail['everee_paymentId'] : NULL,
                    'everee_payment_requestId' => isset($paymentDetail['everee_payment_requestId']) ? $paymentDetail['everee_payment_requestId'] : NULL,
                    'everee_json_response' => isset($paymentDetail['everee_json_response']) ? $paymentDetail['everee_json_response'] : NULL,
                ]);

                // Only mark non-zero amounts as paid (status = 3)
                $where = ['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0];

                // UserCommission - only mark non-zero amounts as paid
                UserCommission::where($where)
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // UserOverrides - only mark non-zero amounts as paid
                UserOverrides::where($where)
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // ClawbackSettlement - only mark non-zero clawback amounts as paid
                ClawbackSettlement::where($where)
                    ->where(function ($q) {
                        $q->whereNotNull('clawback_amount')->where('clawback_amount', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // PayrollAdjustmentDetail - only mark non-zero amounts as paid
                PayrollAdjustmentDetail::where($where)
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // PayrollHourlySalary - only mark non-zero totals as paid
                PayrollHourlySalary::where($where)
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // PayrollOvertime - only mark non-zero totals as paid
                PayrollOvertime::where($where)
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // PayrollDeductions - only mark non-zero totals as paid
                PayrollDeductions::where(['payroll_id' => $finalPayroll->id, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })
                    ->update(['status' => 3]);

                // ApprovalsAndRequest - only mark non-zero amounts as paid (excluding specific adjustment types)
                ApprovalsAndRequest::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0])
                    ->whereNotIn('adjustment_type_id', [7, 8, 9])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })
                    ->update(['status' => 'Paid']);
                $requestAndApprovals = ApprovalsAndRequest::where(['status' => 'Paid', 'payroll_id' => $finalPayroll->id])->get();
                foreach ($requestAndApprovals as $requestAndApproval) {
                    $childRequest = ApprovalsAndRequest::where(['parent_id' => $requestAndApproval->parent_id, 'status' => 'Paid'])->sum('amount');
                    $parentRequest = ApprovalsAndRequest::where(['id' => $requestAndApproval->parent_id, 'status' => 'Accept'])->sum('amount');
                    if ($childRequest == $parentRequest) {
                        ApprovalsAndRequest::where(['id' => $requestAndApproval->parent_id])->update(['status' => 'Paid']);
                    }
                }

                // Define amount column mapping for filtering zero amounts
                $modelAmountColumns = [
                    UserCommission::class => 'amount',
                    UserOverrides::class => 'amount',
                    ClawbackSettlement::class => 'clawback_amount',
                    PayrollAdjustmentDetail::class => 'amount',
                    PayrollHourlySalary::class => 'total',
                    PayrollOvertime::class => 'total'
                ];

                $modelToLocks = [
                    UserCommission::class => UserCommissionLock::class,
                    UserOverrides::class => UserOverridesLock::class,
                    ClawbackSettlement::class => ClawbackSettlementLock::class,
                    PayrollAdjustmentDetail::class => PayrollAdjustmentDetailLock::class,
                    PayrollHourlySalary::class => PayrollHourlySalaryLock::class,
                    PayrollOvertime::class => PayrollOvertimeLock::class
                ];

                foreach ($modelToLocks as $model => $modelToLock) {
                    $amountColumn = $modelAmountColumns[$model];

                    $model::where($where)
                        ->whereNotNull($amountColumn)
                        ->where($amountColumn, '!=', 0)
                        ->chunkById(5, function ($records) use ($modelToLock, $model, $finalPayroll) {
                            try {
                                $records->each(function ($value) use ($modelToLock) {
                                    $modelToLock::updateOrCreate(
                                        ['id' => $value->id],
                                        $value->toArray()
                                    );
                                });
                            } catch (\Exception $e) {
                                $recordIds = $records->pluck('id')->toArray();
                                Log::error('Failed to process chunk in processPayrollData - modelToLock', [
                                    'model' => $model,
                                    'lock_model' => $modelToLock,
                                    'payroll_id' => $finalPayroll->id,
                                    'user_id' => $finalPayroll->user_id,
                                    'record_ids' => $recordIds,
                                    'chunk_size' => $records->count(),
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine()
                                ]);
                                // Continue processing other chunks even if this one fails
                            }
                        });
                }

                // PayrollDeductions - only copy non-zero totals
                PayrollDeductions::where(['payroll_id' => $finalPayroll->id, 'is_next_payroll' => 0, 'is_onetime_payment' => 0])
                    ->where(function ($q) {
                        $q->whereNotNull('total')->where('total', '!=', 0);
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                PayrollDeductionLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - PayrollDeductions', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                // ApprovalsAndRequest - only copy non-zero amounts
                ApprovalsAndRequest::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ApprovalsAndRequestLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ApprovalsAndRequest', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                $customFieldRecords = CustomField::where(['payroll_id' => $finalPayroll->id])->where(function ($q) {
                    $q->whereNotNull('value')->where('value', '!=', 0);
                })->get();
                $customFieldRecords->each(function ($value) {
                    $data = $value->only(['user_id', 'payroll_id', 'column_id', 'value', 'comment', 'approved_by', 'is_mark_paid', 'is_next_payroll', 'pay_period_from', 'pay_period_to']);
                    CustomFieldHistory::updateOrCreate(['payroll_id' => $value->payroll_id, 'user_id' => $value->user_id, 'column_id' => $value->column_id, 'is_onetime_payment' => 0], $data);
                });
                CustomField::where(['payroll_id' => $finalPayroll->id])->delete();

                $nextFromDate = NULL;
                $nextToDate = NULL;
                $advanceRequestStatus = "Approved";
                if ($advanceSetting && $advanceSetting->adwance_setting == 'automatic') {
                    $nextFromDate = $nextPeriod['pay_period_from'];
                    $nextToDate = $nextPeriod['pay_period_to'];
                    $advanceRequestStatus = "Accept";
                }

                $approvalAndRequests = ApprovalsAndRequest::where(['payroll_id' => $finalPayroll->id, 'status' => 'Paid', 'adjustment_type_id' => 4, 'is_onetime_payment' => 0])->where('amount', '>', 0)->whereNotNull('req_no')->get();
                foreach ($approvalAndRequests as $approvalAndRequest) {
                    $description = null;
                    if (!empty($approvalAndRequest->req_no)) {
                        $description = 'Advance payment request Id: ' . $approvalAndRequest->req_no . ' Date of request: ' . date("m/d/Y");
                    }

                    if (!ApprovalsAndRequest::where(['parent_id' => $approvalAndRequest->id])->first()) {
                        ApprovalsAndRequest::create([
                            'user_id' => $approvalAndRequest->user_id,
                            'parent_id' => $approvalAndRequest->id,
                            'manager_id' => $approvalAndRequest->manager_id,
                            'approved_by' => $approvalAndRequest->approved_by,
                            'adjustment_type_id' => $approvalAndRequest->adjustment_type_id,
                            'state_id' => $approvalAndRequest->state_id,
                            'dispute_type' => $approvalAndRequest->dispute_type,
                            'customer_pid' => $approvalAndRequest->customer_pid,
                            'cost_tracking_id' => $approvalAndRequest->cost_tracking_id,
                            'cost_date' => $approvalAndRequest->cost_date,
                            'request_date' => $approvalAndRequest->request_date,
                            'amount' => (0 - $approvalAndRequest->amount),
                            'status' => $advanceRequestStatus,
                            'description' => $description,
                            'pay_period_from' => isset($nextFromDate) ? $nextFromDate : NULL,
                            'pay_period_to' => isset($nextToDate) ? $nextToDate : NULL,
                            'user_worker_type' => $approvalAndRequest->user_worker_type,
                            'pay_frequency' => $approvalAndRequest->pay_frequency
                        ]);
                    }
                }

                ReconciliationFinalizeHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3]);
                ReconciliationFinalizeHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0, "payroll_execute_status" => 3])
                    ->where(function ($q) {
                        $q->whereNotNull('net_amount')->where('net_amount', '!=', 0);
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ReconciliationFinalizeHistoryLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ReconciliationFinalizeHistory', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                UserReconciliationCommission::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0])->update(['status' => 'paid']);
                UserReconciliationCommission::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0, 'status' => 'paid'])
                    ->where(function ($q) {
                        $q->where(function ($query) {
                            $query->whereNotNull('amount')->where('amount', '!=', 0);
                        })->orWhere(function ($query) {
                            $query->whereNotNull('total_due')->where('total_due', '!=', 0);
                        });
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                UserReconciliationCommissionLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - UserReconciliationCommission', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                ReconCommissionHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3]);
                ReconCommissionHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0, 'payroll_execute_status' => 3])
                    ->where(function ($q) {
                        $q->where(function ($query) {
                            $query->whereNotNull('total_amount')->where('total_amount', '!=', 0);
                        })->orWhere(function ($query) {
                            $query->whereNotNull('paid_amount')->where('paid_amount', '!=', 0);
                        });
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ReconCommissionHistoryLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ReconCommissionHistory', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                ReconOverrideHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3]);
                ReconOverrideHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0, 'payroll_execute_status' => 3])
                    ->where(function ($q) {
                        $q->where(function ($query) {
                            $query->whereNotNull('total_amount')->where('total_amount', '!=', 0);
                        })->orWhere(function ($query) {
                            $query->whereNotNull('paid')->where('paid', '!=', 0);
                        });
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ReconOverrideHistoryLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ReconOverrideHistory', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                ReconClawbackHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3]);
                ReconClawbackHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0, 'payroll_execute_status' => 3])
                    ->where(function ($q) {
                        $q->whereNotNull('paid_amount')->where('paid_amount', '!=', 0);
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ReconClawbackHistoryLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ReconClawbackHistory', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                ReconAdjustment::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0])->update(["payroll_execute_status" => 3]);
                ReconAdjustment::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0, 'payroll_execute_status' => 3])
                    ->where(function ($q) {
                        $q->whereNotNull('adjustment_amount')->where('adjustment_amount', '!=', 0);
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ReconAdjustmentLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ReconAdjustment', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                ReconDeductionHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0])->update(["payroll_executed_status" => "3"]);
                ReconDeductionHistory::where(['payroll_id' => $finalPayroll->id, 'status' => "payroll", 'is_onetime_payment' => 0, 'payroll_executed_status' => 3])
                    ->where(function ($q) {
                        $q->whereNotNull('amount')->where('amount', '!=', 0);
                    })->chunkById(5, function ($records) use ($finalPayroll) {
                        try {
                            $records->each(function ($value) {
                                ReconDeductionHistoryLock::updateOrCreate(
                                    ['id' => $value->id],
                                    $value->toArray()
                                );
                            });
                        } catch (\Exception $e) {
                            $recordIds = $records->pluck('id')->toArray();
                            Log::error('Failed to process chunk in processPayrollData - ReconDeductionHistory', [
                                'payroll_id' => $finalPayroll->id,
                                'user_id' => $finalPayroll->user_id,
                                'record_ids' => $recordIds,
                                'chunk_size' => $records->count(),
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine()
                            ]);
                            // Continue processing other chunks even if this one fails
                        }
                    });

                UserCommission::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0, 'is_move_to_recon' => 1])->update(['payroll_id' => 0, 'pay_period_from' => NULL, 'pay_period_to' => NULL, 'settlement_type' => 'reconciliation']);
                UserOverrides::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0, 'is_move_to_recon' => 1])->update(['payroll_id' => 0, 'pay_period_from' => NULL, 'pay_period_to' => NULL, 'overrides_settlement_type' => 'reconciliation']);
                ClawbackSettlement::where(['payroll_id' => $finalPayroll->id, 'is_onetime_payment' => 0, 'is_move_to_recon' => 1])->update(['payroll_id' => 0, 'pay_period_from' => NULL, 'pay_period_to' => NULL, 'clawback_type' => 'reconciliation']);
            });
            // Transaction automatically commits here if no exception was thrown
        } catch (\Illuminate\Database\QueryException $e) {
            // Transaction has already been rolled back automatically by Laravel
            // Log the error for debugging
            Log::error('Database transaction failed in processPayrollData', [
                'payroll_id' => $finalPayroll->id ?? null,
                'user_id' => $finalPayroll->user_id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to allow caller to handle
        } catch (\Exception $e) {
            // Transaction has already been rolled back automatically by Laravel
            // Log the error for debugging
            Log::error('Unexpected error in processPayrollData', [
                'payroll_id' => $finalPayroll->id ?? null,
                'user_id' => $finalPayroll->user_id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to allow caller to handle
        }
    }
}

if (!function_exists("payrollFinalizeValidations")) {
    function payrollFinalizeValidations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "pay_period_from" => "required|date_format:Y-m-d",
            "pay_period_to" => "required|date_format:Y-m-d",
            "pay_frequency" => "required|in:" . FrequencyType::WEEKLY_ID . "," . FrequencyType::MONTHLY_ID . "," . FrequencyType::BI_WEEKLY_ID . "," . FrequencyType::SEMI_MONTHLY_ID . "," . FrequencyType::DAILY_PAY_ID,
            "worker_type" => "required|in:1099,w2"
        ]);

        if ($validator->fails()) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize-payroll',
                'error' => $validator->errors(),
                'code' => 400
            ];
        }

        $payFrequency = $request->pay_frequency;
        $payPeriodFrom = $request->pay_period_from;
        $payPeriodTo = $request->pay_period_to;
        $workerType = strtolower($request->worker_type);

        if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
            $validator = Validator::make($request->all(), [
                'pay_period_from' => 'required|date_format:Y-m-d|before_or_equal:today',
                'pay_period_to' => 'required|date_format:Y-m-d|before_or_equal:today'
            ]);

            if ($validator->fails()) {
                return [
                    'status' => false,
                    'success' => false,
                    'ApiName' => 'finalize-payroll',
                    'error' => $validator->errors(),
                    'code' => 400
                ];
            }
        }

        if ($payFrequency == FrequencyType::DAILY_PAY_ID && ($workerType == 'w2' || $workerType == 'W2')) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize-payroll',
                'message' => 'We do not support daily pay payroll with w2 users!!',
                'code' => 400
            ];
        }

        $legacySheet = LegacyWeeklySheet::orderBy('id', 'DESC')->first();
        if ($legacySheet && $legacySheet->in_process == '1') {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize_payroll',
                'message' => "We regret to inform you that we're unable to process your request to finalize payroll at this time. Our system is currently engaged in the Sale import process. Please try again later. Thank you for your understanding and patience",
                'code' => 400
            ];
        }

        if ($workerType == 'w2' || $workerType == 'W2') {
            $setting = SchedulingApprovalSetting::first();
            if (!$setting) {
                return [
                    'status' => false,
                    'is_show' => false,
                    'success' => false,
                    'ApiName' => 'finalize-payroll',
                    'message' => 'Scheduling approval settings not configured.',
                    'code' => 400
                ];
            }

            $user = auth()->user();
            $groupId = $user->group_id;
            if ($user->is_super_admin == 1 || $setting->scheduling_setting == 'automatic') {
                $show = GroupPermissions::where('group_id', $groupId)->whereIn('role_id', GroupPermissions::distinct()->pluck('role_id'))
                    ->whereIn('permissions_id', function ($query) {
                        $query->select('id')->from('permissions')->where('name', 'scheduling-timesheet-approval');
                    })->exists();

                if (!$show) {
                    return [
                        'status' => false,
                        'is_show' => false,
                        'success' => false,
                        'ApiName' => 'finalize-payroll',
                        'message' => 'You do not have the necessary permissions for timesheet approval, or the scheduling setting is set to manual.',
                        'code' => 400
                    ];
                }
            }
        }

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];
        $checkOtherFinalizedPayroll = Payroll::where('pay_period_from', '!=', $param['pay_period_from'])->where('pay_period_to', '!=', $param['pay_period_to'])->where(['status' => 2, 'is_onetime_payment' => 0])->first();
        if ($checkOtherFinalizedPayroll) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize-payroll',
                'message' => 'Warning: Another payroll finalized for the period ' . date('m/d/Y', strtotime($checkOtherFinalizedPayroll->pay_period_from)) . ' to ' . date('m/d/Y', strtotime($checkOtherFinalizedPayroll->pay_period_to)),
                'code' => 400
            ];
        }

        $deduction = payrollDeductionCalculation($param);
        if ($deduction['status'] == false) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize-payroll',
                'message' => $deduction['message'],
                'code' => 400
            ];
        }

        payrollRemoveDuplicateData($request);
        $payrolls = Payroll::applyFrequencyFilter($param)->get();
        foreach ($payrolls as $payroll) {
            reCalculatePayrollData($payroll->id, $param);
        }
        payrollRemoveZeroData($request); // REMOVES PAYROLL DATA WHERE TOTAL IS 0

        if (Payroll::whereHas('payrollUser', function ($q) {
            $q->where('stop_payroll', 0);
        })->applyFrequencyFilter($param, ['status' => 1, 'is_onetime_payment' => 0])->whereRaw('(COALESCE(net_pay, 0) - COALESCE(subtract_amount, 0)) < 0')->count()) {
            return [
                'status' => false,
                'success' => false,
                'ApiName' => 'finalize-payroll',
                'message' => 'Error: The Net Pay, excluding Reimbursements should not be negative during the selected Pay Period. Kindly adjust to ensure that the Net Pay excluding reimbursements is a positive value.',
                'code' => 400
            ];
        }

        return ['success' => true];
    }
}

if (!function_exists("payrollDeductionCalculation")) {
    function payrollDeductionCalculation($param)
    {
        try {
            $endDate = $param['pay_period_to'];
            $payFrequencyTypeId = $param['pay_frequency'];
            $workerType = $param['worker_type'];

            $users = User::select('id', 'sub_position_id', 'stop_payroll')->whereIn('sub_position_id', function ($query) use ($payFrequencyTypeId) {
                $query->select('position_id')->from('position_pay_frequencies')->where('frequency_type_id', $payFrequencyTypeId);
            })->where('is_super_admin', '!=', '1')->where(DB::raw('DATE_FORMAT(period_of_agreement_start_date, "%Y-%m-%d")'), '<=', $endDate)->where('worker_type', $workerType)->get();

            $payrollData = [];
            $payrolls = Payroll::applyFrequencyFilter($param)->get();
            foreach ($payrolls as $payroll) {
                $payrollData[$payroll->user_id] = $payroll;
            }

            foreach ($users as $user) {
                $payroll = isset($payrollData[$user->user_id]) ? $payrollData[$user->user_id] : null;
                calculateDeduction($user, $param, $payroll);
            }

            return [
                'status' => true,
                'message' => 'Deduction calculated successfully'
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => $e->getMessage() . ' ' . $e->getLine()
            ];
        }
    }
}

if (!function_exists("payrollCalculateNetPay")) {
    function payrollCalculateNetPay($payrollId)
    {
        Payroll::where('id', $payrollId)->update([
            'net_pay' => DB::raw('
                    COALESCE(commission, 0) +
                    COALESCE(override, 0) +
                    COALESCE(adjustment, 0) +
                    COALESCE(reimbursement, 0) +
                    COALESCE(reconciliation, 0) +
                    COALESCE(hourly_salary, 0) +
                    COALESCE(overtime, 0) +
                    COALESCE(custom_payment, 0) -
                    COALESCE(deduction, 0)
                ')
        ]);
    }
}

if (!function_exists("calculateSubtractAmount")) {
    function calculateSubtractAmount($payrollId)
    {
        Payroll::where('id', $payrollId)->update([
            'subtract_amount' => DB::raw('COALESCE(reimbursement, 0)')
        ]);
    }
}

if (!function_exists("payrollHistoryYTDCalculation")) {
    function payrollHistoryYTDCalculation($userId, $payPeriodFrom, $payPeriodTo)
    {
        $salaryYTD = PayrollHourlySalaryLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('total') ?? 0;
        $overtimeYTD = PayrollOvertimeLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('total') ?? 0;
        $commissionYTD = UserCommissionLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('amount') ?? 0;
        $commissionClawBackYTD = ClawbackSettlementLock::where(['user_id' => $userId, 'type' => 'commission', 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('clawback_amount') ?? 0;
        $overrideYTD = UserOverridesLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('amount') ?? 0;
        $overrideClawBackYTD = ClawbackSettlementLock::where(['user_id' => $userId, 'type' => 'overrides', 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('clawback_amount') ?? 0;
        $adjustmentYTD = PayrollAdjustmentDetailLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('amount') ?? 0;
        $approvalsAndRequestYTD = ApprovalsAndRequestLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->where('payroll_id', '!=', 0)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount') ?? 0;
        $fineAndFeeYTD = ApprovalsAndRequestLock::where(['user_id' => $userId, 'adjustment_type_id' => 5, 'is_onetime_payment' => 0])->where('payroll_id', '!=', 0)->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('amount') ?? 0;
        $reimbursementYTD = ApprovalsAndRequestLock::where(['user_id' => $userId, 'adjustment_type_id' => 2, 'is_onetime_payment' => 0])->where('payroll_id', '!=', 0)->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('amount') ?? 0;
        $deductionYTD = PayrollDeductionLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('total') ?? 0;
        $w2DeductionYTD = W2PayrollTaxDeduction::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('fica_tax') ?? 0;
        $reconciliationYTD = ReconciliationFinalizeHistoryLock::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('net_amount') ?? 0;
        $customFieldYTD = CustomFieldHistory::where(['user_id' => $userId, 'is_onetime_payment' => 0])->where('pay_period_to', '<=', $payPeriodTo)->whereYear('pay_period_from', date('Y', strtotime($payPeriodFrom)))->sum('value') ?? 0;

        $oneTimePaymentIds = OneTimePayments::where(['user_id' => $userId, 'from_payroll' => 0])->where('pay_date', '<=', date('Y-m-d', strtotime($payPeriodTo)))->whereYear('pay_date', date('Y', strtotime($payPeriodFrom)))->pluck('id');
        $oneTimeReimbursementYTD = OneTimePayments::whereIn('id', $oneTimePaymentIds)->where('adjustment_type_id', 2)->sum('amount') ?? 0;
        $oneTimeAdjustmentYTD = OneTimePayments::whereIn('id', $oneTimePaymentIds)->where('adjustment_type_id', '!=', 2)->sum('amount') ?? 0;
        $oneTimeW2DeductionYTD = W2PayrollTaxDeduction::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('fica_tax') ?? 0;

        $oneTimePaymentIds = OneTimePayments::where(['user_id' => $userId, 'from_payroll' => 1])->where('pay_date', '<=', date('Y-m-d', strtotime($payPeriodTo)))->whereYear('pay_date', date('Y', strtotime($payPeriodFrom)))->pluck('id');
        $oneTimePayrollSalaryYTD = PayrollHourlySalaryLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('total') ?? 0;
        $oneTimePayrollOvertimeYTD = PayrollOvertimeLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('total') ?? 0;
        $oneTimePayrollCommissionYTD = UserCommissionLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('amount') ?? 0;
        $oneTimePayrollCommissionClawBackYTD = ClawbackSettlementLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->where(['type' => 'commission'])->sum('clawback_amount') ?? 0;
        $oneTimePayrollOverrideYTD = UserOverridesLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('amount') ?? 0;
        $oneTimePayrollOverrideClawBackYTD = ClawbackSettlementLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->where(['type' => 'overrides'])->sum('clawback_amount') ?? 0;
        $oneTimePayrollAdjustmentYTD = PayrollAdjustmentDetailLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('amount') ?? 0;
        $oneTimePayrollApprovalsAndRequestYTD = ApprovalsAndRequestLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->where('payroll_id', '!=', 0)->whereNotIn('adjustment_type_id', [2, 5, 7, 8, 9])->sum('amount') ?? 0;
        $oneTimePayrollFineAndFeeYTD = ApprovalsAndRequestLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->where('adjustment_type_id', 5)->where('payroll_id', '!=', 0)->sum('amount') ?? 0;
        $oneTimePayrollReimbursementYTD = ApprovalsAndRequestLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->where('adjustment_type_id', 2)->where('payroll_id', '!=', 0)->sum('amount') ?? 0;
        $oneTimePayrollDeductionYTD = PayrollDeductionLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('total') ?? 0;
        $oneTimePayrollW2DeductionYTD = W2PayrollTaxDeduction::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('fica_tax') ?? 0;
        $oneTimePayrollReconciliationYTD = ReconciliationFinalizeHistoryLock::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('net_amount') ?? 0;
        $oneTimePayrollCustomFieldYTD = CustomFieldHistory::whereIn('one_time_payment_id', $oneTimePaymentIds)->sum('value') ?? 0;

        $salaryYTD = $salaryYTD + $oneTimePayrollSalaryYTD;
        $overtimeYTD = $overtimeYTD + $oneTimePayrollOvertimeYTD;
        $commissionYTD = ($commissionYTD + $oneTimePayrollCommissionYTD) - ($commissionClawBackYTD + $oneTimePayrollCommissionClawBackYTD);
        $overrideYTD = ($overrideYTD + $oneTimePayrollOverrideYTD) - ($overrideClawBackYTD + $oneTimePayrollOverrideClawBackYTD);
        $reconciliationYTD = $reconciliationYTD + $oneTimePayrollReconciliationYTD;
        $adjustmentYTD = ($adjustmentYTD + $approvalsAndRequestYTD + $oneTimeAdjustmentYTD + $oneTimePayrollAdjustmentYTD + $oneTimePayrollApprovalsAndRequestYTD) - ($fineAndFeeYTD + $oneTimePayrollFineAndFeeYTD);
        $reimbursementYTD = $reimbursementYTD + $oneTimeReimbursementYTD + $oneTimePayrollReimbursementYTD;
        $customFieldYTD = $customFieldYTD + $oneTimePayrollCustomFieldYTD;
        $deductionYTD = $deductionYTD + $oneTimePayrollDeductionYTD;
        $w2DeductionYTD = $w2DeductionYTD + $oneTimeW2DeductionYTD + $oneTimePayrollW2DeductionYTD;
        $netPayYTD = ($salaryYTD + $overtimeYTD + $commissionYTD + $overrideYTD + $reconciliationYTD + $adjustmentYTD + $reimbursementYTD + $customFieldYTD) - ($deductionYTD + $w2DeductionYTD);

        return [
            'netPayYTD' => $netPayYTD,
            'salaryYTD' => $salaryYTD,
            'overtimeYTD' => $overtimeYTD,
            'commissionYTD' => $commissionYTD,
            'overrideYTD' => $overrideYTD,
            'reconciliationYTD' => $reconciliationYTD,
            'deductionYTD' => $deductionYTD,
            'w2DeductionYTD' => $w2DeductionYTD,
            'adjustmentYTD' => $adjustmentYTD,
            'reimbursementYTD' => $reimbursementYTD,
            'customFieldYTD' => $customFieldYTD,
        ];
    }
}

if (!function_exists("payrollColorPallet")) {
    function payrollColorPallet($data)
    {
        $map = [
            'paid' => 1, // GREEN
            'next' => 2, // BLUE
            'recon' => 3, // ORANGE
            'one_time' => 4 // LIGHT BLUE
        ];

        $active = [];
        foreach ($map as $key => $code) {
            if (isset($data[$key]) && !empty($data[$key])) {
                $active[] = $code;
            }
        }

        if (count($active) > 1) {
            $flag = 5; // YELLOW
        } else if (count($active) === 1) {
            $flag = $active[0];
        } else {
            $flag = 0;
        }

        return $flag;
    }
}

if (!function_exists("correctPayrollData")) {
    function correctPayrollData()
    {
        $message = [];
        $userCommissions = UserCommission::where('status', 1)->where('settlement_type', 'during_m2')->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($userCommissions as $userCommission) {
            $payroll = Payroll::where('user_id', $userCommission->user_id)->where('pay_period_from', $userCommission->pay_period_from)->where('pay_period_to', $userCommission->pay_period_to)->where('pay_frequency', $userCommission->pay_frequency)->where('worker_type', $userCommission->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($userCommission->position_id) ? $userCommission->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($userCommission->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $userCommission->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $userCommission->pay_frequency,
                    'worker_type' => $userCommission->user_worker_type,
                    'pay_period_from' => $userCommission->pay_period_from,
                    'pay_period_to' => $userCommission->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for user commission: " . $userCommission->user_id . " - " . $userCommission->pay_period_from . " - " . $userCommission->pay_period_to . " - " . $userCommission->pay_frequency . " - " . $userCommission->user_worker_type;
            }
        }

        $userOverrides = UserOverrides::where('status', 1)->where('overrides_settlement_type', 'during_m2')->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($userOverrides as $userOverride) {
            $payroll = Payroll::where('user_id', $userOverride->user_id)->where('pay_period_from', $userOverride->pay_period_from)->where('pay_period_to', $userOverride->pay_period_to)->where('pay_frequency', $userOverride->pay_frequency)->where('worker_type', $userOverride->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($userOverride->position_id) ? $userOverride->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($userOverride->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $userOverride->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $userOverride->pay_frequency,
                    'worker_type' => $userOverride->user_worker_type,
                    'pay_period_from' => $userOverride->pay_period_from,
                    'pay_period_to' => $userOverride->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for user override: " . $userOverride->user_id . " - " . $userOverride->pay_period_from . " - " . $userOverride->pay_period_to . " - " . $userOverride->pay_frequency . " - " . $userOverride->user_worker_type;
            }
        }

        $clawbackSettlements = ClawbackSettlement::where('status', 1)->where('clawback_type', 'next payroll')->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($clawbackSettlements as $clawbackSettlement) {
            $payroll = Payroll::where('user_id', $clawbackSettlement->user_id)->where('pay_period_from', $clawbackSettlement->pay_period_from)->where('pay_period_to', $clawbackSettlement->pay_period_to)->where('pay_frequency', $clawbackSettlement->pay_frequency)->where('worker_type', $clawbackSettlement->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($clawbackSettlement->position_id) ? $clawbackSettlement->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($clawbackSettlement->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $clawbackSettlement->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $clawbackSettlement->pay_frequency,
                    'worker_type' => $clawbackSettlement->user_worker_type,
                    'pay_period_from' => $clawbackSettlement->pay_period_from,
                    'pay_period_to' => $clawbackSettlement->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for clawback settlement: " . $clawbackSettlement->user_id . " - " . $clawbackSettlement->pay_period_from . " - " . $clawbackSettlement->pay_period_to . " - " . $clawbackSettlement->pay_frequency . " - " . $clawbackSettlement->user_worker_type;
            }
        }

        $payrollHourlySalaries = PayrollHourlySalary::where('status', 1)->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($payrollHourlySalaries as $payrollHourlySalary) {
            $payroll = Payroll::where('user_id', $payrollHourlySalary->user_id)->where('pay_period_from', $payrollHourlySalary->pay_period_from)->where('pay_period_to', $payrollHourlySalary->pay_period_to)->where('pay_frequency', $payrollHourlySalary->pay_frequency)->where('worker_type', $payrollHourlySalary->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($payrollHourlySalary->position_id) ? $payrollHourlySalary->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($payrollHourlySalary->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $payrollHourlySalary->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $payrollHourlySalary->pay_frequency,
                    'worker_type' => $payrollHourlySalary->user_worker_type,
                    'pay_period_from' => $payrollHourlySalary->pay_period_from,
                    'pay_period_to' => $payrollHourlySalary->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for payroll hourly salary: " . $payrollHourlySalary->user_id . " - " . $payrollHourlySalary->pay_period_from . " - " . $payrollHourlySalary->pay_period_to . " - " . $payrollHourlySalary->pay_frequency . " - " . $payrollHourlySalary->user_worker_type;
            }
        }

        $payrollOvertimes = PayrollOvertime::where('status', 1)->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($payrollOvertimes as $payrollOvertime) {
            $payroll = Payroll::where('user_id', $payrollOvertime->user_id)->where('pay_period_from', $payrollOvertime->pay_period_from)->where('pay_period_to', $payrollOvertime->pay_period_to)->where('pay_frequency', $payrollOvertime->pay_frequency)->where('worker_type', $payrollOvertime->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($payrollOvertime->position_id) ? $payrollOvertime->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($payrollOvertime->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $payrollOvertime->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $payrollOvertime->pay_frequency,
                    'worker_type' => $payrollOvertime->user_worker_type,
                    'pay_period_from' => $payrollOvertime->pay_period_from,
                    'pay_period_to' => $payrollOvertime->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for payroll overtime: " . $payrollOvertime->user_id . " - " . $payrollOvertime->pay_period_from . " - " . $payrollOvertime->pay_period_to . " - " . $payrollOvertime->pay_frequency . " - " . $payrollOvertime->user_worker_type;
            }
        }

        $payrollAdjustmentDetails = PayrollAdjustmentDetail::where('status', 1)->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($payrollAdjustmentDetails as $payrollAdjustmentDetail) {
            $payroll = Payroll::where('user_id', $payrollAdjustmentDetail->user_id)->where('pay_period_from', $payrollAdjustmentDetail->pay_period_from)->where('pay_period_to', $payrollAdjustmentDetail->pay_period_to)->where('pay_frequency', $payrollAdjustmentDetail->pay_frequency)->where('worker_type', $payrollAdjustmentDetail->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($payrollAdjustmentDetail->position_id) ? $payrollAdjustmentDetail->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($payrollAdjustmentDetail->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $payrollAdjustmentDetail->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $payrollAdjustmentDetail->pay_frequency,
                    'worker_type' => $payrollAdjustmentDetail->user_worker_type,
                    'pay_period_from' => $payrollAdjustmentDetail->pay_period_from,
                    'pay_period_to' => $payrollAdjustmentDetail->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for payroll adjustment detail: " . $payrollAdjustmentDetail->user_id . " - " . $payrollAdjustmentDetail->pay_period_from . " - " . $payrollAdjustmentDetail->pay_period_to . " - " . $payrollAdjustmentDetail->pay_frequency . " - " . $payrollAdjustmentDetail->user_worker_type;
            }
        }

        $payrollApprovalsAndRequests = ApprovalsAndRequest::where('status', 'Accept')->whereNotIn('adjustment_type_id', [7, 8, 9])->whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($payrollApprovalsAndRequests as $payrollApprovalsAndRequest) {
            $payroll = Payroll::where('user_id', $payrollApprovalsAndRequest->user_id)->where('pay_period_from', $payrollApprovalsAndRequest->pay_period_from)->where('pay_period_to', $payrollApprovalsAndRequest->pay_period_to)->where('pay_frequency', $payrollApprovalsAndRequest->pay_frequency)->where('worker_type', $payrollApprovalsAndRequest->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($payrollApprovalsAndRequest->position_id) ? $payrollApprovalsAndRequest->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($payrollApprovalsAndRequest->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $payrollApprovalsAndRequest->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $payrollApprovalsAndRequest->pay_frequency,
                    'worker_type' => $payrollApprovalsAndRequest->user_worker_type,
                    'pay_period_from' => $payrollApprovalsAndRequest->pay_period_from,
                    'pay_period_to' => $payrollApprovalsAndRequest->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for payroll approvals and requests: " . $payrollApprovalsAndRequest->user_id . " - " . $payrollApprovalsAndRequest->pay_period_from . " - " . $payrollApprovalsAndRequest->pay_period_to . " - " . $payrollApprovalsAndRequest->pay_frequency . " - " . $payrollApprovalsAndRequest->user_worker_type;
            }
        }

        CustomField::where(function ($q) {
            $q->whereNull('value')->orWhere('value', 0);
        })->delete();
        CustomField::whereNull('pay_period_from')->orWhereNull('pay_period_to')->orWhereNull('user_worker_type')->orWhereNull('pay_frequency')->delete();
        $payrollCustomFields = CustomField::whereNotNull('pay_period_from')->whereNotNull('pay_period_to')->whereNotNull('user_worker_type')->whereNotNull('pay_frequency')->get();
        foreach ($payrollCustomFields as $payrollCustomField) {
            $payroll = Payroll::where('user_id', $payrollCustomField->user_id)->where('pay_period_from', $payrollCustomField->pay_period_from)->where('pay_period_to', $payrollCustomField->pay_period_to)->where('pay_frequency', $payrollCustomField->pay_frequency)->where('worker_type', $payrollCustomField->user_worker_type)->first();
            if (!$payroll) {
                $positionId = isset($payrollCustomField->position_id) ? $payrollCustomField->position_id : null;
                if (!$positionId) {
                    $positionId = User::find($payrollCustomField->user_id)?->sub_position_id;
                }

                Payroll::create([
                    'user_id' => $payrollCustomField->user_id,
                    'position_id' => $positionId,
                    'pay_frequency' => $payrollCustomField->pay_frequency,
                    'worker_type' => $payrollCustomField->user_worker_type,
                    'pay_period_from' => $payrollCustomField->pay_period_from,
                    'pay_period_to' => $payrollCustomField->pay_period_to,
                    'status' => 1
                ]);
                $message[] = "Payroll created for payroll custom field: " . $payrollCustomField->user_id . " - " . $payrollCustomField->pay_period_from . " - " . $payrollCustomField->pay_period_to . " - " . $payrollCustomField->pay_frequency . " - " . $payrollCustomField->user_worker_type;
            }
        }

        \Illuminate\Support\Facades\Artisan::call('payroll:re-calculate');

        if (!class_exists('NewEmailClass')) {
            class NewEmailClass
            {
                use EmailNotificationTrait;
            }
        }

        $table = '<table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Error Message</th>
                </tr>
            </thead>
        <tbody>';
        foreach ($message as $index => $error) {
            $table .= "<tr>
                <td>" . ($index + 1) . "</td>
                <td>" . htmlspecialchars($error) . "</td>
            </tr>";
        }
        $table .= '</tbody></table>';
        $data = [
            'email' => 'jay@sequifi.com',
            'subject' => config('app.domain_name') . ' - Payroll Data Correction!!',
            'template' => $table
        ];
        (new NewEmailClass)->sendEmailNotification($data);
    }
}
