<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MonthlyPayFrequency;

/**
 * MonthlyPayFrequencyObserver
 * 
 * Auto-creates the next monthly pay period (1st to last day of month) when a period is closed.
 */
class MonthlyPayFrequencyObserver extends BasePayFrequencyObserver
{
    protected function getModelClass(): string
    {
        return MonthlyPayFrequency::class;
    }
    
    protected function calculateNext($lastPeriod): array
    {
        return $this->calculateNextMonthlyPeriod($lastPeriod);
    }
    
    protected function getTableName(): string
    {
        return 'monthly_pay_frequencies';
    }
}
