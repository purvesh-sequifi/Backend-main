<?php

use App\Models\User;
use App\Models\Payroll;
use App\Models\CustomField;
use App\Models\UserOverrides;
use App\Models\FrequencyType;
use App\Models\UserCommission;
use App\Models\PayrollHistory;
use App\Models\OneTimePayments;
use App\Models\paystubEmployee;
use App\Models\PayrollOvertime;
use App\Models\PayrollDeductions;
use App\Models\DailyPayFrequency;
use App\Models\PayrollAdjustment;
use App\Models\UserOverridesLock;
use App\Models\ClawbackSettlement;
use App\Models\CustomFieldHistory;
use App\Models\WeeklyPayFrequency;
use App\Models\UserCommissionLock;
use App\Models\PayrollHourlySalary;
use App\Models\ApprovalsAndRequest;
use App\Models\MonthlyPayFrequency;
use App\Models\PayrollOvertimeLock;
use App\Models\PayrollDeductionLock;
use App\Models\ReconOverrideHistory;
use App\Models\W2PayrollTaxDeduction;
use App\Models\PayrollAdjustmentLock;
use App\Models\AdditionalPayFrequency;
use App\Models\ClawbackSettlementLock;
use App\Models\ReconCommissionHistory;
use App\Models\ApprovalsAndRequestLock;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollHourlySalaryLock;
use Illuminate\Support\Facades\Artisan;
use App\Models\ReconOverrideHistoryLock;
use App\Models\ReconCommissionHistoryLock;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\ReconciliationFinalizeHistory;
use Illuminate\Database\Migrations\Migration;
use App\Models\TempPayrollFinalizeExecuteDetail;
use App\Models\ReconciliationFinalizeHistoryLock;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Data migration moved to PayrollV2DataMigrationSeeder
        // This migration now only handles structure changes (none needed)
        // All payroll data updates are handled via seeder for proper separation of concerns
        // See PayrollV2DataMigrationSeeder for the full migration logic
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Data migration - no structure to reverse
    }
};

// OLD CODE MOVED TO SEEDER - KEEPING FOR REFERENCE
/*
        foreach ($frequencyTypes as $frequencyType) {
            if ($frequencyType->id == FrequencyType::WEEKLY_ID) {
                $class = WeeklyPayFrequency::class;
            } else if ($frequencyType->id == FrequencyType::MONTHLY_ID) {
                $class = MonthlyPayFrequency::class;
            } else if ($frequencyType->id == FrequencyType::BI_WEEKLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::BI_WEEKLY_TYPE;
            } else if ($frequencyType->id == FrequencyType::SEMI_MONTHLY_ID) {
                $class = AdditionalPayFrequency::class;
                $type = AdditionalPayFrequency::SEMI_MONTHLY_TYPE;
            } else if ($frequencyType->id == FrequencyType::DAILY_PAY_ID) {
                $class = DailyPayFrequency::class;
            }

            if (!isset($class)) {
                continue;
            }

            $frequency = $class::query();
            if ($frequencyType->id == FrequencyType::BI_WEEKLY_ID || $frequencyType->id == FrequencyType::SEMI_MONTHLY_ID) {
                $frequency = $frequency->where('type', $type);
            }

            $payPeriods = $frequency->get();
            foreach ($payPeriods as $payPeriod) {
                if ($frequencyType->id == FrequencyType::DAILY_PAY_ID) {
                    Payroll::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    PayrollHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    ApprovalsAndRequest::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    ApprovalsAndRequestLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    ClawbackSettlement::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    ClawbackSettlementLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    PayrollAdjustment::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    PayrollAdjustmentLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    PayrollAdjustmentDetail::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    PayrollAdjustmentDetailLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    PayrollDeductions::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    PayrollDeductionLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    PayrollHourlySalary::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    PayrollHourlySalaryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    PayrollOvertime::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    PayrollOvertimeLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    ReconciliationFinalizeHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    ReconciliationFinalizeHistoryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    UserCommission::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    UserCommissionLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    UserOverrides::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    UserOverridesLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    CustomField::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    CustomFieldHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    ReconCommissionHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    ReconCommissionHistoryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    ReconOverrideHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                    ReconOverrideHistoryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    TempPayrollFinalizeExecuteDetail::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);

                    W2PayrollTaxDeduction::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
                        ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyType->id]);
                } else {
                    Payroll::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    PayrollHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    ApprovalsAndRequest::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    ApprovalsAndRequestLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    ClawbackSettlement::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    ClawbackSettlementLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    PayrollAdjustment::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    PayrollAdjustmentLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    PayrollAdjustmentDetail::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    PayrollAdjustmentDetailLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    PayrollDeductions::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    PayrollDeductionLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    PayrollHourlySalary::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    PayrollHourlySalaryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    PayrollOvertime::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    PayrollOvertimeLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    ReconciliationFinalizeHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    ReconciliationFinalizeHistoryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    UserCommission::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    UserCommissionLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    UserOverrides::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    UserOverridesLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    CustomField::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    CustomFieldHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    ReconCommissionHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    ReconCommissionHistoryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    ReconOverrideHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    ReconOverrideHistoryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    TempPayrollFinalizeExecuteDetail::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    W2PayrollTaxDeduction::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollAdjustmentDetail::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollAdjustmentDetailLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollDeduction::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollDeductionLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollHourlySalary::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollHourlySalaryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollOvertime::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollOvertimeLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollCommission::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollCommissionLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollOverride::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollOverrideLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);

                    // UserPayrollClawback::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                    // UserPayrollClawbackLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyType->id]);
                }
            }
        }

        $users = User::get();
        foreach ($users as $user) {
            Payroll::where('user_id', $user->id)->update(['worker_type' => $user->worker_type]);
            PayrollHistory::where('user_id', $user->id)->update(['worker_type' => $user->worker_type]);

            ApprovalsAndRequest::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            ApprovalsAndRequestLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            ClawbackSettlement::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            ClawbackSettlementLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            PayrollAdjustment::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            PayrollAdjustmentLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            PayrollAdjustmentDetail::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            PayrollAdjustmentDetailLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            PayrollDeductions::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            PayrollDeductionLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            PayrollHourlySalary::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            PayrollHourlySalaryLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            PayrollOvertime::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            PayrollOvertimeLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            ReconciliationFinalizeHistory::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            ReconciliationFinalizeHistoryLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            UserCommission::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            UserCommissionLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            UserOverrides::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            UserOverridesLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            CustomField::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            CustomFieldHistory::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            ReconCommissionHistory::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            ReconCommissionHistoryLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            ReconOverrideHistory::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);
            ReconOverrideHistoryLock::where('user_id', $user->id)->update(['user_worker_type' => $user->worker_type]);

            TempPayrollFinalizeExecuteDetail::where('user_id', $user->id)->update(['worker_type' => $user->worker_type]);
        }

        W2PayrollTaxDeduction::query()->update(['user_worker_type' => 'w2']);

        $oneTimePaymentIds = array_merge(
            Payroll::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollHistory::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            ApprovalsAndRequest::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            ApprovalsAndRequestLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            ClawbackSettlement::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            ClawbackSettlementLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollAdjustment::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollAdjustmentLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollAdjustmentDetail::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollAdjustmentDetailLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollDeductions::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollDeductionLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollHourlySalary::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollHourlySalaryLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollOvertime::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            PayrollOvertimeLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            ReconciliationFinalizeHistory::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            ReconciliationFinalizeHistoryLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            UserCommission::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            UserCommissionLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            UserOverrides::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            UserOverridesLock::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            CustomField::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray(),
            CustomFieldHistory::where('is_onetime_payment', 1)->pluck('one_time_payment_id')->toArray()
        );
        OneTimePayments::whereIn('id', $oneTimePaymentIds)->update(['from_payroll' => 1]);

        $payrollAdjustmentDetails = PayrollAdjustmentDetail::get();
        foreach ($payrollAdjustmentDetails as $payrollAdjustmentDetail) {
            $id = null;
            if ($payrollAdjustmentDetail->payroll_type == 'hourlysalary') {
                $id = PayrollHourlySalary::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id])->first()?->id;
            } else if ($payrollAdjustmentDetail->payroll_type == 'overtime') {
                $id = PayrollOvertime::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id])->first()?->id;
            } else if ($payrollAdjustmentDetail->payroll_type == 'commission') {
                if ($payrollAdjustmentDetail->type == 'clawback') {
                    $id = ClawbackSettlement::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'schema_type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                } else {
                    $id = UserCommission::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'schema_type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                }
            } else if ($payrollAdjustmentDetail->payroll_type == 'overrides') {
                if ($payrollAdjustmentDetail->type == 'clawback') {
                    $id = ClawbackSettlement::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'adders_type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                } else {
                    $id = UserOverrides::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                }
            } else if ($payrollAdjustmentDetail->payroll_type == 'deduction') {
                $id = PayrollDeductions::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'cost_center_id' => $payrollAdjustmentDetail->cost_center_id])->first()?->id;
            }

            if ($id) {
                PayrollAdjustmentDetail::where('id', $payrollAdjustmentDetail->id)->update(['payroll_type_id' => $id]);
            }
        }

        $payrollAdjustmentDetails = PayrollAdjustmentDetailLock::get();
        foreach ($payrollAdjustmentDetails as $payrollAdjustmentDetail) {
            $id = null;
            if ($payrollAdjustmentDetail->payroll_type == 'hourlysalary') {
                $id = PayrollHourlySalaryLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id])->first()?->id;
            } else if ($payrollAdjustmentDetail->payroll_type == 'overtime') {
                $id = PayrollOvertimeLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id])->first()?->id;
            } else if ($payrollAdjustmentDetail->payroll_type == 'commission') {
                if ($payrollAdjustmentDetail->type == 'clawback') {
                    $id = ClawbackSettlementLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'schema_type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                } else {
                    $id = UserCommissionLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'schema_type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                }
            } else if ($payrollAdjustmentDetail->payroll_type == 'overrides') {
                if ($payrollAdjustmentDetail->type == 'clawback') {
                    $id = ClawbackSettlementLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'adders_type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                } else {
                    $id = UserOverridesLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'pid' => $payrollAdjustmentDetail->pid, 'type' => $payrollAdjustmentDetail->adjustment_type])->first()?->id;
                }
            } else if ($payrollAdjustmentDetail->payroll_type == 'deduction') {
                $id = PayrollDeductionLock::where(['user_id' => $payrollAdjustmentDetail->user_id, 'payroll_id' => $payrollAdjustmentDetail->payroll_id, 'cost_center_id' => $payrollAdjustmentDetail->cost_center_id])->first()?->id;
            }

            if ($id) {
                PayrollAdjustmentDetailLock::where('id', $payrollAdjustmentDetail->id)->update(['payroll_type_id' => $id]);
            }
        }

        $oneTimePayments = OneTimePayments::where('from_payroll', 1)->get();
        foreach ($oneTimePayments as $oneTimePayment) {
            $payFrequency = null;
            $userWorkerType = null;
            $payPeriodFrom = null;
            $payPeriodTo = null;


            $payroll = Payroll::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payroll) {
                $payFrequency = $payroll->pay_frequency;
                $userWorkerType = $payroll->worker_type;
                $payPeriodFrom = $payroll->pay_period_from;
                $payPeriodTo = $payroll->pay_period_to;
            }

            $payrollHistory = PayrollHistory::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollHistory) {
                $payFrequency = $payrollHistory->pay_frequency;
                $userWorkerType = $payrollHistory->worker_type;
                $payPeriodFrom = $payrollHistory->pay_period_from;
                $payPeriodTo = $payrollHistory->pay_period_to;
            }

            $approvalsAndRequest = ApprovalsAndRequest::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($approvalsAndRequest) {
                $payFrequency = $approvalsAndRequest->pay_frequency;
                $userWorkerType = $approvalsAndRequest->user_worker_type;
                $payPeriodFrom = $approvalsAndRequest->pay_period_from;
                $payPeriodTo = $approvalsAndRequest->pay_period_to;
            }

            $approvalsAndRequestLock = ApprovalsAndRequestLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($approvalsAndRequestLock) {
                $payFrequency = $approvalsAndRequestLock->pay_frequency;
                $userWorkerType = $approvalsAndRequestLock->user_worker_type;
                $payPeriodFrom = $approvalsAndRequestLock->pay_period_from;
                $payPeriodTo = $approvalsAndRequestLock->pay_period_to;
            }

            $clawbackSettlement = ClawbackSettlement::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($clawbackSettlement) {
                $payFrequency = $clawbackSettlement->pay_frequency;
                $userWorkerType = $clawbackSettlement->user_worker_type;
                $payPeriodFrom = $clawbackSettlement->pay_period_from;
                $payPeriodTo = $clawbackSettlement->pay_period_to;
            }

            $clawbackSettlementLock = ClawbackSettlementLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($clawbackSettlementLock) {
                $payFrequency = $clawbackSettlementLock->pay_frequency;
                $userWorkerType = $clawbackSettlementLock->user_worker_type;
                $payPeriodFrom = $clawbackSettlementLock->pay_period_from;
                $payPeriodTo = $clawbackSettlementLock->pay_period_to;
            }

            $payrollAdjustment = PayrollAdjustment::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollAdjustment) {
                $payFrequency = $payrollAdjustment->pay_frequency;
                $userWorkerType = $payrollAdjustment->user_worker_type;
                $payPeriodFrom = $payrollAdjustment->pay_period_from;
                $payPeriodTo = $payrollAdjustment->pay_period_to;
            }

            $payrollAdjustmentLock = PayrollAdjustmentLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollAdjustmentLock) {
                $payFrequency = $payrollAdjustmentLock->pay_frequency;
                $userWorkerType = $payrollAdjustmentLock->user_worker_type;
                $payPeriodFrom = $payrollAdjustmentLock->pay_period_from;
                $payPeriodTo = $payrollAdjustmentLock->pay_period_to;
            }

            $payrollAdjustmentDetail = PayrollAdjustmentDetail::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollAdjustmentDetail) {
                $payFrequency = $payrollAdjustmentDetail->pay_frequency;
                $userWorkerType = $payrollAdjustmentDetail->user_worker_type;
                $payPeriodFrom = $payrollAdjustmentDetail->pay_period_from;
                $payPeriodTo = $payrollAdjustmentDetail->pay_period_to;
            }

            $payrollAdjustmentDetailLock = PayrollAdjustmentDetailLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollAdjustmentDetailLock) {
                $payFrequency = $payrollAdjustmentDetailLock->pay_frequency;
                $userWorkerType = $payrollAdjustmentDetailLock->user_worker_type;
                $payPeriodFrom = $payrollAdjustmentDetailLock->pay_period_from;
                $payPeriodTo = $payrollAdjustmentDetailLock->pay_period_to;
            }

            $payrollDeductions = PayrollDeductions::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollDeductions) {
                $payFrequency = $payrollDeductions->pay_frequency;
                $userWorkerType = $payrollDeductions->user_worker_type;
                $payPeriodFrom = $payrollDeductions->pay_period_from;
                $payPeriodTo = $payrollDeductions->pay_period_to;
            }

            $payrollDeductionsLock = PayrollDeductionLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollDeductionsLock) {
                $payFrequency = $payrollDeductionsLock->pay_frequency;
                $userWorkerType = $payrollDeductionsLock->user_worker_type;
                $payPeriodFrom = $payrollDeductionsLock->pay_period_from;
                $payPeriodTo = $payrollDeductionsLock->pay_period_to;
            }

            $payrollHourlySalary = PayrollHourlySalary::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollHourlySalary) {
                $payFrequency = $payrollHourlySalary->pay_frequency;
                $userWorkerType = $payrollHourlySalary->user_worker_type;
                $payPeriodFrom = $payrollHourlySalary->pay_period_from;
                $payPeriodTo = $payrollHourlySalary->pay_period_to;
            }

            $payrollHourlySalaryLock = PayrollHourlySalaryLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollHourlySalaryLock) {
                $payFrequency = $payrollHourlySalaryLock->pay_frequency;
                $userWorkerType = $payrollHourlySalaryLock->user_worker_type;
                $payPeriodFrom = $payrollHourlySalaryLock->pay_period_from;
                $payPeriodTo = $payrollHourlySalaryLock->pay_period_to;
            }

            $payrollOvertime = PayrollOvertime::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollOvertime) {
                $payFrequency = $payrollOvertime->pay_frequency;
                $userWorkerType = $payrollOvertime->user_worker_type;
                $payPeriodFrom = $payrollOvertime->pay_period_from;
                $payPeriodTo = $payrollOvertime->pay_period_to;
            }

            $payrollOvertimeLock = PayrollOvertimeLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($payrollHourlySalaryLock) {
                $payFrequency = $payrollOvertimeLock->pay_frequency;
                $userWorkerType = $payrollOvertimeLock->user_worker_type;
                $payPeriodFrom = $payrollOvertimeLock->pay_period_from;
                $payPeriodTo = $payrollOvertimeLock->pay_period_to;
            }

            $reconciliationFinalizeHistory = ReconciliationFinalizeHistory::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($reconciliationFinalizeHistory) {
                $payFrequency = $reconciliationFinalizeHistory->pay_frequency;
                $userWorkerType = $reconciliationFinalizeHistory->user_worker_type;
                $payPeriodFrom = $reconciliationFinalizeHistory->pay_period_from;
                $payPeriodTo = $reconciliationFinalizeHistory->pay_period_to;
            }

            $reconciliationFinalizeHistoryLock = ReconciliationFinalizeHistoryLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($reconciliationFinalizeHistoryLock) {
                $payFrequency = $reconciliationFinalizeHistoryLock->pay_frequency;
                $userWorkerType = $reconciliationFinalizeHistoryLock->user_worker_type;
                $payPeriodFrom = $reconciliationFinalizeHistoryLock->pay_period_from;
                $payPeriodTo = $reconciliationFinalizeHistoryLock->pay_period_to;
            }

            $userCommission = UserCommission::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($userCommission) {
                $payFrequency = $userCommission->pay_frequency;
                $userWorkerType = $userCommission->user_worker_type;
                $payPeriodFrom = $userCommission->pay_period_from;
                $payPeriodTo = $userCommission->pay_period_to;
            }

            $userCommissionLock = UserCommissionLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($userCommissionLock) {
                $payFrequency = $userCommissionLock->pay_frequency;
                $userWorkerType = $userCommissionLock->user_worker_type;
                $payPeriodFrom = $userCommissionLock->pay_period_from;
                $payPeriodTo = $userCommissionLock->pay_period_to;
            }

            $userOverrides = UserOverrides::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($userOverrides) {
                $payFrequency = $userOverrides->pay_frequency;
                $userWorkerType = $userOverrides->user_worker_type;
                $payPeriodFrom = $userOverrides->pay_period_from;
                $payPeriodTo = $userOverrides->pay_period_to;
            }

            $userOverridesLock = UserOverridesLock::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($userOverridesLock) {
                $payFrequency = $userOverridesLock->pay_frequency;
                $userWorkerType = $userOverridesLock->user_worker_type;
                $payPeriodFrom = $userOverridesLock->pay_period_from;
                $payPeriodTo = $userOverridesLock->pay_period_to;
            }

            $customField = CustomField::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($customField) {
                $payFrequency = $customField->pay_frequency;
                $userWorkerType = $customField->user_worker_type;
                $payPeriodFrom = $customField->pay_period_from;
                $payPeriodTo = $customField->pay_period_to;
            }

            $customFieldHistory = CustomFieldHistory::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($customFieldHistory) {
                $payFrequency = $customFieldHistory->pay_frequency;
                $userWorkerType = $customFieldHistory->user_worker_type;
                $payPeriodFrom = $customFieldHistory->pay_period_from;
                $payPeriodTo = $customFieldHistory->pay_period_to;
            }
            if ($payFrequency && $userWorkerType && $payPeriodFrom && $payPeriodTo) {
                OneTimePayments::where('id', $oneTimePayment->id)->update(['pay_frequency' => $payFrequency, 'user_worker_type' => $userWorkerType, 'pay_period_from' => $payPeriodFrom, 'pay_period_to' => $payPeriodTo]);

                if (!paystubEmployee::where(["one_time_payment_id" => $oneTimePayment->id, "is_onetime_payment" => 1])->first()) {
                    create_paystub_employee([
                        "one_time_payment_id" => $oneTimePayment->id,
                        "user_id" => $oneTimePayment->user_id,
                        "pay_period_from" => $payPeriodFrom,
                        "pay_period_to" => $payPeriodTo
                    ], 1);
                }
            }
        }

        $oneTimePayments = OneTimePayments::where('from_payroll', '!=', 1)->get();
        foreach ($oneTimePayments as $oneTimePayment) {
            create_paystub_employee([
                "one_time_payment_id" => $oneTimePayment->id,
                "user_id" => $oneTimePayment->user_id,
                "pay_period_from" => $oneTimePayment->pay_period_from,
                "pay_period_to" => $oneTimePayment->pay_period_to
            ], 1);
        }

        Artisan::call('payroll:re-calculate');
    }
*/
