<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AdditionalPayFrequency;

/**
 * AdditionalPayFrequencyObserver
 * 
 * Auto-creates the next pay period for bi-weekly or semi-monthly frequencies.
 * Uses pattern-aware logic that replicates the actual period duration from existing records,
 * not hardcoded assumptions. This ensures it works with any company-configured structure.
 * 
 * Handles type filtering to keep bi-weekly and semi-monthly sequences separate.
 */
class AdditionalPayFrequencyObserver extends BasePayFrequencyObserver
{
    private ?string $currentType = null;
    
    /**
     * Handle the AdditionalPayFrequency "updated" event.
     * 
     * Overrides parent to capture the type from the triggering model BEFORE queries.
     * 
     * @param \App\Models\AdditionalPayFrequency $frequency
     * @return void
     */
    public function updated($frequency): void
    {
        // CRITICAL FIX: Capture type from triggering model, not from query result
        // This ensures applyTypeFilter() uses the correct type when querying for last period
        // Cast to string since database returns integer but constants are strings
        $this->currentType = $frequency->type !== null ? (string) $frequency->type : null;
        
        // Call parent updated() which will use our type filter
        parent::updated($frequency);
    }
    
    protected function getModelClass(): string
    {
        return AdditionalPayFrequency::class;
    }
    
    protected function calculateNext($lastPeriod): array
    {
        // Bi-weekly uses simple duration replication
        if ($lastPeriod->type === AdditionalPayFrequency::BI_WEEKLY_TYPE) {
            return $this->calculateNextAdditionalPeriod($lastPeriod);
        }
        
        // Semi-monthly uses comprehensive logic from trait (matches SetupController)
        return $this->calculateNextSemiMonthlyPeriod($lastPeriod);
    }
    protected function getTableName(): string
    {
        return 'additional_pay_frequencies';
    }
    
    /**
     * Apply type filter to query for AdditionalPayFrequency.
     * Ensures bi-weekly and semi-monthly sequences remain separate.
     */
    protected function applyTypeFilter($query)
    {
        if ($this->currentType) {
            return $query->where('type', $this->currentType);
        }
        return $query;
    }
}
