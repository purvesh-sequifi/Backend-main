<?php

declare(strict_types=1);

namespace App\Jobs\Sales;

use App\Services\JobNotificationService;
use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SaleProcessJob implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    public const BATCH_KEY_PREFIX = 'sale_process_batch_';

    private const BATCH_STATE_TTL_SECONDS = 259200; // 3 days (matches NotificationService TTL)
    private const NOTIFICATION_THROTTLE_SECONDS = 5;
    private const PROGRESS_CAP_PROCESSING = 99;

    public $ids;

    public $timeout = 14400; // Increase timeout to 4 hours

    public $tries = 3; // Allow more retries if the job fails

    // Add backoff to prevent immediate retries when there's a database issue
    public $backoff = [60, 120, 300, 600]; // Wait 1m, 2m, 5m, 10m between retries

    public string $notificationUniqueKey;
    public string $notificationInitiatedAt;

    protected ?int $batchChunkNumber = null;
    protected ?int $batchTotalChunks = null;
    protected ?int $batchTotalRecords = null;
    protected ?int $batchSize = null;
    protected ?string $batchDataSourceType = null;

    /**
     * @param array<int,int|string> $ids
     */
    public function __construct(
        $ids,
        ?string $batchNotificationKey = null,
        ?string $batchInitiatedAt = null,
        ?int $chunkNumber = null,
        ?int $totalChunks = null,
        ?int $totalRecords = null,
        ?int $batchSize = null,
        ?string $dataSourceType = null
    )
    {
        $this->ids = $ids;
        // Batch mode (AWS Lambda): all chunks share ONE notification key to avoid N notifications.
        // Non-batch mode (legacy): preserve original per-job notifications.
        $this->notificationInitiatedAt = $batchInitiatedAt ?: now()->toIso8601String();
        if (is_string($batchNotificationKey) && $batchNotificationKey !== '') {
            $this->notificationUniqueKey = $batchNotificationKey;
        } else {
            $firstId = is_array($ids) && !empty($ids) ? (string) reset($ids) : 'unknown';
            $count = is_array($ids) ? count($ids) : 0;
            $this->notificationUniqueKey = 'sale_process_' . $firstId . '_' . $count . '_' . time();
        }

        // Store batch context for better messages/progress (best-effort).
        $this->batchChunkNumber = $chunkNumber;
        $this->batchTotalChunks = $totalChunks;
        $this->batchTotalRecords = $totalRecords;
        $this->batchSize = $batchSize;
        $this->batchDataSourceType = $dataSourceType;
        $this->onQueue('sales-process');
    }

    private function isBatchMode(): bool
    {
        return $this->batchChunkNumber !== null
            && $this->batchTotalChunks !== null
            && $this->batchTotalChunks > 0
            && is_string($this->notificationUniqueKey)
            && str_starts_with($this->notificationUniqueKey, self::BATCH_KEY_PREFIX);
    }

    private function redisKeyPrefix(): string
    {
        // Redis namespace for AWS Lambda batch progress.
        //
        // New format (preferred):
        //   sale_process_batch:<suffix>  where <suffix> is notificationUniqueKey without leading "sale_process_batch_"
        //
        // Legacy format (kept for in-flight/backward compatibility):
        //   sale_process_batch:<notificationUniqueKey>
        //
        // We dynamically select the legacy namespace if any legacy keys already exist,
        // to avoid resetting progress mid-run during a deploy.
        $legacyPrefix = $this->legacyRedisKeyPrefix();
        if ($legacyPrefix !== '') {
            try {
                $exists = Redis::exists($legacyPrefix . ':started')
                    || Redis::exists($legacyPrefix . ':total_records')
                    || Redis::exists($legacyPrefix . ':completed_chunks');
                if ($exists) {
                    return $legacyPrefix;
                }
            } catch (\Throwable $e) {
                Log::debug('SaleProcessJob: Redis legacy prefix detection failed (best-effort)', [
                    'unique_key' => $this->notificationUniqueKey ?? null,
                    'legacy_prefix' => $legacyPrefix,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->newRedisKeyPrefix();
    }

    private function newRedisKeyPrefix(): string
    {
        $key = (string) ($this->notificationUniqueKey ?? '');
        $suffix = $key;
        if (str_starts_with($key, self::BATCH_KEY_PREFIX)) {
            $suffix = substr($key, strlen(self::BATCH_KEY_PREFIX));
        }

        return 'sale_process_batch:' . $suffix;
    }

    private function legacyRedisKeyPrefix(): string
    {
        $key = (string) ($this->notificationUniqueKey ?? '');
        if ($key === '' || ! str_starts_with($key, self::BATCH_KEY_PREFIX)) {
            return '';
        }

        return 'sale_process_batch:' . $key;
    }

    private function safeRedisGetInt(string $key, int $default = 0): int
    {
        try {
            return (int) (Redis::get($key) ?? $default);
        } catch (\Throwable $e) {
            Log::warning('SaleProcessJob: Redis get failed, using default', [
                'key' => $key,
                'default' => $default,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    private function safeRedisIncr(string $key): ?int
    {
        try {
            return (int) Redis::incr($key);
        } catch (\Throwable $e) {
            Log::warning('SaleProcessJob: Redis incr failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function safeRedisExpire(string $key, int $ttlSeconds): void
    {
        try {
            Redis::expire($key, $ttlSeconds);
        } catch (\Throwable $e) {
            Log::warning('SaleProcessJob: Redis expire failed', [
                'key' => $key,
                'ttl' => $ttlSeconds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function safeRedisSetNxWithTtl(string $key, string $value, int $ttlSeconds): bool
    {
        try {
            return (bool) Redis::set($key, $value, 'EX', $ttlSeconds, 'NX');
        } catch (\Throwable $e) {
            Log::warning('SaleProcessJob: Redis SET NX failed', [
                'key' => $key,
                'ttl' => $ttlSeconds,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function chunkFinishedMarkerKey(): string
    {
        $chunk = (int) ($this->batchChunkNumber ?? 0);
        return $this->redisKeyPrefix() . ':chunk_finished:' . $chunk;
    }

    private function shouldEmitBatchNotificationNow(bool $force): bool
    {
        if ($force) {
            return true;
        }

        $gateKey = $this->redisKeyPrefix() . ':notify_throttle';
        return $this->safeRedisSetNxWithTtl($gateKey, '1', self::NOTIFICATION_THROTTLE_SECONDS);
    }

    private function sourceSuffix(): string
    {
        $source = is_string($this->batchDataSourceType) ? trim($this->batchDataSourceType) : '';
        if ($source === '') {
            return '';
        }

        return ' [source: ' . $source . ']';
    }

    private function estimateProcessedRecords(int $completedChunks): int
    {
        $total = (int) ($this->batchTotalRecords ?? 0);
        $size = (int) ($this->batchSize ?? 0);
        if ($total <= 0 || $size <= 0 || $completedChunks <= 0) {
            return 0;
        }
        return (int) min($total, $completedChunks * $size);
    }

    public function handle(): void
    {
        try {
            $count = is_array($this->ids) ? count($this->ids) : 0;
            if ($this->isBatchMode()) {
                // Emit "started" only once per batch to avoid toast spam.
                $startedKey = $this->redisKeyPrefix() . ':started';
                // Atomic "set if not exists + TTL" to avoid a forever key if the process crashes
                // between setnx() and expire().
                try {
                    $started = Redis::set($startedKey, '1', 'EX', self::BATCH_STATE_TTL_SECONDS, 'NX') ? 1 : 0;
                } catch (\Throwable $e) {
                    // Redis issues should not break sales processing; we only lose "emit started once" behavior.
                    Log::warning('SaleProcessJob: Redis started guard failed', [
                        'key' => $startedKey,
                        'error' => $e->getMessage(),
                    ]);
                    $started = 0;
                }

                if ($started === 1) {
                    // Store batch context for downstream progress emitters (e.g. SalesProcessController)
                    // so they can display unified progress instead of per-500 row progress.
                    try {
                        Redis::setex($this->redisKeyPrefix() . ':total_records', self::BATCH_STATE_TTL_SECONDS, (string) ((int) ($this->batchTotalRecords ?? 0)));
                        Redis::setex($this->redisKeyPrefix() . ':total_chunks', self::BATCH_STATE_TTL_SECONDS, (string) ((int) ($this->batchTotalChunks ?? 0)));
                        Redis::setex($this->redisKeyPrefix() . ':batch_size', self::BATCH_STATE_TTL_SECONDS, (string) ((int) ($this->batchSize ?? 0)));
                        Redis::setex($this->redisKeyPrefix() . ':data_source_type', self::BATCH_STATE_TTL_SECONDS, (string) ((string) ($this->batchDataSourceType ?? '')));
                    } catch (\Throwable $e) {
                        Log::debug('SaleProcessJob: Redis batch context write failed (best-effort)', [
                            'unique_key' => $this->notificationUniqueKey ?? null,
                            'prefix' => $this->redisKeyPrefix(),
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $total = (int) ($this->batchTotalRecords ?? 0);
                    $chunks = (int) ($this->batchTotalChunks ?? 0);
                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_process',
                        'SaleProcessJob',
                        'started',
                        0,
                        sprintf(
                            'Sales processing: %s / %s sales (batch %d/%d)',
                            number_format(0),
                            number_format($total),
                            1,
                            $chunks
                        ) . $this->sourceSuffix(),
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'record_count' => $count,
                            'data_source_type' => $this->batchDataSourceType,
                            'batch_size' => $this->batchSize,
                            'total_records' => $this->batchTotalRecords,
                            'total_chunks' => $this->batchTotalChunks,
                            'completed_chunks' => 0,
                        ]
                    );
                }

                // Always emit a processing update indicating current chunk being worked on.
                $completedChunks = $this->safeRedisGetInt($this->redisKeyPrefix() . ':completed_chunks', 0);

                // Smooth progress under parallel workers:
                // track how many chunks have STARTED (best-effort) so we can avoid showing the same
                // processed/progress value for multiple concurrently-starting chunks.
                $startedChunksKey = $this->redisKeyPrefix() . ':started_chunks';
                $startedChunks = $this->safeRedisIncr($startedChunksKey);
                if ($startedChunks !== null) {
                    $this->safeRedisExpire($startedChunksKey, self::BATCH_STATE_TTL_SECONDS);
                }

                $effectiveCompletedForEstimate = $completedChunks;
                if ($startedChunks !== null && $startedChunks > 0) {
                    // We pre-incremented started chunks for this job, so "startedChunks - 1"
                    // represents chunks that started before this one.
                    $effectiveCompletedForEstimate = max($effectiveCompletedForEstimate, $startedChunks - 1);
                }

                $processedRecords = $this->estimateProcessedRecords($effectiveCompletedForEstimate);
                $total = (int) ($this->batchTotalRecords ?? 0);
                $chunks = (int) ($this->batchTotalChunks ?? 0);
                $progress = $chunks > 0
                    ? (int) max(1, min(self::PROGRESS_CAP_PROCESSING, floor(($effectiveCompletedForEstimate / $chunks) * 99)))
                    : 1;

                if ($this->shouldEmitBatchNotificationNow(false)) {
                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_process',
                        'SaleProcessJob',
                        'processing',
                        $progress,
                        sprintf(
                            'Sales processing: %s / %s sales (batch %d/%d)',
                            number_format($processedRecords),
                            number_format($total),
                            (int) ($this->batchChunkNumber ?? 0),
                            $chunks
                        ) . $this->sourceSuffix(),
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'record_count' => $count,
                            'data_source_type' => $this->batchDataSourceType,
                            'batch_size' => $this->batchSize,
                            'total_records' => $this->batchTotalRecords,
                            'total_chunks' => $this->batchTotalChunks,
                            'completed_chunks' => $completedChunks,
                            'current_chunk' => $this->batchChunkNumber,
                        ]
                    );
                }
            } else {
                // Legacy behavior: per-job notifications
                app(JobNotificationService::class)->notify(
                    null,
                    'sales_process',
                    'SaleProcessJob',
                    'started',
                    0,
                    "Sales processing started for {$count} record(s).",
                    $this->notificationUniqueKey,
                    $this->notificationInitiatedAt,
                    null,
                    [
                        'record_count' => $count,
                        'data_source_type' => null,
                    ]
                );
            }

            // Log detailed information at the start of job handling
            Log::info('SaleProcessJob starting execution', [
                'attempt' => $this->attempts(),
                'queue' => $this->queue ?? 'unknown',
                'memory_usage_start' => memory_get_usage(true) / 1024 / 1024 .'MB',
            ]);

            // Increase memory limit for this job to 8GB for larger datasets
            ini_set('memory_limit', '8192M');

            // Explicitly reconnect to the database to prevent connection timeout issues
            DB::disconnect('mysql');
            DB::reconnect('mysql');
            Log::info('SaleProcessJob: Reconnected to database');

            // Pass the callback to the controller method
            $namespace = app()->getNamespace();
            $salesProcessController = app()->make($namespace.\Http\Controllers\API\V2\Sales\SalesProcessController::class);
            $salesProcessController->integrationSaleProcess(
                $this->ids,
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                null,
                null
            );

            Log::info('Completed SaleProcessJob');

            $count = is_array($this->ids) ? count($this->ids) : 0;
            if ($this->isBatchMode()) {
                // Ensure we only count this chunk once (success or failure).
                // This prevents double-counting if an exception occurs after incrementing completion counters.
                $finishedKey = $this->chunkFinishedMarkerKey();
                $countThisChunk = $this->safeRedisSetNxWithTtl($finishedKey, '1', self::BATCH_STATE_TTL_SECONDS);

                $completedKey = $this->redisKeyPrefix() . ':completed_chunks';
                $completedChunks = $countThisChunk ? $this->safeRedisIncr($completedKey) : $this->safeRedisGetInt($completedKey, 0);
                $this->safeRedisExpire($completedKey, self::BATCH_STATE_TTL_SECONDS);

                $chunks = (int) ($this->batchTotalChunks ?? 0);
                $total = (int) ($this->batchTotalRecords ?? 0);
                $effectiveCompletedChunks = $completedChunks ?? 0;
                $processedRecords = $this->estimateProcessedRecords($effectiveCompletedChunks);

                // If Redis is unavailable, fall back to a best-effort progress based on current chunk number.
                $fallbackCompleted = (int) ($this->batchChunkNumber ?? 0);
                $effectiveCompletedChunks = $completedChunks !== null ? $effectiveCompletedChunks : max(0, $fallbackCompleted);
                $isDone = $chunks > 0 && $effectiveCompletedChunks >= $chunks;

                $status = $isDone ? 'completed' : 'processing';
                $progress = $isDone ? 100 : (int) max(1, min(self::PROGRESS_CAP_PROCESSING, floor(($effectiveCompletedChunks / max(1, $chunks)) * 99)));
                $message = $isDone
                    ? sprintf('Sales processing: %s / %s sales (batch %d/%d)', number_format($total), number_format($total), $chunks, $chunks)
                    : sprintf(
                        'Sales processing: %s / %s sales (batch %d/%d)',
                        number_format($processedRecords),
                        number_format($total),
                        $effectiveCompletedChunks,
                        $chunks
                    );
                $message .= $this->sourceSuffix();

                if ($this->shouldEmitBatchNotificationNow($isDone)) {
                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_process',
                        'SaleProcessJob',
                        $status,
                        $progress,
                        $message,
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        $isDone ? now()->toIso8601String() : null,
                        [
                            'record_count' => $count,
                            'data_source_type' => $this->batchDataSourceType,
                            'batch_size' => $this->batchSize,
                            'total_records' => $this->batchTotalRecords,
                            'total_chunks' => $this->batchTotalChunks,
                            'completed_chunks' => $effectiveCompletedChunks,
                            'current_chunk' => $this->batchChunkNumber,
                        ]
                    );
                }
            } else {
                app(JobNotificationService::class)->notify(
                    null,
                    'sales_process',
                    'SaleProcessJob',
                    'completed',
                    100,
                    "Sales processing completed for {$count} record(s).",
                    $this->notificationUniqueKey,
                    $this->notificationInitiatedAt,
                    now()->toIso8601String(),
                    [
                        'record_count' => $count,
                        'data_source_type' => null,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Enhanced error logging with more context
            Log::error('SaleProcessJob failed with exception', [
                'attempt' => $this->attempts(),
                'queue' => $this->queue ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 .'MB',
            ]);

            // Check if this is a database connection issue
            $errorMsg = $e->getMessage();

            if (
                str_contains($errorMsg, 'SQLSTATE[HY000]') ||
                str_contains($errorMsg, 'Error while reading greeting packet') ||
                str_contains($errorMsg, 'Lost connection') ||
                str_contains($errorMsg, 'gone away')
            ) {
                Log::error('SaleProcessJob database connection error detected', [
                    'error' => $errorMsg,
                    'attempt' => $this->attempts(),
                ]);

                // Attempt to reconnect before giving up
                if ($this->attempts() < $this->tries) {
                    try {
                        DB::disconnect('mysql');
                        DB::reconnect('mysql');
                        Log::info('SaleProcessJob attempted emergency database reconnection');
                    } catch (\Exception $reconnectError) {
                        Log::error('Failed emergency database reconnection', [
                            'error' => $reconnectError->getMessage(),
                        ]);
                    }
                }
            }

            // Re-throw to trigger the failed method
            throw $e;
        }
    }

    public function failed(\Throwable $e)
    {
        // Log when job is definitively considered failed by Laravel
        Log::error('SaleProcessJob marked as FAILED by Laravel queue system', [
            'final_attempt' => $this->attempts(),
            'error_message' => $e->getMessage(),
            'memory_usage_final' => memory_get_usage(true) / 1024 / 1024 .'MB',
        ]);

        // Persist failure notification (best-effort)
        try {
            $count = is_array($this->ids) ? count($this->ids) : 0;
            if ($this->isBatchMode()) {
                // Ensure we only count this chunk once (success or failure).
                $finishedKey = $this->chunkFinishedMarkerKey();
                $countThisChunk = $this->safeRedisSetNxWithTtl($finishedKey, '1', self::BATCH_STATE_TTL_SECONDS);

                $failedKey = $this->redisKeyPrefix() . ':failed_chunks';
                $failedChunks = $countThisChunk ? ((int) ($this->safeRedisIncr($failedKey) ?? 0)) : $this->safeRedisGetInt($failedKey, 0);
                $this->safeRedisExpire($failedKey, self::BATCH_STATE_TTL_SECONDS);

                // Count failed chunks toward completion so the batch can reach a terminal state.
                $completedKey = $this->redisKeyPrefix() . ':completed_chunks';
                $completedChunks = $countThisChunk ? ((int) ($this->safeRedisIncr($completedKey) ?? 0)) : $this->safeRedisGetInt($completedKey, 0);
                $this->safeRedisExpire($completedKey, self::BATCH_STATE_TTL_SECONDS);

                $chunks = (int) ($this->batchTotalChunks ?? 0);
                $total = (int) ($this->batchTotalRecords ?? 0);
                $processedRecords = $this->estimateProcessedRecords($completedChunks);
                $isDone = $chunks > 0 && $completedChunks >= $chunks;
                $progress = $isDone ? 100 : ($chunks > 0 ? (int) max(1, min(self::PROGRESS_CAP_PROCESSING, floor(($completedChunks / $chunks) * 99))) : 1);
                $status = $isDone ? 'failed' : 'processing';

                if ($this->shouldEmitBatchNotificationNow($isDone)) {
                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_process',
                        'SaleProcessJob',
                        $status,
                        $progress,
                        sprintf(
                            'Sales processing: %s / %s sales (batch %d/%d). Errors: %d.',
                            number_format($processedRecords),
                            number_format($total),
                            (int) ($this->batchChunkNumber ?? 0),
                            $chunks,
                            $failedChunks
                        ) . $this->sourceSuffix(),
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                        $isDone ? now()->toIso8601String() : null,
                        [
                            'record_count' => $count,
                            'data_source_type' => $this->batchDataSourceType,
                            'batch_size' => $this->batchSize,
                            'total_records' => $this->batchTotalRecords,
                            'total_chunks' => $this->batchTotalChunks,
                            'completed_chunks' => $completedChunks,
                            'failed_chunks' => $failedChunks,
                            'current_chunk' => $this->batchChunkNumber,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            } else {
                app(JobNotificationService::class)->notify(
                    null,
                    'sales_process',
                    'SaleProcessJob',
                    'failed',
                    0,
                    "Sales processing failed for {$count} record(s): {$e->getMessage()}",
                    $this->notificationUniqueKey ?? ('sale_process_' . $count . '_' . time()),
                    $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                    now()->toIso8601String(),
                    [
                        'record_count' => $count,
                        'data_source_type' => null,
                    ]
                );
            }
        } catch (\Throwable $ignore) {
            // never fail the failed() handler
        }
    }
}
