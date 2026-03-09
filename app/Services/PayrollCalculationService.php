<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FrequencyType;
use App\Models\SalesMaster;
use App\Models\UserCommission;
use App\Models\UserCommissionLock;

class PayrollCalculationService
{
    /**
     * Calculate total sales and total gross amount for standard payroll view (Path C)
     * Uses UserCommission table for active/in-progress payroll
     */
    public function calculateUserCommissionTotals(
        int $userId,
        string $startDate,
        string $endDate,
        int $payFrequency
    ): array {
        $totalSales = 0;
        $totalGrossAmount = 0;

        // Build queries based on pay frequency type
        if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
            $totalSales = $this->getTotalSalesForDailyPay($userId, $startDate, $endDate);
            $totalGrossAmount = $this->getTotalGrossAmountForDailyPayFromUserCommission($userId, $startDate, $endDate);
        } else {
            $totalSales = $this->getTotalSalesForStandardPay($userId, $startDate, $endDate);
            $totalGrossAmount = $this->getTotalGrossAmountForStandardPayFromUserCommission($userId, $startDate, $endDate);
        }

        return [
            'total_sales' => $totalSales,
            'total_gross_amount' => round($totalGrossAmount, 2),
        ];
    }

    /**
     * Calculate total sales and total gross amount for finalized/locked payroll (Path B)
     * Uses UserCommissionLock table for finalized/failed payment payroll
     */
    public function calculateUserCommissionTotalsFromLocked(
        int $userId,
        string $startDate,
        string $endDate,
        int $payFrequency
    ): array {
        $totalSales = 0;
        $totalGrossAmount = 0;

        // Build queries based on pay frequency type
        if ($payFrequency == FrequencyType::DAILY_PAY_ID) {
            $totalSales = $this->getTotalSalesForDailyPay($userId, $startDate, $endDate);
            $totalGrossAmount = $this->getTotalGrossAmountForDailyPayFromUserCommissionLock($userId, $startDate, $endDate);
        } else {
            $totalSales = $this->getTotalSalesForStandardPay($userId, $startDate, $endDate);
            $totalGrossAmount = $this->getTotalGrossAmountForStandardPayFromUserCommissionLock($userId, $startDate, $endDate);
        }

        return [
            'total_sales' => $totalSales,
            'total_gross_amount' => round($totalGrossAmount, 2),
        ];
    }

    /**
     * Get total sales count for daily pay frequency
     */
    private function getTotalSalesForDailyPay(int $userId, string $startDate, string $endDate): int
    {
        return UserCommission::where('user_id', $userId)
            ->whereBetween('pay_period_from', [$startDate, $endDate])
            ->whereBetween('pay_period_to', [$startDate, $endDate])
            ->whereColumn('pay_period_from', 'pay_period_to')
            ->whereIn('status', [1, 2, 6])
            ->distinct('pid')
            ->count('pid');
    }

    /**
     * Get total sales count for standard pay frequency (weekly, bi-weekly, monthly, semi-monthly)
     */
    private function getTotalSalesForStandardPay(int $userId, string $startDate, string $endDate): int
    {
        return UserCommission::where('user_id', $userId)
            ->where('pay_period_from', $startDate)
            ->where('pay_period_to', $endDate)
            ->whereIn('status', [1, 2, 6])
            ->distinct('pid')
            ->count('pid');
    }

    /**
     * Get total gross amount for daily pay frequency from UserCommission (Path C - Standard View)
     * Gets PIDs from UserCommission and sums gross_account_value from sale_masters
     */
    private function getTotalGrossAmountForDailyPayFromUserCommission(int $userId, string $startDate, string $endDate): float
    {
        $commissionPids = UserCommission::where('user_id', $userId)
            ->whereBetween('pay_period_from', [$startDate, $endDate])
            ->whereBetween('pay_period_to', [$startDate, $endDate])
            ->whereColumn('pay_period_from', 'pay_period_to')
            ->whereIn('status', [1, 2, 6])
            ->pluck('pid')
            ->toArray();

        if (empty($commissionPids)) {
            return 0.0;
        }

        return (float) SalesMaster::whereIn('pid', $commissionPids)
            ->sum('gross_account_value');
    }

    /**
     * Get total gross amount for standard pay frequency from UserCommission (Path C - Standard View)
     * Gets PIDs from UserCommission and sums gross_account_value from sale_masters
     */
    private function getTotalGrossAmountForStandardPayFromUserCommission(int $userId, string $startDate, string $endDate): float
    {
        $commissionPids = UserCommission::where('user_id', $userId)
            ->where('pay_period_from', $startDate)
            ->where('pay_period_to', $endDate)
            ->whereIn('status', [1, 2, 6])
            ->pluck('pid')
            ->toArray();

        if (empty($commissionPids)) {
            return 0.0;
        }

        return (float) SalesMaster::whereIn('pid', $commissionPids)
            ->sum('gross_account_value');
    }

    /**
     * Get total gross amount for daily pay frequency from UserCommissionLock (Path B - Finalized/Failed Payment)
     * Gets PIDs from UserCommissionLock and sums gross_account_value from sale_masters
     */
    private function getTotalGrossAmountForDailyPayFromUserCommissionLock(int $userId, string $startDate, string $endDate): float
    {
        $commissionPids = UserCommissionLock::where('user_id', $userId)
            ->whereBetween('pay_period_from', [$startDate, $endDate])
            ->whereBetween('pay_period_to', [$startDate, $endDate])
            ->whereColumn('pay_period_from', 'pay_period_to')
            ->pluck('pid')
            ->toArray();

        if (empty($commissionPids)) {
            return 0.0;
        }

        return (float) SalesMaster::whereIn('pid', $commissionPids)
            ->sum('gross_account_value');
    }

    /**
     * Get total gross amount for standard pay frequency from UserCommissionLock (Path B - Finalized/Failed Payment)
     * Gets PIDs from UserCommissionLock and sums gross_account_value from sale_masters
     */
    private function getTotalGrossAmountForStandardPayFromUserCommissionLock(int $userId, string $startDate, string $endDate): float
    {
        $commissionPids = UserCommissionLock::where('user_id', $userId)
            ->where('pay_period_from', $startDate)
            ->where('pay_period_to', $endDate)
            ->pluck('pid')
            ->toArray();

        if (empty($commissionPids)) {
            return 0.0;
        }

        return (float) SalesMaster::whereIn('pid', $commissionPids)
            ->sum('gross_account_value');
    }
}
