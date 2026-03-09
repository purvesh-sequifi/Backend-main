<?php

namespace App\Jobs\Sales;

use App\Models\LegacyApiRawDataHistory;
use App\Services\JobNotificationService;
use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\Sales\SaleProcessJob;

class SaleMasterJobAwsLambda implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    public $type;

    public $name;

    public $chunk;

    public $timeout = 14400; // Increase timeout to 4 hours

    public $tries = 5; // Allow more retries if the job fails

    public $backoff = [60, 120, 300, 600]; // Wait 1m, 2m, 5m, 10m between retries

    public $includeCloser1IdNull;

    public string $notificationUniqueKey;
    public string $notificationInitiatedAt;
    public string $saleProcessBatchNotificationKey;

    public function __construct($type = 'excel', $chunk = 100, $name = 'sales-process', $includeCloser1IdNull = false)
    {
        $this->type = $type;
        $this->name = $name;
        $this->chunk = $chunk;
        $this->includeCloser1IdNull = $includeCloser1IdNull;
        $this->notificationUniqueKey = 'sale_master_lambda_' . $type . '_' . time();
        $this->notificationInitiatedAt = now()->toIso8601String();
        // One unified notification key for all downstream SaleProcessJob chunks (prevents N notifications for N chunks).
        $this->saleProcessBatchNotificationKey = SaleProcessJob::BATCH_KEY_PREFIX . $this->notificationUniqueKey;
        $this->onQueue('sales-process');
    }

    public function handle(): void
    {
        try {
            app(JobNotificationService::class)->notify(
                null,
                'sales_master_lambda',
                'SaleMasterJobAwsLambda',
                'started',
                0,
                "Sales master (lambda) job started (type: {$this->type}).",
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
            );

            // Log detailed information at the start of job handling
            Log::info('SaleMasterJobAwsLambda starting execution', [
                'type' => $this->type,
                'attempt' => $this->attempts(),
                'queue' => $this->queue ?? 'unknown',
                'memory_usage_start' => memory_get_usage(true) / 1024 / 1024 .'MB',
            ]);

            // Increase memory limit for this job to 2GB for larger datasets
            ini_set('memory_limit', '2048M');

            // Explicitly reconnect to the database to prevent connection timeout issues
            DB::disconnect('mysql');
            DB::reconnect('mysql');
            Log::info('SaleMasterJobAwsLambda: Reconnected to database');

            $domainName = config('app.domain_name');

            $query = LegacyApiRawDataHistory::select('id')
            ->where('data_source_type', 'LIKE', $this->type . '%')
            ->where('import_to_sales', 0);

            if(! $this->includeCloser1IdNull) {
                $query->whereNotNull('closer1_id'); // Ensure we only process records with a valid closer
            }

            if ($domainName == 'momentum') {
                $query->where('customer_signoff', '>=', '2025-10-01');
            }

            $totalRecords = $query->count();
            Log::info("Query count for type '{$this->type}': ".$totalRecords);

            $chunkCount = 0;
            $expectedChunks = $totalRecords > 0 ? (int) ceil($totalRecords / max(1, (int) $this->chunk)) : 0;
            $query->chunkById($this->chunk, function ($records) use (&$chunkCount, $expectedChunks, $totalRecords) {
                $ids = $records->pluck('id')->toArray();
                dispatch(new SaleProcessJob(
                    $ids,
                    $this->saleProcessBatchNotificationKey,
                    $this->notificationInitiatedAt,
                    $chunkCount + 1,
                    $expectedChunks,
                    $totalRecords,
                    (int) $this->chunk,
                    $this->type
                ))->onQueue($this->name);
                $chunkCount++;

                // Emit chunk dispatch progress (best-effort)
                try {
                    $progress = $expectedChunks > 0
                        ? (int) floor(($chunkCount / $expectedChunks) * 99)
                        : 0;
                    $progress = max(1, min(99, $progress));

                    app(JobNotificationService::class)->notify(
                        null,
                        'sales_master_lambda',
                        'SaleMasterJobAwsLambda',
                        'processing',
                        $progress,
                        "Queued chunk {$chunkCount}/" . ($expectedChunks > 0 ? $expectedChunks : '?') . " (batch size {$this->chunk}).",
                        $this->notificationUniqueKey,
                        $this->notificationInitiatedAt,
                        null,
                        [
                            'type' => $this->type,
                            'total_records' => $totalRecords,
                            'batch_size' => (int) $this->chunk,
                            'chunks_dispatched' => $chunkCount,
                            'chunks_total' => $expectedChunks > 0 ? $expectedChunks : null,
                        ]
                    );
                } catch (\Throwable) {
                    // best-effort only
                }
            });

            Log::info("Completed SaleMasterJobAwsLambda for {$this->type}");

            app(JobNotificationService::class)->notify(
                null,
                'sales_master_lambda',
                'SaleMasterJobAwsLambda',
                'completed',
                100,
                "Sales master (lambda) job completed (type: {$this->type}). Chunks: {$chunkCount}.",
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String(),
                [
                    'type' => $this->type,
                    'chunks' => $chunkCount,
                ]
            );
        } catch (\Throwable $e) {
            // Enhanced error logging with more context
            Log::error('SaleMasterJobAwsLambda failed with exception', [
                'type' => $this->type,
                'chunk' => $this->chunk,
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
                Log::error('SaleMasterJobAwsLambda database connection error detected', [
                    'error' => $errorMsg,
                    'type' => $this->type,
                    'attempt' => $this->attempts(),
                ]);

                // Attempt to reconnect before giving up
                try {
                    DB::disconnect('mysql');
                    DB::reconnect('mysql');
                    Log::info('SaleMasterJobAwsLambda attempted emergency database reconnection');
                } catch (\Exception $reconnectError) {
                    Log::error('Failed emergency database reconnection', [
                        'error' => $reconnectError->getMessage(),
                    ]);
                }
            }

            // Re-throw to trigger the failed method
            throw $e;
        }
    }

    public function failed(\Throwable $e)
    {
        // Log when job is definitively considered failed by Laravel
        Log::error('SaleMasterJobAwsLambda marked as FAILED by Laravel queue system', [
            'type' => $this->type,
            'final_attempt' => $this->attempts(),
            'error_message' => $e->getMessage(),
            'memory_usage_final' => memory_get_usage(true) / 1024 / 1024 .'MB',
        ]);

        // Persist failure notification (best-effort)
        try {
            app(JobNotificationService::class)->notify(
                null,
                'sales_master_lambda',
                'SaleMasterJobAwsLambda',
                'failed',
                0,
                "Sales master (lambda) job failed (type: {$this->type}): {$e->getMessage()}",
                $this->notificationUniqueKey ?? ('sale_master_lambda_' . ($this->type ?? 'unknown') . '_' . time()),
                $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                now()->toIso8601String(),
                [
                    'type' => $this->type,
                ]
            );
        } catch (\Throwable $ignore) {
            // never fail the failed() handler
        }
    }
}
