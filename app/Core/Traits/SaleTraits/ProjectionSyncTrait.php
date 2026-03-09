<?php

namespace App\Core\Traits\SaleTraits;

use App\Models\ProjectionUserCommission;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait ProjectionSyncTrait
{
    /**
     * Ensure projection data exists in ProjectionUserCommission table when needed
     * This fixes the disconnect between projected_commission flag and actual projection amounts
     */
    public function ensureProjectionDataExists($pid, $sale = null)
    {
        if (! $sale) {
            $sale = SalesMaster::where('pid', $pid)->first();
        }

        if (! $sale || $sale->date_cancelled) {
            return;
        }

        // INFINITE LOOP PREVENTION: Check if we're already processing this PID
        $cacheKey = "projection_sync_in_progress_{$pid}";
        $retryKey = "projection_sync_failed_{$pid}";

        // If sync is already in progress for this PID, skip
        if (Cache::has($cacheKey)) {
            Log::warning("Projection sync already in progress for PID {$pid}, skipping to prevent infinite loop", [
                'pid' => $pid,
                'customer_name' => $sale->customer_name,
            ]);

            return;
        }

        // If sync has failed recently (within last 5 minutes), skip to prevent rapid retries
        if (Cache::has($retryKey)) {
            $failureCount = Cache::get($retryKey, 0);
            if ($failureCount >= 3) {
                Log::warning("Projection sync has failed {$failureCount} times for PID {$pid}, skipping to prevent infinite loop", [
                    'pid' => $pid,
                    'customer_name' => $sale->customer_name,
                    'failure_count' => $failureCount,
                ]);

                return;
            }
        }

        // Check if projection data already exists for this PID
        $existingProjections = ProjectionUserCommission::where('pid', $pid)->count();

        if ($existingProjections == 0) {
            // Set cache flag to prevent concurrent processing
            Cache::put($cacheKey, true, 300); // 5 minutes

            Log::info("No projection data found for PID {$pid}, triggering sync", [
                'pid' => $pid,
                'customer_name' => $sale->customer_name,
            ]);

            try {
                // Call the projection sync command for this specific PID
                Artisan::call('syncSalesProjectionData:sync', ['pid' => $pid]);

                // Verify projection data was created
                $newProjections = ProjectionUserCommission::where('pid', $pid)->count();

                if ($newProjections > 0) {
                    // Success - clear any failure cache
                    Cache::forget($retryKey);
                    Log::info("Projection sync completed successfully for PID {$pid}", [
                        'pid' => $pid,
                        'projections_created' => $newProjections,
                        'success' => true,
                    ]);
                } else {
                    // Sync command ran but didn't create data - this is a failure
                    $failureCount = Cache::get($retryKey, 0) + 1;
                    Cache::put($retryKey, $failureCount, 300); // Remember failure for 5 minutes

                    Log::error("Projection sync failed to create data for PID {$pid}", [
                        'pid' => $pid,
                        'projections_created' => $newProjections,
                        'failure_count' => $failureCount,
                        'success' => false,
                    ]);
                }

            } catch (\Exception $e) {
                // Exception occurred - increment failure count
                $failureCount = Cache::get($retryKey, 0) + 1;
                Cache::put($retryKey, $failureCount, 300); // Remember failure for 5 minutes

                Log::error("Exception during projection sync for PID {$pid}", [
                    'pid' => $pid,
                    'error' => $e->getMessage(),
                    'failure_count' => $failureCount,
                ]);
            } finally {
                // Always clear the in-progress flag
                Cache::forget($cacheKey);
            }
        }
    }

    /**
     * Verify that projection flag matches actual projection data
     * Returns true if they are synchronized, false if there's a mismatch
     */
    public function verifyProjectionSync($pid)
    {
        $sale = SalesMaster::where('pid', $pid)->first();
        if (! $sale) {
            return false;
        }

        $hasProjectionFlag = $sale->projected_commission == 1;
        $hasProjectionData = ProjectionUserCommission::where('pid', $pid)->exists();
        $hasPendingMilestones = SaleProductMaster::where('pid', $pid)
            ->whereNull('milestone_date')
            ->where('is_projected', 1)
            ->exists();

        return [
            'pid' => $pid,
            'has_projection_flag' => $hasProjectionFlag,
            'has_projection_data' => $hasProjectionData,
            'has_pending_milestones' => $hasPendingMilestones,
            'is_synchronized' => $hasProjectionFlag === $hasProjectionData,
            'needs_sync' => $hasProjectionFlag && ! $hasProjectionData,
        ];
    }
}
