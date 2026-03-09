<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use App\Models\ReconOverrideHistoryLock;
use App\Models\ReconCommissionHistoryLock;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\ReconciliationFinalizeHistory;
use App\Models\TempPayrollFinalizeExecuteDetail;
use App\Models\ReconciliationFinalizeHistoryLock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollV2DataMigrationSeeder extends Seeder
{
    /**
     * Migrate old payroll data for Payroll V2.
     * Data from migration 2025_09_22_031237_migrate_old_payroll_data_for_payroll_v2.php
     * 
     * This seeder is idempotent - checks if migration already done.
     */
    public function run(): void
    {
        // Check if migration already completed
        if ($this->isMigrationCompleted()) {
            Log::info('PayrollV2 data migration already completed, skipping');
            return;
        }

        Log::info('Starting PayrollV2 data migration');

        DB::beginTransaction();

        try {
            $this->migratePayFrequencyData();
            $this->migrateWorkerTypeData();
            $this->migratePayrollTypeIdData();
            $this->migrateOneTimePaymentData();
            $this->createPaystubEmployees();
            
            // Mark migration as completed
            $this->markMigrationCompleted();
            
            DB::commit();
            
            Log::info('PayrollV2 data migration completed successfully');
            
            // Re-calculate payrolls after data migration
            Artisan::call('payroll:re-calculate');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayrollV2 data migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function isMigrationCompleted(): bool
    {
        return DB::table('system_settings')
            ->where('key', 'payroll_v2_data_migrated')
            ->where('value', '1')
            ->exists();
    }

    private function markMigrationCompleted(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'payroll_v2_data_migrated'],
            [
                'value' => '1',
                'description' => 'PayrollV2 data migration completed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function migratePayFrequencyData(): void
    {
        $frequencyTypes = FrequencyType::where('status', 1)->get();
        
        foreach ($frequencyTypes as $frequencyType) {
            $class = null;
            $type = null;

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
                    $this->updateDailyPayFrequency($payPeriod, $frequencyType->id);
                } else {
                    $this->updateRegularFrequency($payPeriod, $frequencyType->id);
                }
            }
        }
    }

    private function updateDailyPayFrequency($payPeriod, int $frequencyTypeId): void
    {
        $updateQuery = [
            'whereBetween' => ['pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to]],
            'whereColumn' => ['pay_period_from', 'pay_period_to'],
            'pay_frequency' => $frequencyTypeId
        ];

        Payroll::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        PayrollHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        ApprovalsAndRequest::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        ApprovalsAndRequestLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        ClawbackSettlement::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        ClawbackSettlementLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        PayrollAdjustment::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        PayrollAdjustmentLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        PayrollAdjustmentDetail::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        PayrollAdjustmentDetailLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        PayrollDeductions::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        PayrollDeductionLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        PayrollHourlySalary::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        PayrollHourlySalaryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        PayrollOvertime::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        PayrollOvertimeLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        ReconciliationFinalizeHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        ReconciliationFinalizeHistoryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        UserCommission::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        UserCommissionLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        UserOverrides::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        UserOverridesLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        CustomField::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        CustomFieldHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        ReconCommissionHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        ReconCommissionHistoryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        ReconOverrideHistory::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
        ReconOverrideHistoryLock::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        TempPayrollFinalizeExecuteDetail::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);

        W2PayrollTaxDeduction::whereBetween('pay_period_from', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereBetween('pay_period_to', [$payPeriod->pay_period_from, $payPeriod->pay_period_to])
            ->whereColumn('pay_period_from', 'pay_period_to')->update(['pay_frequency' => $frequencyTypeId]);
    }

    private function updateRegularFrequency($payPeriod, int $frequencyTypeId): void
    {
        Payroll::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        PayrollHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        ApprovalsAndRequest::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        ApprovalsAndRequestLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        ClawbackSettlement::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        ClawbackSettlementLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        PayrollAdjustment::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        PayrollAdjustmentLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        PayrollAdjustmentDetail::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        PayrollAdjustmentDetailLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        PayrollDeductions::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        PayrollDeductionLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        PayrollHourlySalary::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        PayrollHourlySalaryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        PayrollOvertime::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        PayrollOvertimeLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        ReconciliationFinalizeHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        ReconciliationFinalizeHistoryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        UserCommission::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        UserCommissionLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        UserOverrides::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        UserOverridesLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        CustomField::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        CustomFieldHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        ReconCommissionHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        ReconCommissionHistoryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        ReconOverrideHistory::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
        ReconOverrideHistoryLock::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        TempPayrollFinalizeExecuteDetail::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);

        W2PayrollTaxDeduction::where(['pay_period_from' => $payPeriod->pay_period_from, 'pay_period_to' => $payPeriod->pay_period_to])->update(['pay_frequency' => $frequencyTypeId]);
    }

    private function migrateWorkerTypeData(): void
    {
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
    }

    private function migratePayrollTypeIdData(): void
    {
        $payrollAdjustmentDetails = PayrollAdjustmentDetail::get();
        foreach ($payrollAdjustmentDetails as $detail) {
            $id = $this->getPayrollTypeId($detail, false);
            if ($id) {
                PayrollAdjustmentDetail::where('id', $detail->id)->update(['payroll_type_id' => $id]);
            }
        }

        $payrollAdjustmentDetailLocks = PayrollAdjustmentDetailLock::get();
        foreach ($payrollAdjustmentDetailLocks as $detail) {
            $id = $this->getPayrollTypeId($detail, true);
            if ($id) {
                PayrollAdjustmentDetailLock::where('id', $detail->id)->update(['payroll_type_id' => $id]);
            }
        }
    }

    private function getPayrollTypeId($detail, bool $isLock)
    {
        $id = null;
        
        if ($detail->payroll_type == 'hourlysalary') {
            $model = $isLock ? PayrollHourlySalaryLock::class : PayrollHourlySalary::class;
            $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id])->first()?->id;
        } else if ($detail->payroll_type == 'overtime') {
            $model = $isLock ? PayrollOvertimeLock::class : PayrollOvertime::class;
            $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id])->first()?->id;
        } else if ($detail->payroll_type == 'commission') {
            if ($detail->type == 'clawback') {
                $model = $isLock ? ClawbackSettlementLock::class : ClawbackSettlement::class;
                $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id, 'pid' => $detail->pid, 'schema_type' => $detail->adjustment_type])->first()?->id;
            } else {
                $model = $isLock ? UserCommissionLock::class : UserCommission::class;
                $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id, 'pid' => $detail->pid, 'schema_type' => $detail->adjustment_type])->first()?->id;
            }
        } else if ($detail->payroll_type == 'overrides') {
            if ($detail->type == 'clawback') {
                $model = $isLock ? ClawbackSettlementLock::class : ClawbackSettlement::class;
                $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id, 'pid' => $detail->pid, 'adders_type' => $detail->adjustment_type])->first()?->id;
            } else {
                $model = $isLock ? UserOverridesLock::class : UserOverrides::class;
                $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id, 'pid' => $detail->pid, 'type' => $detail->adjustment_type])->first()?->id;
            }
        } else if ($detail->payroll_type == 'deduction') {
            $model = $isLock ? PayrollDeductionLock::class : PayrollDeductions::class;
            $id = $model::where(['user_id' => $detail->user_id, 'payroll_id' => $detail->payroll_id, 'cost_center_id' => $detail->cost_center_id])->first()?->id;
        }

        return $id;
    }

    private function migrateOneTimePaymentData(): void
    {
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
        
        if (!empty($oneTimePaymentIds)) {
            OneTimePayments::whereIn('id', $oneTimePaymentIds)->update(['from_payroll' => 1]);
        }

        $oneTimePayments = OneTimePayments::where('from_payroll', 1)->get();
        foreach ($oneTimePayments as $oneTimePayment) {
            $payData = $this->getOneTimePaymentData($oneTimePayment);
            
            if ($payData['pay_frequency'] && $payData['user_worker_type'] && $payData['pay_period_from'] && $payData['pay_period_to']) {
                OneTimePayments::where('id', $oneTimePayment->id)->update([
                    'pay_frequency' => $payData['pay_frequency'],
                    'user_worker_type' => $payData['user_worker_type'],
                    'pay_period_from' => $payData['pay_period_from'],
                    'pay_period_to' => $payData['pay_period_to']
                ]);
            }
        }
    }

    private function getOneTimePaymentData($oneTimePayment): array
    {
        $data = [
            'pay_frequency' => null,
            'user_worker_type' => null,
            'pay_period_from' => null,
            'pay_period_to' => null
        ];

        $sources = [
            Payroll::class => ['worker_type_field' => 'worker_type', 'worker_type_target' => 'user_worker_type'],
            PayrollHistory::class => ['worker_type_field' => 'worker_type', 'worker_type_target' => 'user_worker_type'],
            ApprovalsAndRequest::class => ['worker_type_field' => 'user_worker_type', 'worker_type_target' => 'user_worker_type'],
            ApprovalsAndRequestLock::class => ['worker_type_field' => 'user_worker_type', 'worker_type_target' => 'user_worker_type'],
            ClawbackSettlement::class => ['worker_type_field' => 'user_worker_type', 'worker_type_target' => 'user_worker_type'],
            ClawbackSettlementLock::class => ['worker_type_field' => 'user_worker_type', 'worker_type_target' => 'user_worker_type'],
            PayrollAdjustment::class => ['worker_type_field' => 'user_worker_type', 'worker_type_target' => 'user_worker_type'],
            PayrollAdjustmentLock::class => ['worker_type_field' => 'user_worker_type', 'worker_type_target' => 'user_worker_type'],
        ];

        foreach ($sources as $model => $config) {
            $record = $model::where('one_time_payment_id', $oneTimePayment->id)->first();
            if ($record) {
                $data['pay_frequency'] = $record->pay_frequency;
                $data['user_worker_type'] = $record->{$config['worker_type_field']};
                $data['pay_period_from'] = $record->pay_period_from;
                $data['pay_period_to'] = $record->pay_period_to;
                break;
            }
        }

        return $data;
    }

    private function createPaystubEmployees(): void
    {
        $oneTimePayments = OneTimePayments::where('from_payroll', 1)->get();
        foreach ($oneTimePayments as $oneTimePayment) {
            $payData = $this->getOneTimePaymentData($oneTimePayment);
            
            if ($payData['pay_frequency'] && $payData['user_worker_type'] && $payData['pay_period_from'] && $payData['pay_period_to']) {
                if (!paystubEmployee::where(["one_time_payment_id" => $oneTimePayment->id, "is_onetime_payment" => 1])->first()) {
                    create_paystub_employee([
                        "one_time_payment_id" => $oneTimePayment->id,
                        "user_id" => $oneTimePayment->user_id,
                        "pay_period_from" => $payData['pay_period_from'],
                        "pay_period_to" => $payData['pay_period_to']
                    ], 1);
                }
            }
        }

        $oneTimePayments = OneTimePayments::where('from_payroll', '!=', 1)->get();
        foreach ($oneTimePayments as $oneTimePayment) {
            if (!paystubEmployee::where(["one_time_payment_id" => $oneTimePayment->id, "is_onetime_payment" => 1])->exists()) {
                create_paystub_employee([
                    "one_time_payment_id" => $oneTimePayment->id,
                    "user_id" => $oneTimePayment->user_id,
                    "pay_period_from" => $oneTimePayment->pay_period_from,
                    "pay_period_to" => $oneTimePayment->pay_period_to
                ], 1);
            }
        }
    }
}

