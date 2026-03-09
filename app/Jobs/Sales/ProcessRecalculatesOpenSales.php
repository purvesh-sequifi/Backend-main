<?php

namespace App\Jobs\Sales;

use App\Events\PositionUpdateProgress;
use App\Events\sendEventToPusher;
use App\Services\JobNotificationService;
use App\Services\JobPerformanceTracker;
use App\Services\NotificationService;
use App\Traits\EmailNotificationTrait;
use Illuminate\Support\Collection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessRecalculatesOpenSales implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 7200; // 2 hours (handles 10K+ sales)

    /**
     * Normalized list of PIDs to process.
     *
     * @var array<int, string>
     */
    protected array $pid = [];

    protected $dataForPusher;

    protected string $notificationUniqueKey;
    protected string $notificationInitiatedAt;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($pid, $dataForPusher)
    {
        $this->onQueue('sales-process');
        $this->dataForPusher = $dataForPusher;
        $this->notificationInitiatedAt = now()->toIso8601String();

        // Normalize PIDs to a plain array to avoid Collection/Countable edge cases and to keep
        // error handling (array_diff, count, md5 hash) safe across all dispatch sites.
        if ($pid instanceof Collection) {
            $pids = $pid->values()->all();
        } elseif (is_array($pid)) {
            $pids = $pid;
        } else {
            $pids = [$pid];
        }

        $pids = array_values(array_filter(array_map(static fn ($v) => (string) $v, $pids), static fn ($v) => $v !== ''));
        $this->pid = $pids;

        $sortedPids = $this->pid;
        sort($sortedPids);
        $pidHash = md5(implode(',', $sortedPids));
        $this->notificationUniqueKey = 'open_sales_recalc_' . $pidHash . '_' . time();
    }


    /**
     * Get the unique ID for the job.
     * Prevents duplicate jobs with the same PIDs from being queued.
     */
    public function uniqueId(): string
    {
        // Sort PIDs to ensure consistent hashing regardless of order
        $sortedPids = $this->pid;
        sort($sortedPids);
        return md5(implode(',', $sortedPids));
    }

    /**
     * The number of seconds the unique lock should be maintained.
     */
    public function uniqueFor(): int
    {
        return 3600; // 1 hour lock to prevent duplicates during and after processing
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $processedPids = [];
        $failedPids = [];

        $recipientUserId = isset($this->dataForPusher['user_id']) ? (int) $this->dataForPusher['user_id'] : null;
        $recipientUserIds = $this->dataForPusher['recipient_user_ids'] ?? null;
        if (is_array($recipientUserIds)) {
            $recipientUserIds = array_values(array_unique(array_filter(array_map(
                static fn ($id) => is_numeric($id) ? (int) $id : null,
                $recipientUserIds
            ), static fn ($id) => is_int($id) && $id > 0)));
        } else {
            $recipientUserIds = null;
        }
        app(JobNotificationService::class)->notify(
            $recipientUserId,
            'sales_recalculate_open_sales',
            'ProcessRecalculatesOpenSales',
            'started',
            0,
            'Open sales recalculation started.',
            $this->notificationUniqueKey,
            $this->notificationInitiatedAt,
            null,
            [
                'pid_count' => is_array($this->pid) ? count($this->pid) : 1,
            ],
            $recipientUserIds
        );

        // Initialize performance tracker
        $tracker = new JobPerformanceTracker();

        // Start batch tracking and get the batch ID
        $batchId = $tracker->startBatch(
            'ProcessRecalculatesOpenSales',
            count($this->pid),
            1, // Single chunk
            'sales-process',
            $this->dataForPusher['user_id'] ?? null,
            $this->dataForPusher
        );

        try {
            \Log::info('[ProcessRecalculatesOpenSales] Starting job', [
                'batch_id' => $batchId,
                'pids' => $this->pid,
                'count' => count($this->pid),
            ]);

            // Note: Historically, broadcasts were avoided here to prevent confusing UX during position update.
            // With Redis-backed notifications, we can safely surface status without impacting the position-update progress bar.

            // Start chunk tracking
            $tracker->startChunk($batchId, 1, $this->pid);

            $totalPids = is_array($this->pid) ? count($this->pid) : 1;
            $processedCountForProgress = 0;
            foreach ($this->pid as $pid) {
                try {
                    $namespace = app()->getNamespace();
                    $SaleRecalculateController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesController::class);

                    $request = new \Illuminate\Http\Request;
                    $request->merge(['pid' => $pid]);

                    $response = $SaleRecalculateController->recalculateSaleData($request);

                    // Check if the response indicates failure
                    if ($response->getStatusCode() !== 200) {
                        $responseData = json_decode($response->getContent(), true);
                        $errorMessage = $responseData['Message'] ?? 'Unknown error';

                        \Log::warning('[ProcessRecalculatesOpenSales] PID recalculation failed', [
                            'pid' => $pid,
                            'status_code' => $response->getStatusCode(),
                            'error' => $errorMessage,
                        ]);

                        $failedPids[] = [
                            'pid' => $pid,
                            'error' => $errorMessage,
                        ];

                        continue;
                    }

                    $processedPids[] = $pid;
                    \Log::info('[ProcessRecalculatesOpenSales] PID recalculated successfully', ['pid' => $pid]);

                } catch (Throwable $pidException) {
                    \Log::error('[ProcessRecalculatesOpenSales] Exception processing PID', [
                        'pid' => $pid,
                        'exception' => $pidException->getMessage(),
                        'trace' => $pidException->getTraceAsString(),
                    ]);

                    $failedPids[] = [
                        'pid' => $pid,
                        'error' => $pidException->getMessage(),
                    ];
                }

                $processedCountForProgress++;
                if ($totalPids > 0 && ($processedCountForProgress === 1 || $processedCountForProgress === $totalPids || ($processedCountForProgress % 50) === 0)) {
                    $recipientUserId = isset($this->dataForPusher['user_id']) ? (int) $this->dataForPusher['user_id'] : null;
                    $progress = (int) round(($processedCountForProgress / max(1, $totalPids)) * 95);
                    app(JobNotificationService::class)->notify(
                        $recipientUserId,
                        'sales_recalculate_open_sales',
                        'ProcessRecalculatesOpenSales',
                        'processing',
                        min(95, max(1, $progress)),
                        "Recalculating open sales ({$processedCountForProgress}/{$totalPids})...",
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'batch_id' => $batchId,
                            'processed_count' => count($processedPids),
                            'failed_count' => count($failedPids),
                            ],
                            $recipientUserIds
                    );
                }
            }

            // Complete chunk tracking
            $tracker->completeChunk(
                $batchId,
                1,
                count($processedPids),
                count($failedPids),
                !empty($failedPids) ? $failedPids : null
            );

            // Complete batch tracking
            $tracker->completeBatch($batchId);

            $duration = round((microtime(true) - $startTime), 2);
            $throughput = count($this->pid) > 0 ? round(count($this->pid) / $duration, 2) : 0;

            \Log::info('[ProcessRecalculatesOpenSales] Job completed', [
                'batch_id' => $batchId,
                'processed_count' => count($processedPids),
                'failed_count' => count($failedPids),
                'duration_seconds' => $duration,
                'throughput_pids_per_sec' => $throughput,
                'processed_pids' => $processedPids,
                'failed_pids' => $failedPids,
            ]);

            /* Send event to pusher */
            $pusherMsg = count($failedPids) > 0
                ? sprintf('Sale recalculation completed with %d successes and %d failures', count($processedPids), count($failedPids))
                : 'Sale recalculated successfully';
            $pusherEvent = 'recalculate-sale';
            $domainName = config('app.domain_name');
            $dataForPusherEvent = [];
            if (! empty($this->dataForPusher)) {
                $dataForPusherEvent = $this->dataForPusher;
            }
            $dataForPusherEvent['processed_count'] = count($processedPids);
            $dataForPusherEvent['failed_count'] = count($failedPids);
            $dataForPusherEvent['batch_id'] = $batchId;
            // event(new sendEventToPusher($domainName, $pusherEvent, $pusherMsg, $dataForPusherEvent));

            // If all PIDs failed, mark the job as failed
            if (count($failedPids) > 0 && count($processedPids) === 0) {
                throw new \Exception(sprintf('All %d PIDs failed to recalculate', count($failedPids)));
            }

        } catch (Throwable $e) {
            // Mark tracking as failed
            if (isset($batchId) && isset($tracker)) {
                $remainingPids = array_values(array_diff($this->pid, $processedPids));
                $tracker->completeChunk(
                    $batchId,
                    1,
                    count($processedPids),
                    count($this->pid) - count($processedPids),
                    [['error' => $e->getMessage(), 'pids' => $remainingPids]]
                );
            }

            \Log::error('[ProcessRecalculatesOpenSales] Job failed with exception', [
                'batch_id' => $batchId ?? 'unknown',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'processed_pids' => $processedPids,
                'failed_pids' => $failedPids,
                'attempt' => $this->attempts(),
            ]);

            // Persist failure notification (best-effort)
            try {
                $recipientUserId = isset($this->dataForPusher['user_id']) ? (int) $this->dataForPusher['user_id'] : null;
                app(JobNotificationService::class)->notify(
                    $recipientUserId,
                    'sales_recalculate_open_sales',
                    'ProcessRecalculatesOpenSales',
                    'failed',
                    0,
                    'Open sales recalculation failed: ' . $e->getMessage(),
                    $this->notificationUniqueKey ?? ('open_sales_recalc_' . $this->uniqueId() . '_' . time()),
                    $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                    now()->toIso8601String(),
                    [
                        'batch_id' => $batchId ?? null,
                        'processed_count' => count($processedPids),
                        'failed_count' => count($failedPids),
                    ],
                    $recipientUserIds
                );
            } catch (\Throwable $ignore) {
                // never throw from error path
            }

            throw $e; // Re-throw to mark job as failed and trigger retry
        }

        // If we got here, the job finished successfully (even if some pids failed).
        try {
            $recipientUserId = isset($this->dataForPusher['user_id']) ? (int) $this->dataForPusher['user_id'] : null;
            app(JobNotificationService::class)->notify(
                $recipientUserId,
                'sales_recalculate_open_sales',
                'ProcessRecalculatesOpenSales',
                'completed',
                100,
                sprintf('Open sales recalculation completed. Success: %d, Failed: %d.', count($processedPids), count($failedPids)),
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String(),
                [
                    'processed_count' => count($processedPids),
                    'failed_count' => count($failedPids),
                ],
                $recipientUserIds
            );
        } catch (\Throwable $ignore) {
            // best-effort only
        }
        
        // Send final 100% broadcast AFTER main try-catch (outside sales processing)
        // If broadcast fails, job should still be marked as successful (sales were saved)
        try {
            $positionId = $this->dataForPusher['position_id'] ?? null;
            if ($positionId) {
                $positionContext = \Cache::get('position_update_context_' . $positionId);
                
                if ($positionContext) {
                    // Calculate total duration from position update start
                    $totalDuration = \Carbon\Carbon::parse($positionContext['initiated_at'])->diffInSeconds(now());
                    $durationFormatted = gmdate('i\m s\s', $totalDuration);  // "1m 11s" format
                    $salesCount = count($processedPids);
                    
                    // Format dates for user-friendly display
                    $completedDate = now()->format('M d, Y g:i A');  // "Dec 24, 2025 4:25 PM"
                    
                    // Send final 100% completion broadcast
                    $finalNotificationData = [
                        'positionId' => (int)$positionId,
                        'positionName' => $positionContext['position_name'],
                        'status' => 'completed',
                        'progress' => 100,
                        'message' => "Position '{$positionContext['position_name']}' updated successfully on {$completedDate}. Duration: {$durationFormatted} | {$salesCount} sales recalculated",
                        'updatedBy' => $positionContext['updated_by'],
                        'updatedById' => $positionContext['updated_by_id'],
                        'initiatedAt' => $positionContext['initiated_at'],
                        'completedAt' => now()->toDateTimeString(),
                        'uniqueKey' => $positionContext['unique_key'],
                        'salesRecalculated' => $salesCount,
                        'salesList' => $processedPids,
                        'timestamp' => now()->toISOString()
                    ];
                    
                    broadcast(new PositionUpdateProgress($finalNotificationData));
                    
                    // 🆕 STORE IN REDIS (Non-blocking, graceful failure)
                    try {
                        app(NotificationService::class)->storeNotification(
                            $positionContext['updated_by_id'],
                            'position_update',
                            $finalNotificationData
                        );
                    } catch (\Exception $redisError) {
                        // Silent failure - Redis storage is optional enhancement
                    }
                    
                    \Log::info('🎉 Final 100% broadcast sent after sales recalculation', [
                        'position_id' => $positionId,
                        'sales_processed' => $salesCount,
                        'total_duration' => $durationFormatted
                    ]);
                    
                    // Clean up cache
                    \Cache::forget('position_update_context_' . $positionId);
                }
            }
        } catch (\Exception $broadcastError) {
            // Log broadcast failure but don't fail the job - sales were already saved successfully
            \Log::warning('Final broadcast failed but sales recalculation succeeded', [
                'position_id' => $this->dataForPusher['position_id'] ?? null,
                'error' => $broadcastError->getMessage(),
                'sales_processed' => count($processedPids)
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        // Log when job is definitively considered failed by Laravel
        \Log::error('[ProcessRecalculatesOpenSales] Job marked as FAILED by Laravel queue system', [
            'final_attempt' => $this->attempts(),
            'error_message' => $exception->getMessage(),
            'pids' => $this->pid,
            'queue' => 'sales-process',
        ]);
        
        // Send failure broadcast so frontend doesn't stay stuck at 95%
        $positionId = $this->dataForPusher['position_id'] ?? null;
        if ($positionId) {
            $positionContext = \Cache::get('position_update_context_' . $positionId);
            
            if ($positionContext) {
                try {
                    broadcast(new PositionUpdateProgress([
                        'positionId' => (int)$positionId,
                        'positionName' => $positionContext['position_name'],
                        'status' => 'completed',  // Still mark as completed (position updated successfully)
                        'progress' => 100,
                        'message' => "Position '{$positionContext['position_name']}' updated. Sales recalculation failed but position changes saved.",
                        'updatedBy' => $positionContext['updated_by'],
                        'updatedById' => $positionContext['updated_by_id'],
                        'initiatedAt' => $positionContext['initiated_at'],
                        'completedAt' => now()->toDateTimeString(),
                        'uniqueKey' => $positionContext['unique_key'],
                        'salesRecalculated' => 0,
                        'salesRecalculationFailed' => true,
                        'salesList' => []
                    ]));
                    
                    \Log::info('Sent failure recovery broadcast - position update was successful', [
                        'position_id' => $positionId
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to send failure broadcast', ['error' => $e->getMessage()]);
                }
                
                // Clean up cache
                \Cache::forget('position_update_context_' . $positionId);
            }
        }
    }
}
