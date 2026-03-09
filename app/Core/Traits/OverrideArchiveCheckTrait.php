<?php

namespace App\Core\Traits;

use App\Services\OverrideManagementService;
use Illuminate\Support\Facades\Log;

trait OverrideArchiveCheckTrait
{
    /**
     * Check if override should be created based on archive status
     * This prevents recreation of overrides that were previously deleted
     * 
     * MAIN REQUIREMENT:
     * - If override is DELETED and in archive → DO NOT create during recalculation
     * - If override is NOT in archive → CREATE when date is added/recalculated
     * 
     * Returns: true if should create, false if should skip (archived)
     */
    protected function shouldCreateOverride($userId, $pid, $type, $saleUserId = null, $isProjection = false)
    {
        $overrideService = app(OverrideManagementService::class);
        
        // if ($isProjection) {
        //     return !$overrideService->isProjectionOverrideArchived($userId, $pid, $type, $saleUserId);
        // } else {
        //     return !$overrideService->isOverrideArchived($userId, $pid, $type, $saleUserId);
        // }

        return !$overrideService->isOverrideArchived($userId, $pid, $type, $saleUserId);
    }

    /**
     * Log override creation skip due to archive status
     */
    protected function logOverrideCreationSkipped($userId, $pid, $type, $saleUserId = null, $isProjection = false)
    {
        $overrideType = $isProjection ? 'projection' : 'normal';
        $saleUserIdStr = $saleUserId ? "sale_user_id: {$saleUserId}" : 'sale_user_id: null';
        
        Log::info("Override creation skipped due to archive status", [
            'user_id' => $userId,
            'pid' => $pid,
            'type' => $type,
            'override_type' => $overrideType,
            'sale_user_id' => $saleUserIdStr,
            'reason' => 'Override was previously deleted and archived'
        ]);
    }

    /**
     * Check and skip override creation if archived
     * 
     * USAGE: if ($this->checkAndSkipIfArchived(...)) { create override; }
     * 
     * Returns:
     * - true = Override is NOT archived → PROCEED with creation
     * - false = Override IS archived → SKIP creation (was deleted)
     * 
     * This ensures:
     * ✅ FIRST-TIME milestone date addition: No archive entry exists → returns true → CREATES override
     * ✅ After deletion: Archive entry exists → returns false → SKIPS creation (prevents recreation)
     * ✅ Normal recalculation: No archive entry → returns true → CREATES override
     * 
     * CRITICAL: Only blocks creation when archive entry EXISTS. If no archive entry, creation proceeds normally.
     */
    protected function checkAndSkipIfArchived($userId, $pid, $type, $saleUserId = null, $isProjection = false)
    {
        if (!$this->shouldCreateOverride($userId, $pid, $type, $saleUserId, $isProjection)) {
            $this->logOverrideCreationSkipped($userId, $pid, $type, $saleUserId, $isProjection);
            return false; // Skip creation
        }
        
        return true; // Proceed with creation
    }
}
