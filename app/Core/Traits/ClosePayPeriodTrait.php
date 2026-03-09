<?php

declare(strict_types=1);

namespace App\Core\Traits;

use App\Models\AdditionalPayFrequency;
use App\Models\MonthlyPayFrequency;
use App\Models\WeeklyPayFrequency;
use Illuminate\Support\Facades\Log;

/**
 * ClosePayPeriodTrait
 * 
 * Provides helper method to close pay periods using model instance saves
 * instead of mass updates. This ensures Laravel observers fire correctly.
 * 
 * CRITICAL: Mass updates via query builder DO NOT fire observers.
 * We must retrieve model instances and call save() to trigger observers.
 */
trait ClosePayPeriodTrait
{
    /**
     * Close pay period(s) using model instance saves to trigger observers.
     * 
     * This method replaces mass update() calls which don't fire observers.
     * It retrieves model instances and calls save() to ensure observers fire.
     * 
     * @param string $startDate Pay period from date (Y-m-d)
     * @param string $endDate Pay period to date (Y-m-d)
     * @param string $workerType Worker type ('1099' or 'w2')
     * @param int $openStatusFromBank Open status from bank (0 or 1)
     * @return void
     */
    protected function closePayPeriodWithObserver(
        string $startDate,
        string $endDate,
        string $workerType,
        int $openStatusFromBank = 0
    ): void {
        $isW2 = strtolower($workerType) === 'w2';
        $statusField = $isW2 ? 'w2_closed_status' : 'closed_status';
        $bankField = $isW2 ? 'w2_open_status_from_bank' : 'open_status_from_bank';

        // Close Weekly Pay Frequency
        $weekly = WeeklyPayFrequency::where([
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate
        ])->first();

        if ($weekly) {
            $weekly->{$statusField} = 1;
            $weekly->{$bankField} = $openStatusFromBank;
            $weekly->save(); // Triggers observer

            Log::debug('[ClosePayPeriod] Closed weekly pay period', [
                'from' => $startDate,
                'to' => $endDate,
                'worker_type' => $workerType,
                'field' => $statusField
            ]);
        }

        // Close Monthly Pay Frequency
        $monthly = MonthlyPayFrequency::where([
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate
        ])->first();

        if ($monthly) {
            $monthly->{$statusField} = 1;
            $monthly->{$bankField} = $openStatusFromBank;
            $monthly->save(); // Triggers observer

            Log::debug('[ClosePayPeriod] Closed monthly pay period', [
                'from' => $startDate,
                'to' => $endDate,
                'worker_type' => $workerType,
                'field' => $statusField
            ]);
        }

        // Close Additional Pay Frequency (Bi-Weekly & Semi-Monthly)
        $additional = AdditionalPayFrequency::where([
            'pay_period_from' => $startDate,
            'pay_period_to' => $endDate
        ])->first();

        if ($additional) {
            $additional->{$statusField} = 1;
            $additional->{$bankField} = $openStatusFromBank;
            $additional->save(); // Triggers observer

            Log::debug('[ClosePayPeriod] Closed additional pay period', [
                'from' => $startDate,
                'to' => $endDate,
                'worker_type' => $workerType,
                'type' => $additional->type,
                'field' => $statusField
            ]);
        }
    }
}
