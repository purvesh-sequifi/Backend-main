<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\WeeklyPayFrequency;

/**
 * WeeklyPayFrequencyObserver
 * 
 * Auto-creates the next weekly pay period (+7 days) when a period is closed.
 */
class WeeklyPayFrequencyObserver extends BasePayFrequencyObserver
{
    protected function getModelClass(): string
    {
        return WeeklyPayFrequency::class;
    }
    
    protected function calculateNext($lastPeriod): array
    {
        return $this->calculateNextWeeklyPeriod($lastPeriod);
    }
    
    protected function getTableName(): string
    {
        return 'weekly_pay_frequencies';
    }
}
