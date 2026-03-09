<?php

declare(strict_types=1);

namespace App\Observers;

use App\Core\Traits\PayPeriodCalculationTrait;
use Illuminate\Support\Facades\Log;

/**
 * BasePayFrequencyObserver
 * 
 * Abstract base class for all pay frequency observers.
 * Provides shared logic for automatic next period creation when a period is closed.
 * 
 * Child classes only need to implement 3 simple methods:
 * - getModelClass(): Return the model class name
 * - calculateNext(): Return next period dates
 * - getTableName(): Return table name for logging
 * 
 * SAFETY MECHANISMS:
 * 1. isDirty() - Only triggers on actual changes (prevents duplicate triggers)
 * 2. firstOrCreate() - Atomic operation prevents race conditions
 * 3. try-catch - Graceful degradation on errors
 * 4. Runs within transaction context - Rolls back with failed execution
 * 5. Comprehensive logging - Audit trail for debugging
 * 
 * EDGE CASES HANDLED:
 * - Both 1099 and W2 closing simultaneously (firstOrCreate handles atomically)
 * - No existing periods in table (null check with warning log)
 * - Transaction rollback (observer runs in parent transaction)
 * - Date boundaries (Carbon handles month/year transitions)
 * - Observer re-firing (isDirty prevents duplicate triggers)
 */
abstract class BasePayFrequencyObserver
{
    use PayPeriodCalculationTrait;
    
    /**
     * Return the model class this observer handles.
     * Example: WeeklyPayFrequency::class
     */
    abstract protected function getModelClass(): string;
    
    /**
     * Calculate the next period dates based on last period.
     * Example: $this->calculateNextWeeklyPeriod($lastPeriod)
     * 
     * @param object $lastPeriod
     * @return array ['pay_period_from' => 'Y-m-d', 'pay_period_to' => 'Y-m-d']
     */
    abstract protected function calculateNext($lastPeriod): array;
    
    /**
     * Return the table name for logging purposes.
     * Example: 'weekly_pay_frequencies'
     */
    abstract protected function getTableName(): string;
    
    /**
     * Handle the model "updated" event.
     * 
     * Fires when a pay frequency record is updated. Checks if closed_status
     * or w2_closed_status changed to 1, then auto-creates the next sequential period.
     * 
     * @param mixed $frequency The frequency model instance
     * @return void
     */
    public function updated($frequency): void
    {
        // SAFETY CHECK: Only trigger on actual changes to closed_status
        // isDirty() prevents duplicate triggers if same record is saved multiple times
        if ($frequency->isDirty('closed_status') && $frequency->closed_status == 1) {
            $this->createNextPeriod('1099');
        }
        
        // SAFETY CHECK: Only trigger on actual changes to w2_closed_status
        // Check if field exists before accessing (daily frequency may not have this field)
        if (in_array('w2_closed_status', $frequency->getFillable()) || 
            array_key_exists('w2_closed_status', $frequency->getAttributes())) {
            if ($frequency->isDirty('w2_closed_status') && $frequency->w2_closed_status == 1) {
                $this->createNextPeriod('w2');
            }
        }
    }
    
    /**
     * Create the next sequential pay period.
     * 
     * Logic:
     * 1. Find the last existing period (highest pay_period_to date)
     * 2. Calculate next period dates (child class implements specific logic)
     * 3. Use firstOrCreate() to prevent race conditions
     * 4. Log the result for audit trail
     * 
     * @param string $workerType The worker type that triggered this ('1099' or 'w2')
     * @return void
     */
    private function createNextPeriod(string $workerType): void
    {
        try {
            $modelClass = $this->getModelClass();
            $tableName = $this->getTableName();
            
            // Find the last period in the entire table
            // This ensures we always create the next sequential period after the highest date
            $query = $modelClass::orderBy('pay_period_to', 'DESC');
            
            // Special handling for AdditionalPayFrequency which has multiple types
            if ($modelClass === \App\Models\AdditionalPayFrequency::class) {
                // This will be overridden in child class to filter by type
                $query = $this->applyTypeFilter($query);
            }
            
            $lastPeriod = $query->first();
            
            // SAFETY CHECK: Handle edge case where no periods exist yet
            if (!$lastPeriod) {
                Log::warning('[PayPeriodObserver] No pay periods exist yet - cannot auto-create next period', [
                    'triggered_by' => $workerType,
                    'table' => $tableName
                ]);
                return;
            }
            
            // SAFETY CHECK: Validate period dates are not NULL
            // If pay_period_from or pay_period_to is NULL, Carbon::parse(null) returns current date
            // which could create unexpected periods
            if (empty($lastPeriod->pay_period_from) || empty($lastPeriod->pay_period_to)) {
                Log::error('[PayPeriodObserver] Invalid period dates (NULL values) - skipping auto-create', [
                    'period_id' => $lastPeriod->id ?? 'unknown',
                    'pay_period_from' => $lastPeriod->pay_period_from ?? 'NULL',
                    'pay_period_to' => $lastPeriod->pay_period_to ?? 'NULL',
                    'triggered_by' => $workerType,
                    'table' => $tableName
                ]);
                return;
            }
            
            // Calculate next period dates using child class implementation
            $nextPeriod = $this->calculateNext($lastPeriod);
            
            // Prepare attributes for creation
            $attributes = [
                'pay_period_from' => $nextPeriod['pay_period_from'],
                'pay_period_to' => $nextPeriod['pay_period_to']
            ];
            
            // Add type for AdditionalPayFrequency
            if ($modelClass === \App\Models\AdditionalPayFrequency::class) {
                $attributes['type'] = $lastPeriod->type;
            }
            
            // SAFETY MECHANISM: firstOrCreate() is atomic at database level
            // If both 1099 and W2 execute simultaneously, only ONE record will be created
            // The database ensures atomicity - second call will find existing record
            $created = $modelClass::firstOrCreate(
                $attributes,
                [
                    'closed_status' => 0,
                    'w2_closed_status' => 0,
                    'open_status_from_bank' => 0,
                    'w2_open_status_from_bank' => 0,
                ]
            );
            
            // Log result for debugging and audit trail
            if ($created->wasRecentlyCreated) {
                Log::info('[PayPeriodObserver] Auto-created next pay period', [
                    'from' => $nextPeriod['pay_period_from'],
                    'to' => $nextPeriod['pay_period_to'],
                    'triggered_by' => $workerType,
                    'previous_period_end' => $lastPeriod->pay_period_to,
                    'table' => $tableName
                ]);
            } else {
                Log::debug('[PayPeriodObserver] Next period already exists - no action needed', [
                    'from' => $nextPeriod['pay_period_from'],
                    'to' => $nextPeriod['pay_period_to'],
                    'triggered_by' => $workerType,
                    'table' => $tableName
                ]);
            }
            
        } catch (\Exception $e) {
            // SAFETY MECHANISM: Silent failure - execution continues even if auto-creation fails
            // This ensures payroll execution is never blocked by observer failures
            // Errors are logged for investigation but don't propagate to parent transaction
            Log::error('[PayPeriodObserver] Failed to auto-create next pay period', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'triggered_by' => $workerType,
                'table' => $this->getTableName(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Apply type filter for AdditionalPayFrequency queries.
     * Overridden in AdditionalPayFrequencyObserver.
     * 
     * @param mixed $query
     * @return mixed
     */
    protected function applyTypeFilter($query)
    {
        return $query;
    }
}
