<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\API\V2\Sales\SalesController;
use App\Models\JobChunkMetric;
use App\Models\JobPerformanceMetric;
use App\Services\JobNotificationService;
use App\Services\JobPerformanceTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalculateSalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PROGRESS_CAP_PROCESSING = 99;
    private const BATCH_CONTEXT_CACHE_TTL_SECONDS = 5;
    private const BATCH_CONTEXT_CACHE_LOCK_SECONDS = 10;
    private const BATCH_CONTEXT_CACHE_LOCK_BLOCK_SECONDS = 2;

    public $timeout = 1800; // 30 minutes

    protected array $pids;
    protected string $batchId;
    protected int $chunkNumber;
    protected ?int $recipientUserId;
    protected ?int $totalChunksHint;
    protected ?int $chunkSizeHint;
    protected string $notificationUniqueKey;
    protected string $notificationInitiatedAt;

    public function __construct(
        array $pids,
        string $batchId = '',
        int $chunkNumber = 0,
        ?int $recipientUserId = null,
        ?int $totalChunksHint = null,
        ?int $chunkSizeHint = null
    )
    {
        $this->pids = $pids;
        $this->batchId = $batchId;
        $this->chunkNumber = $chunkNumber;
        $this->recipientUserId = $recipientUserId;
        $this->totalChunksHint = $totalChunksHint;
        $this->chunkSizeHint = $chunkSizeHint;
        $this->notificationInitiatedAt = now()->toIso8601String();
        // IMPORTANT:
        // For recalculate-sale-all, we want ONE unified notification for the entire batch (not one per chunk).
        // All chunk jobs should write updates to the same uniqueKey so the UI shows a single card/stream.
        $this->notificationUniqueKey = $batchId !== ''
            ? ('recalc_sales_batch_' . $batchId)
            : ('recalc_sales_' . 'no_batch' . '_chunk_' . $chunkNumber);
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $tracker = new JobPerformanceTracker();

        $recipientUserId = $this->resolveRecipientUserId();
        // Non-batch usage (legacy): keep the original per-job notifications.
        if ($this->batchId === '') {
            app(JobNotificationService::class)->notify(
                $recipientUserId,
                'sales_recalculate',
                'RecalculateSalesJob',
                'started',
                0,
                'Sales recalculation started.',
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                null,
                [
                    'batch_id' => null,
                    'chunk_number' => $this->chunkNumber,
                    'pids_count' => count($this->pids),
                ]
            );
        }
        
        // Start chunk tracking if batch ID is provided
        if ($this->batchId) {
            $tracker->startChunk($this->batchId, $this->chunkNumber, $this->pids);
        }

        // Emit a unified "processing chunk (x/y)" update (monotonic progress based on completed chunks).
        if ($this->batchId !== '') {
            [$totalChunks, $completedChunks, $totalSuccess, $totalFailed, $totalSales, $chunkSize] = $this->getBatchContext();
            if ($totalChunks > 0) {
                $progress = $this->calculateBatchProgress($completedChunks, $totalChunks, true);
                $processedSales = $this->estimateProcessedSales($completedChunks, $chunkSize, $totalSales);
                app(JobNotificationService::class)->notify(
                    $recipientUserId,
                    'sales_recalculate',
                    'RecalculateSalesJob',
                    'processing',
                    $progress,
                    sprintf(
                        'Sales recalculation: %s / %s sales (chunk %d/%d)',
                        number_format($processedSales),
                        number_format($totalSales),
                        $this->chunkNumber,
                        $totalChunks
                    ),
                    $this->notificationUniqueKey,
                    $this->notificationInitiatedAt,
                    null,
                    [
                        'batch_id' => $this->batchId,
                        'chunk_number' => $this->chunkNumber,
                        'total_chunks' => $totalChunks,
                        'completed_chunks' => $completedChunks,
                        'success_count' => $totalSuccess,
                        'failed_count' => $totalFailed,
                        'pids_count' => count($this->pids),
                        'total_pids' => $totalSales,
                        'chunk_size' => $chunkSize,
                    ]
                );
            }
        }

        $controller = new SalesController();
        $successCount = 0;
        $failedCount = 0;
        $errorDetails = [];

        Log::info("RecalculateSalesJob started", [
            'batch_id' => $this->batchId,
            'chunk_number' => $this->chunkNumber,
            'pids_count' => count($this->pids),
            'pids' => $this->pids,
            'memory_start' => memory_get_usage(true),
            'time_start' => $startTime
        ]);

        foreach ($this->pids as $pid) {
            $pidStartTime = microtime(true);
            request()->merge(['pid' => $pid]);
            
            try {
                $controller->recalculateSale(request(), true);
                $successCount++;
                
                $pidDuration = round((microtime(true) - $pidStartTime) * 1000, 2);
                Log::info("Successfully recalculated PID {$pid}", [
                    'pid' => $pid,
                    'duration_ms' => $pidDuration,
                    'memory_usage' => memory_get_usage(true),
                    'batch_id' => $this->batchId
                ]);
                
            } catch (\Exception $e) {
                $failedCount++;
                $pidDuration = round((microtime(true) - $pidStartTime) * 1000, 2);
                $errorDetails[] = [
                    'pid' => $pid,
                    'error' => $e->getMessage(),
                    'duration_ms' => $pidDuration
                ];
                
                Log::error("Failed to recalculate PID {$pid}", [
                    'pid' => $pid,
                    'error' => $e->getMessage(),
                    'duration_ms' => $pidDuration,
                    'memory_usage' => memory_get_usage(true),
                    'batch_id' => $this->batchId
                ]);
            }

            // Legacy per-job progress notifications (only when no batchId is provided).
            if ($this->batchId === '') {
                $processed = $successCount + $failedCount;
                $total = max(1, count($this->pids));
                if ($processed === 1 || $processed === $total || ($processed % 25) === 0) {
                    $progress = (int) round(($processed / $total) * 95);
                    app(JobNotificationService::class)->notify(
                        $recipientUserId,
                        'sales_recalculate',
                        'RecalculateSalesJob',
                        'processing',
                        min(95, max(1, $progress)),
                        "Recalculating sales ({$processed}/{$total})...",
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'batch_id' => null,
                            'chunk_number' => $this->chunkNumber,
                            'success_count' => $successCount,
                            'failed_count' => $failedCount,
                        ]
                    );
                }
            }
        }

        $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
        $throughput = count($this->pids) > 0 ? round(count($this->pids) / ((microtime(true) - $startTime)), 2) : 0;

        // Complete chunk tracking
        if ($this->batchId) {
            $tracker->completeChunk(
                $this->batchId,
                $this->chunkNumber,
                $successCount,
                $failedCount,
                !empty($errorDetails) ? $errorDetails : null
            );
        }

        Log::info("RecalculateSalesJob completed", [
            'batch_id' => $this->batchId,
            'chunk_number' => $this->chunkNumber,
            'total_pids' => count($this->pids),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'duration_ms' => $totalDuration,
            'throughput_pids_per_sec' => $throughput,
            'memory_peak' => memory_get_peak_usage(true),
            'memory_end' => memory_get_usage(true)
        ]);

        // Legacy completion notification (only when no batchId is provided).
        if ($this->batchId === '') {
            app(JobNotificationService::class)->notify(
                $recipientUserId,
                'sales_recalculate',
                'RecalculateSalesJob',
                'completed',
                100,
                "Sales recalculation completed. Success: {$successCount}, Failed: {$failedCount}.",
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String(),
                [
                    'batch_id' => null,
                    'chunk_number' => $this->chunkNumber,
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                ]
            );
        }

        // Update the unified batch notification after this chunk completes.
        if ($this->batchId !== '') {
            [$totalChunks, $completedChunks, $totalSuccess, $totalFailed, $totalSales, $chunkSize] = $this->getBatchContext();
            if ($totalChunks > 0) {
                $isBatchDone = ($completedChunks >= $totalChunks);
                $status = $isBatchDone ? ($totalFailed > 0 ? 'failed' : 'completed') : 'processing';
                $progress = $isBatchDone ? 100 : $this->calculateBatchProgress($completedChunks, $totalChunks, false);
                $processedSales = $isBatchDone ? $totalSales : $this->estimateProcessedSales($completedChunks, $chunkSize, $totalSales);
                $message = $isBatchDone
                    ? sprintf(
                        'Sales recalculation: %s / %s sales (chunk %d/%d). Success: %d, Failed: %d.',
                        number_format($totalSales),
                        number_format($totalSales),
                        $totalChunks,
                        $totalChunks,
                        $totalSuccess,
                        $totalFailed
                    )
                    : sprintf(
                        'Sales recalculation: %s / %s sales (chunk %d/%d)',
                        number_format($processedSales),
                        number_format($totalSales),
                        $completedChunks,
                        $totalChunks
                    );

                // Throttle batch-level notification updates to reduce write/broadcast spam.
                // Always emit the final update.
                if ($isBatchDone || ($completedChunks % 5) === 0) {
                    app(JobNotificationService::class)->notify(
                        $this->resolveRecipientUserId(),
                        'sales_recalculate',
                        'RecalculateSalesJob',
                        $status,
                        $progress,
                        $message,
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        $isBatchDone ? now()->toIso8601String() : null,
                        [
                            'batch_id' => $this->batchId,
                            'chunk_number' => $this->chunkNumber,
                            'total_chunks' => $totalChunks,
                            'completed_chunks' => $completedChunks,
                            'success_count' => $totalSuccess,
                            'failed_count' => $totalFailed,
                            'total_pids' => $totalSales,
                            'chunk_size' => $chunkSize,
                        ]
                    );
                }
            }
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        // Best-effort: mark this chunk as failed in metrics (so batch progress remains accurate).
        // Then update the unified batch notification with a failure message.
        $completedAt = now()->toIso8601String();

        if ($this->batchId) {
            $tracker = new JobPerformanceTracker();
            $tracker->completeChunk(
                $this->batchId,
                $this->chunkNumber,
                0,
                count($this->pids),
                [['error' => $exception->getMessage(), 'pids' => $this->pids]]
            );
        }

        try {
            if ($this->batchId !== '') {
                [$totalChunks, $completedChunks, $totalSuccess, $totalFailed] = $this->getBatchTotals();
                if ($totalChunks > 0) {
                    $isBatchDone = ($completedChunks >= $totalChunks);
                    $status = $isBatchDone ? 'failed' : 'processing';
                    $progress = $isBatchDone ? 100 : $this->calculateBatchProgress($completedChunks, $totalChunks, false);
                    $message = $isBatchDone
                        ? "Sales recalculation completed with errors ({$completedChunks}/{$totalChunks} chunks). Success: {$totalSuccess}, Failed: {$totalFailed}."
                        : "Chunk ({$this->chunkNumber}/{$totalChunks}) failed. Progress: {$completedChunks}/{$totalChunks} chunks completed.";

                    app(JobNotificationService::class)->notify(
                        $this->resolveRecipientUserId(),
                        'sales_recalculate',
                        'RecalculateSalesJob',
                        $status,
                        $progress,
                        $message,
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                        $isBatchDone ? $completedAt : null,
                        [
                            'batch_id' => $this->batchId,
                            'chunk_number' => $this->chunkNumber,
                            'total_chunks' => $totalChunks,
                            'completed_chunks' => $completedChunks,
                            'success_count' => $totalSuccess,
                            'failed_count' => $totalFailed,
                            'error' => $exception->getMessage(),
                        ]
                    );
                }
            } else {
                // Non-batch fallback behavior
                app(JobNotificationService::class)->notify(
                    $this->resolveRecipientUserId(),
                    'sales_recalculate',
                    'RecalculateSalesJob',
                    'failed',
                    0,
                    'Sales recalculation failed: ' . $exception->getMessage(),
                    $this->notificationUniqueKey ?? ('recalc_sales_' . 'no_batch' . '_chunk_' . $this->chunkNumber),
                    $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                    $completedAt,
                    [
                        'batch_id' => null,
                        'chunk_number' => $this->chunkNumber,
                        'pids_count' => count($this->pids),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::debug('RecalculateSalesJob: failed() notification update failed (best-effort)', [
                'batch_id' => $this->batchId !== '' ? $this->batchId : null,
                'chunk_number' => $this->chunkNumber,
                'unique_key' => $this->notificationUniqueKey ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        Log::error("RecalculateSalesJob failed completely", [
            'batch_id' => $this->batchId,
            'chunk_number' => $this->chunkNumber,
            'pids' => $this->pids,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    private function resolveRecipientUserId(): ?int
    {
        if ($this->recipientUserId !== null && (int) $this->recipientUserId > 0) {
            return (int) $this->recipientUserId;
        }

        if ($this->batchId === '') {
            return null;
        }

        try {
            $metric = JobPerformanceMetric::query()
                ->where('batch_id', $this->batchId)
                ->first();

            if (!$metric || $metric->triggered_by === null || $metric->triggered_by === '') {
                return null;
            }

            $id = (int) $metric->triggered_by;
            return $id > 0 ? $id : null;
        } catch (\Throwable $e) {
            Log::debug('RecalculateSalesJob: resolveRecipientUserId failed (best-effort)', [
                'batch_id' => $this->batchId !== '' ? $this->batchId : null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int}
     *   totalChunks, completedChunks, totalSuccess, totalFailed, totalSales, chunkSize
     */
    private function getBatchContext(): array
    {
        if ($this->batchId === '') {
            return [0, 0, 0, 0, 0, 0];
        }

        $cacheKey = 'recalc_batch_context:' . $this->batchId;
        try {
            /** @var array{0:int,1:int,2:int,3:int,4:int,5:int} $cached */
            // Best-effort stampede protection: under high concurrency, multiple chunks can compute the same
            // context simultaneously. Use a short cache lock if supported by the cache store.
            try {
                $cached = Cache::lock($cacheKey . ':lock', self::BATCH_CONTEXT_CACHE_LOCK_SECONDS)
                    ->block(self::BATCH_CONTEXT_CACHE_LOCK_BLOCK_SECONDS, function () use ($cacheKey): array {
                        /** @var array{0:int,1:int,2:int,3:int,4:int,5:int} $value */
                        $value = Cache::remember($cacheKey, self::BATCH_CONTEXT_CACHE_TTL_SECONDS, function (): array {
                            return $this->getBatchContextUncached();
                        });
                        return $value;
                    });
            } catch (\Throwable $e) {
                Log::debug('RecalculateSalesJob: batch context cache lock failed (best-effort)', [
                    'batch_id' => $this->batchId,
                    'cache_key' => $cacheKey,
                    'error' => $e->getMessage(),
                ]);

                $cached = Cache::remember($cacheKey, self::BATCH_CONTEXT_CACHE_TTL_SECONDS, function (): array {
                    return $this->getBatchContextUncached();
                });
            }
            return $cached;
        } catch (\Throwable $e) {
            Log::debug('RecalculateSalesJob: batch context cache failed (best-effort)', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->getBatchContextUncached();
    }

    /**
     * Uncached batch context computation (DB-backed).
     *
     * @return array{0:int,1:int,2:int,3:int,4:int,5:int}
     */
    private function getBatchContextUncached(): array
    {
        try {
            $metric = JobPerformanceMetric::query()
                ->where('batch_id', $this->batchId)
                ->first();
            $totalChunks = (int) ($metric?->total_chunks ?? 0);
            if ($totalChunks <= 0 && $this->totalChunksHint !== null) {
                $totalChunks = (int) $this->totalChunksHint;
            }

            $totalSales = (int) ($metric?->total_pids ?? 0);

            $chunkSize = 0;
            $requestParams = $metric?->request_params;
            if (is_array($requestParams) && isset($requestParams['chunk_size']) && is_numeric($requestParams['chunk_size'])) {
                $chunkSize = (int) $requestParams['chunk_size'];
            }
            if ($chunkSize <= 0 && $this->chunkSizeHint !== null) {
                $chunkSize = (int) $this->chunkSizeHint;
            }
            if ($chunkSize <= 0) {
                $chunkSize = 20;
            }

            // Aggregate in SQL to avoid fetching all chunk rows on every update.
            $agg = JobChunkMetric::query()
                ->where('batch_id', $this->batchId)
                ->selectRaw("SUM(CASE WHEN status IN ('completed','failed') THEN 1 ELSE 0 END) AS completed_chunks")
                ->selectRaw('COALESCE(SUM(success_count), 0) AS total_success')
                ->selectRaw('COALESCE(SUM(failed_count), 0) AS total_failed')
                ->first();

            $completedChunks = (int) ($agg?->completed_chunks ?? 0);
            $totalSuccess = (int) ($agg?->total_success ?? 0);
            $totalFailed = (int) ($agg?->total_failed ?? 0);

            return [$totalChunks, $completedChunks, $totalSuccess, $totalFailed, $totalSales, $chunkSize];
        } catch (\Throwable $e) {
            Log::debug('RecalculateSalesJob: getBatchContext failed (best-effort)', [
                'batch_id' => $this->batchId !== '' ? $this->batchId : null,
                'error' => $e->getMessage(),
            ]);
            return [0, 0, 0, 0, 0, 0];
        }
    }

    /**
     * Backward-compatible helper used by failed().
     *
     * @return array{0:int,1:int,2:int,3:int}
     *   totalChunks, completedChunks, totalSuccess, totalFailed
     */
    private function getBatchTotals(): array
    {
        [$totalChunks, $completedChunks, $totalSuccess, $totalFailed] = $this->getBatchContext();
        return [(int) $totalChunks, (int) $completedChunks, (int) $totalSuccess, (int) $totalFailed];
    }

    private function calculateBatchProgress(int $completedChunks, int $totalChunks, bool $isStartingChunk): int
    {
        if ($totalChunks <= 0) {
            return 0;
        }

        $ratio = max(0.0, min(1.0, $completedChunks / max(1, $totalChunks)));
        // Keep "processing" capped below 100; 100 is reserved for explicit completion.
        $progress = (int) floor($ratio * self::PROGRESS_CAP_PROCESSING);

        // While chunks are running, show minimal non-zero progress to make it clear work is happening.
        if ($isStartingChunk && $progress === 0) {
            return 1;
        }

        return max(0, min(self::PROGRESS_CAP_PROCESSING, $progress));
    }

    private function estimateProcessedSales(int $completedChunks, int $chunkSize, int $totalSales): int
    {
        if ($totalSales <= 0 || $chunkSize <= 0 || $completedChunks <= 0) {
            return 0;
        }

        $estimate = $completedChunks * $chunkSize;
        return (int) min($totalSales, $estimate);
    }
}
