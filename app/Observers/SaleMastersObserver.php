<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SalesMaster;
use App\Models\SaleMastersHistory;

class SaleMastersObserver
{
    /**
     * Handle events after database transaction commits.
     * 
     * NOTE: Disabled afterCommit because it causes issues with tracking changes:
     * - After commit, getDirty() is cleared
     * - getChanges() persists but we lose old values from getOriginal()
     * - This makes proper history tracking impossible
     * 
     * Without afterCommit:
     * - Observer runs BEFORE transaction commits
     * - We can access both old and new values reliably
     * - If history creation fails, the whole transaction rolls back (safer)
     * - Tradeoff: History failure will prevent sale save (acceptable for data integrity)
     */
    public bool $afterCommit = false;

    /**
     * Handle the SalesMaster "created" event.
     */
    public function created(SalesMaster $sale): void
    {
        $this->logHistory($sale, 'create');
    }

    /**
     * Handle the SalesMaster "updating" event (BEFORE save).
     * NOTE: Not used anymore - using getChanges() in updated() instead.
     * Keeping this for reference in case we need pre-save hooks in future.
     */
    public function updating(SalesMaster $sale): void
    {
        // No longer needed - getChanges() in updated() works perfectly
    }

    /**
     * Handle the SalesMaster "updated" event.
     */
    public function updated(SalesMaster $sale): void
    {
        // Prevent duplicate logging
        static $processingSales = [];

        $key = $sale->id . '_' . time();
        
        if (isset($processingSales[$key])) {
            return;
        }

        $processingSales[$key] = true;

        $this->logHistory($sale, 'update');

        // Clean up old entries
        if (count($processingSales) > 100) {
            $processingSales = array_slice($processingSales, -50, 50, true);
        }
    }

    /**
     * Handle the SalesMaster "deleted" event.
     */
    public function deleted(SalesMaster $sale): void
    {
        $this->logHistory($sale, 'delete');
    }

    /**
     * Log history for sale changes.
     * 
     * Note: This runs BEFORE transaction commits (afterCommit = false)
     * This ensures we can properly track changed fields using getDirty().
     */
    protected function logHistory(SalesMaster $sale, string $changeType): void
    {
        // Safely get context data with fallbacks
        $changedBy = null;
        $ipAddress = null;
        $userAgent = null;
        
        try {
            $changedBy = auth()->id();
        } catch (\Throwable $e) {
            // Queue jobs may not have auth context
            $changedBy = null;
        }
        
        try {
            $ipAddress = request()?->ip();
            $userAgent = request()?->userAgent();
        } catch (\Throwable $e) {
            // Console/queue context may not have request
            $ipAddress = null;
            $userAgent = null;
        }

        // Determine the source of THIS SPECIFIC CHANGE (not the original sale source)
        $currentActionSource = $this->determineCurrentActionSource($sale);

        try {
            if ($changeType === 'create') {
                // For create, store initial values in new_values
                $newValues = $this->getChangedAttributes($sale);

                if (empty($newValues)) {
                    return;
                }

                SaleMastersHistory::create([
                    'sale_master_id' => $sale->id,
                    'pid' => $sale->pid,
                    'change_type' => 'create',
                    'changed_by' => $changedBy,
                    'data_source_type' => $currentActionSource,
                    'old_values' => null,
                    'new_values' => $newValues,
                    'changed_fields' => array_keys($newValues),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

            } elseif ($changeType === 'update') {
                // For update, track ONLY changed fields
                $changes = $this->getOnlyChangedFields($sale);

                if (empty($changes)) {
                    return;
                }

                SaleMastersHistory::create([
                    'sale_master_id' => $sale->id,
                    'pid' => $sale->pid,
                    'change_type' => 'update',
                    'changed_by' => $changedBy,
                    'data_source_type' => $currentActionSource,
                    'old_values' => $changes['old'],
                    'new_values' => $changes['new'],
                    'changed_fields' => $changes['fields'],
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

            } elseif ($changeType === 'delete') {
                // For delete, store final values in old_values
                $oldValues = $this->getChangedAttributes($sale);

                SaleMastersHistory::create([
                    'sale_master_id' => $sale->id,
                    'pid' => $sale->pid,
                    'change_type' => 'delete',
                    'changed_by' => $changedBy,
                    'data_source_type' => $currentActionSource,
                    'old_values' => $oldValues,
                    'new_values' => null,
                    'changed_fields' => array_keys($oldValues),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
            }
        } catch (\Throwable $e) {
            // CRITICAL: Log detailed error if history creation fails
            // With afterCommit=false, this will rollback the entire transaction
            \Log::error('[OBSERVER] CRITICAL: Failed to create sale history', [
                'sale_id' => $sale->id ?? null,
                'pid' => $sale->pid ?? null,
                'change_type' => $changeType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'sale_data_source' => $sale->data_source_type ?? null,
            ]);
            
            // Re-throw to rollback transaction (with afterCommit=false)
            throw $e;
        }
    }

    /**
     * Determine the source of the current action (not the original sale source)
     * 
     * This is critical for accurate history tracking. The data_source_type should reflect
     * WHO/WHAT made THIS CHANGE, not where the sale originally came from.
     * 
     * Priority:
     * 1. Request path analysis (for web/API requests) - For detecting manual edits
     * 2. Sale's data_source_type (most reliable for imports after our fix)
     * 3. LegacyApiRawDataHistory (fallback for edge cases)
     * 4. Explicitly set source (for special cases)
     * 5. Final fallback to 'unknown'
     * 
     * @param SalesMaster $sale
     * @return string
     */
    protected function determineCurrentActionSource(SalesMaster $sale): string
    {
        // Priority 1: Check request path for MANUAL EDITS (highest priority)
        // Manual edits always happen via HTTP requests, so this catches them first
        try {
            $request = request();
            if ($request && $request->path()) {
                $path = $request->path();
                
                // Manual sale operations via UI/API (catch-all for any API v2 sales endpoint)
                // Check this FIRST to catch manual edits before anything else
                if (str_contains($path, 'api/v2/sales') ||
                    str_contains($path, 'add-manual-sale') || 
                    str_contains($path, 'manual-sale') ||
                    str_contains($path, '/manual/') ||
                    str_contains($path, 'edit-sale') ||
                    str_contains($path, 'update-sale') ||
                    str_contains($path, 'recalculate_sale_data')) {
                    // Make sure it's not an import/excel endpoint
                    if (!str_contains($path, 'excel') && !str_contains($path, 'import')) {
                        return 'manual';
                    }
                }
                
                // Excel import processing
                if (str_contains($path, 'excel') || 
                    str_contains($path, 'import') ||
                    str_contains($path, 'sales-import')) {
                    return 'excel';
                }
                
                // API integrations
                if (str_contains($path, 'fieldroutes')) {
                    return 'fieldroutes';
                }
                if (str_contains($path, 'denver')) {
                    return 'Denver';
                }
                if (str_contains($path, 'pocomos')) {
                    return 'pocomos';
                }
                
                // Alert resolution
                if (str_contains($path, 'alert') || str_contains($path, 'resolve')) {
                    return 'alert';
                }
            }
        } catch (\Throwable $e) {
            // Request may not be available
        }
        
        // Priority 2: Use sale's data_source_type (most reliable after our fix)
        // Since we now correctly set data_source_type in SalesMaster table,
        // this is the most reliable source for imports
        if (!empty($sale->data_source_type)) {
            return $sale->data_source_type;
        }
        
        // Priority 3: Check explicitly set source (for special cases)
        if (isset($sale->_current_action_source) && !empty($sale->_current_action_source)) {
            return $sale->_current_action_source;
        }
        
        // Priority 4: Check LegacyApiRawDataHistory (fallback for edge cases)
        try {
            $rawData = \App\Models\LegacyApiRawDataHistory::where('pid', $sale->pid)
                ->whereIn('import_to_sales', [0, 1])
                ->latest()
                ->first(['data_source_type', 'updated_at']);
                
            if ($rawData && $rawData->data_source_type) {
                return $rawData->data_source_type;
            }
        } catch (\Throwable $e) {
            // Table may not exist or query may fail
        }
        
        // Priority 5: Final fallback
        return 'unknown';
    }

    /**
     * Get all non-null attributes from sale.
     */
    protected function getChangedAttributes(SalesMaster $sale): array
    {
        $data = [];
        $attributes = $sale->getAttributes();

        foreach ($attributes as $key => $value) {
            // Skip certain fields
            if (in_array($key, ['id', 'created_at', 'updated_at'])) {
                continue;
            }

            // Only include non-null values
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Get ONLY changed fields with old and new values.
     * Uses getDirty() which works properly without afterCommit.
     */
    protected function getOnlyChangedFields(SalesMaster $sale): array
    {
        $changedFields = [];
        $oldValues = [];
        $newValues = [];

        // Fields to exclude from history tracking (calculated/auto-generated fields)
        $excludedFields = [
            'created_at',
            'updated_at',
            'total_commission_amount',
            'total_override_amount',
            'total_commission',
            'total_override',
            'projected_commission',
            'projected_override',
            'product_code', // Auto-generated from product_id
            'm1_date', // Legacy - use milestone_trigger instead
            'm2_date', // Legacy - use milestone_trigger instead
            'sales_rep_email', // Email not needed in history
            'rep_email', // Email not needed in history
            'closer1_name', // Track ID instead, show name on FE
            'closer2_name', // Track ID instead, show name on FE
            'setter1_name', // Track ID instead, show name on FE
            'setter2_name', // Track ID instead, show name on FE
            'sales_rep_name', // Track ID instead, show name on FE
            'sales_setter_name', // Track ID instead, show name on FE
        ];

        // With afterCommit=false, getDirty() works perfectly
        $dirty = $sale->getDirty();

        foreach ($dirty as $field => $newValue) {
            // Skip excluded fields
            if (in_array($field, $excludedFields)) {
                continue;
            }

            $oldValue = $sale->getOriginal($field);

            // ONLY log if values actually different
            // Special handling for JSON fields like milestone_trigger
            if ($field === 'milestone_trigger') {
                if (!$this->jsonEquals($oldValue, $newValue)) {
                    // Parse milestone_trigger to track individual milestone changes
                    // Works for ALL company types (Solar, Turf, Pest, Roofing, Fiber, Mortgage)
                    $oldMilestones = is_string($oldValue) ? json_decode($oldValue, true) : $oldValue;
                    $newMilestones = is_string($newValue) ? json_decode($newValue, true) : $newValue;
                    
                    // Instead of storing the entire JSON, store individual milestone changes
                    // This makes history readable: "M1 Date: 2026-01-01 → 2026-02-01"
                    if (is_array($oldMilestones) && is_array($newMilestones)) {
                        // Track each milestone by name (e.g., "M1 Date", "M2 Date", "Funding Date")
                        $oldMilestoneMap = [];
                        $newMilestoneMap = [];
                        
                        foreach ($oldMilestones as $m) {
                            if (isset($m['name']) && is_string($m['name'])) {
                                // Use milestone name as key (e.g., "M1 Date", "M2 Date")
                                $oldMilestoneMap[$m['name']] = $m['date'] ?? null;
                            }
                        }
                        
                        foreach ($newMilestones as $m) {
                            if (isset($m['name']) && is_string($m['name'])) {
                                $newMilestoneMap[$m['name']] = $m['date'] ?? null;
                            }
                        }
                        
                        // Find what changed (handles date additions, removals, and modifications)
                        $allMilestoneNames = array_unique(array_merge(
                            array_keys($oldMilestoneMap),
                            array_keys($newMilestoneMap)
                        ));
                        
                        // Collect all milestone changes into a single field
                        $milestoneChanges = [];
                        foreach ($allMilestoneNames as $milestoneName) {
                            $oldDate = $oldMilestoneMap[$milestoneName] ?? null;
                            $newDate = $newMilestoneMap[$milestoneName] ?? null;
                            
                            // Only log if the date actually changed
                            // Handles: null→date, date→null, date1→date2
                            if ($oldDate !== $newDate) {
                                $milestoneChanges[] = [
                                    'name' => $milestoneName,
                                    'old' => $oldDate,
                                    'new' => $newDate
                                ];
                            }
                        }
                        
                        // Store as single "Milestone Trigger" field
                        if (!empty($milestoneChanges)) {
                            $changedFields[] = 'Milestone Trigger';
                            
                            // Build readable format: "M2 Date: 2026-01-01, M3 Date: 2026-02-01"
                            $oldValueParts = [];
                            $newValueParts = [];
                            
                            foreach ($milestoneChanges as $change) {
                                if ($change['old']) {
                                    $oldValueParts[] = $change['name'] . ': ' . $change['old'];
                                } else {
                                    $oldValueParts[] = $change['name'] . ': Not set';
                                }
                                
                                if ($change['new']) {
                                    $newValueParts[] = $change['name'] . ': ' . $change['new'];
                                } else {
                                    $newValueParts[] = $change['name'] . ': Not set';
                                }
                            }
                            
                            $oldValues['Milestone Trigger'] = !empty($oldValueParts) ? implode(', ', $oldValueParts) : 'Not set';
                            $newValues['Milestone Trigger'] = !empty($newValueParts) ? implode(', ', $newValueParts) : 'Not set';
                        }
                    } else {
                        // Fallback: store the entire JSON if parsing fails
                        // This ensures we don't lose history even if format is unexpected
                        $changedFields[] = $field;
                        $oldValues[$field] = $oldValue;
                        $newValues[$field] = $newValue;
                    }
                }
            } elseif ($oldValue !== $newValue) {
                $changedFields[] = $field;
                $oldValues[$field] = $oldValue;
                $newValues[$field] = $newValue;
            }
        }

        if (empty($changedFields)) {
            return [];
        }

        return [
            'fields' => $changedFields,
            'old' => $oldValues,
            'new' => $newValues,
        ];
    }

    /**
     * Compare two JSON values to see if they're actually different
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return bool True if same, false if different
     */
    protected function jsonEquals($oldValue, $newValue): bool
    {
        // Decode if strings
        $old = is_string($oldValue) ? json_decode($oldValue, true) : $oldValue;
        $new = is_string($newValue) ? json_decode($newValue, true) : $newValue;

        // Handle nulls
        if ($old === null && $new === null) {
            return true;
        }
        if ($old === null || $new === null) {
            return false;
        }

        // For milestone_trigger, compare only the dates (ignore name/trigger text changes)
        if (is_array($old) && is_array($new) && isset($old[0]['date'])) {
            $oldDates = array_column($old, 'date');
            $newDates = array_column($new, 'date');
            sort($oldDates);
            sort($newDates);
            return json_encode($oldDates) === json_encode($newDates);
        }

        // Compare as arrays (normalize JSON encoding)
        return json_encode($old, JSON_UNESCAPED_SLASHES) === json_encode($new, JSON_UNESCAPED_SLASHES);
    }
}
