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

class SaleMasterJob implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    public $type;

    public $name;

    public $chunk;

    public $timeout = 14400; // Increase timeout to 4 hours

    public $tries = 5; // Allow more retries if the job fails

    public $backoff = [60, 120, 300, 600]; // Wait 1m, 2m, 5m, 10m between retries

    // Define exempt domains as class constant to avoid duplication
    private const EXEMPT_DOMAINS = ['frdmturf', 'primepestandlawn', 'solvepestorlando', 'solvepestraleigh', 'insightpestfl', 'insightpestmt', 'insightpestal'];

    public string $notificationUniqueKey;
    public string $notificationInitiatedAt;

    public function __construct(string $type = 'excel', int $chunk = 100, string $name = 'sales-process')
    {
        $this->type = $type;
        $this->name = $name;
        $this->chunk = $chunk;
        $this->notificationInitiatedAt = now()->toIso8601String();
        $this->notificationUniqueKey = 'sale_master_' . $type . '_' . time();
        $this->onQueue('sales-process');
    }

    public function handle(): void
    {
        try {
            app(JobNotificationService::class)->notify(
                null,
                'sales_master',
                'SaleMasterJob',
                'started',
                0,
                "Sales master job started (type: {$this->type}).",
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt
            );

            // Log detailed information at the start of job handling
            Log::info('SaleMasterJob starting execution', [
                'type' => $this->type,
                'attempt' => $this->attempts(),
                'queue' => $this->queue ?? 'unknown',
                'memory_usage_start' => memory_get_usage(true) / 1024 / 1024 .'MB',
            ]);

            // Increase memory limit for this job to 8GB for larger datasets
            ini_set('memory_limit', '8192M');

            // Explicitly reconnect to the database to prevent connection timeout issues
            DB::disconnect('mysql');
            DB::reconnect('mysql');
            Log::info('SaleMasterJob: Reconnected to database');

            $domainName = config('app.domain_name');

            // Validate critical inputs
            if (empty($domainName)) {
                throw new \InvalidArgumentException('DOMAIN_NAME environment variable is not set');
            }

            if (empty($this->type)) {
                throw new \InvalidArgumentException('Job type cannot be empty');
            }

            // Process records with validation handling all filtering and error marking
            // SOLUTION: Removed redundant query-level filters to prevent the issue where
            // records were filtered out at query level but also marked as failed in validation
            $query = LegacyApiRawDataHistory::select([
                'id', 'pid', 'customer_signoff', 'trigger_date', 'closer1_id',
                'initial_service_date', 'initialStatusText', 'product',
            ])->where(['data_source_type' => $this->type, 'import_to_sales' => '0']);

            // IMPORTANT: Prevent double-processing of CSV imports.
            // CSV uploads create `legacy_api_raw_data_histories` rows with `excel_import_id` set,
            // and those are processed by `ExcelSalesProcessJob` / `SalesExcelProcessController`.
            // If SaleMasterJob also runs for type=excel, it can pick up the same rows and dispatch
            // a second SaleProcessJob, resulting in duplicate "Sales processing" notifications.
            if ($this->type === 'excel') {
                $query->whereNull('excel_import_id');
            }

            // Apply closer1_id filter based on domain and type requirements
            if ($this->requiresCloser($domainName)) {
                $query->whereNotNull('closer1_id');
            }

            // All domain-specific filtering is now handled by validation in the main loop
            // This ensures failed records are marked as import_to_sales = '2' instead of just skipped

            $chunkCount = 0;
            $validRecordCount = 0;
            $errorRecordCount = 0;

            // Count total records up-front for better progress reporting
            $totalRecords = (int) $query->count();
            app(JobNotificationService::class)->notify(
                null,
                'sales_master',
                'SaleMasterJob',
                'processing',
                10,
                "Found {$totalRecords} raw records to evaluate.",
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                null,
                [
                    'total_records' => $totalRecords,
                    'type' => $this->type,
                ]
            );

            $query->chunkById($this->chunk, function ($records) use (&$chunkCount, &$validRecordCount, &$errorRecordCount, $domainName) {
                $validIds = [];

                foreach ($records as $record) {
                    try {
                        // Validate record before processing (pass domainName to avoid env() calls)
                        $validationResult = $this->validateRecord($record, $domainName);

                        if ($validationResult['valid']) {
                            $validIds[] = $record->id;
                            $validRecordCount++;
                        } else {
                            // Mark record as failed with specific error details
                            $this->markRecordAsFailed($record, $validationResult['reason'], $validationResult['description']);
                            $errorRecordCount++;

                            Log::warning('SaleMasterJob: Record validation failed', [
                                'record_id' => $record->id,
                                'pid' => $record->pid,
                                'reason' => $validationResult['reason'],
                                'description' => $validationResult['description'],
                            ]);

                            continue; // Skip this record and continue with next
                        }
                    } catch (\Throwable $e) {
                        // Handle unexpected validation errors
                        $this->markRecordAsFailed($record, 'Validation Process Error', $e->getMessage());
                        $errorRecordCount++;

                        Log::error('SaleMasterJob: Validation exception for record', [
                            'record_id' => $record->id,
                            'pid' => $record->pid,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);

                        continue; // Skip this record and continue with next
                    }
                }

                // Only dispatch valid records for processing
                if (! empty($validIds)) {
                    dispatch(new SaleProcessJob($validIds))->onQueue($this->name);
                }

                $chunkCount++;

                Log::info("SaleMasterJob: Processed chunk {$chunkCount}", [
                    'total_records' => count($records),
                    'valid_records' => count($validIds),
                    'error_records' => count($records) - count($validIds),
                ]);
            });

            Log::info("Completed SaleMasterJob for {$this->type}", [
                'total_chunks' => $chunkCount,
                'valid_records' => $validRecordCount,
                'error_records' => $errorRecordCount,
            ]);

            app(JobNotificationService::class)->notify(
                null,
                'sales_master',
                'SaleMasterJob',
                'completed',
                100,
                "Sales master job completed (type: {$this->type}). Valid: {$validRecordCount}, Failed: {$errorRecordCount}.",
                $this->notificationUniqueKey,
                $this->notificationInitiatedAt,
                now()->toIso8601String(),
                [
                    'total_chunks' => $chunkCount,
                    'valid_records' => $validRecordCount,
                    'error_records' => $errorRecordCount,
                    'type' => $this->type,
                ]
            );
        } catch (\Throwable $e) {
            // Enhanced error logging with more context
            Log::error('SaleMasterJob failed with exception', [
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

            // Persist failure notification (best-effort)
            try {
                app(JobNotificationService::class)->notify(
                    null,
                    'sales_master',
                    'SaleMasterJob',
                    'failed',
                    0,
                    "Sales master job failed (type: {$this->type}): {$e->getMessage()}",
                    $this->notificationUniqueKey ?? ('sale_master_' . ($this->type ?? 'unknown') . '_' . time()),
                    $this->notificationInitiatedAt ?? now()->subSeconds(1)->toIso8601String(),
                    now()->toIso8601String(),
                    [
                        'type' => $this->type,
                    ]
                );
            } catch (\Throwable $ignore) {
                // never throw from job error path
            }

            // Check if this is a database connection issue
            $errorMsg = $e->getMessage();

            if (
                str_contains($errorMsg, 'SQLSTATE[HY000]') ||
                str_contains($errorMsg, 'Error while reading greeting packet') ||
                str_contains($errorMsg, 'Lost connection') ||
                str_contains($errorMsg, 'gone away')
            ) {
                Log::error('SaleMasterJob database connection error detected', [
                    'error' => $errorMsg,
                    'type' => $this->type,
                    'attempt' => $this->attempts(),
                ]);

                // Attempt to reconnect before giving up
                try {
                    DB::disconnect('mysql');
                    DB::reconnect('mysql');
                    Log::info('SaleMasterJob attempted emergency database reconnection');
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

    /**
     * Determine if closer1_id is required based on domain and type
     */
    private function requiresCloser(string $domainName): bool
    {
        // Non-exempt domains require closer
        if (! in_array($domainName, self::EXEMPT_DOMAINS, true)) {
            return true;
        }

        // Pocomos type always requires closer
        if ($this->type == 'Pocomos') {
            return true;
        }

        // FieldRoutes data types always require closer regardless of domain
        // Exception: EXEMPT_DOMAINS domains don't require closer
        if ($this->type === 'WhiteKnight' || strpos($this->type, 'FR_') === 0) {
            if (! in_array($domainName, self::EXEMPT_DOMAINS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate a record before processing
     */
    private function validateRecord(LegacyApiRawDataHistory $record, string $domainName): array
    {
        // Check for missing required fields
        if (empty($record->pid)) {
            return [
                'valid' => false,
                'reason' => 'Missing Required Field',
                'description' => 'PID is required but not provided',
            ];
        }

        // Improved validation for customer_signoff field
        if (empty($record->customer_signoff) ||
            $record->customer_signoff === '0000-00-00' ||
            $record->customer_signoff === '0000-00-00 00:00:00' ||
            trim($record->customer_signoff) === '') {
            return [
                'valid' => false,
                'reason' => 'Missing Required Field',
                'description' => 'Customer signoff date is required but not provided or contains invalid date value',
            ];
        }

        // Additional validation to ensure the date is parseable and reasonable
        try {
            $signoffDate = \Carbon\Carbon::parse($record->customer_signoff);
            // Check if the year is reasonable (not negative or too far in the future)
            if ($signoffDate->year < 1000 || $signoffDate->year > 2050) {
                return [
                    'valid' => false,
                    'reason' => 'Invalid Date',
                    'description' => 'Customer signoff date contains an unreasonable year: '.$signoffDate->year,
                ];
            }
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'reason' => 'Invalid Date Format',
                'description' => 'Customer signoff date could not be parsed: '.$record->customer_signoff,
            ];
        }

        // Validate milestone dates if present
        if ($record->trigger_date) {
            $milestoneDates = json_decode($record->trigger_date, true);

            // Handle JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'valid' => false,
                    'reason' => 'Invalid Data Format',
                    'description' => 'Trigger date contains invalid JSON format',
                ];
            }

            if (is_array($milestoneDates) && ! empty($milestoneDates)) {
                foreach ($milestoneDates as $milestoneDate) {
                    if (isset($milestoneDate['date']) && $milestoneDate['date'] < $record->customer_signoff) {
                        return [
                            'valid' => false,
                            'reason' => 'Invalid Milestone Date',
                            'description' => 'The milestone date cannot be earlier than the sale date',
                        ];
                    }
                }
            }
        }

        // NOTE: closer1_id validation is removed since it's already filtered at query level
        // Records without closer1_id (when required) won't reach this validation

        // Domain-specific validations (using strict comparisons)

        if ($domainName == 'evomarketing') {
            if (empty($record->initial_service_date)) {
                return [
                    'valid' => false,
                    'reason' => 'Missing Service Date',
                    'description' => 'Initial service date is required for evomarketing domain',
                ];
            }
        }

        if ($domainName == 'whiteknight') {
            if ($record->product == 'Termite Inspection') {
                return [
                    'valid' => false,
                    'reason' => 'Invalid Product',
                    'description' => 'Termite Inspection products are not eligible for processing',
                ];
            }
            if (empty($record->initial_service_date)) {
                return [
                    'valid' => false,
                    'reason' => 'Missing Service Date',
                    'description' => 'Initial service date is required for whiteknight domain',
                ];
            }
            if ($record->customer_signoff < '2025-03-01') {
                return [
                    'valid' => false,
                    'reason' => 'Date Restriction',
                    'description' => 'Sales before March 1st, 2025 are not eligible for import in WhiteKnight domain',
                ];
            }
        }

        if ($domainName == 'threeriverspest') {
            if (empty($record->initial_service_date)) {
                return [
                    'valid' => false,
                    'reason' => 'Missing Service Date',
                    'description' => 'Initial service date is required for threeriverspest domain',
                ];
            }
        }

        if ($domainName == 'momentum') {
            if ($record->customer_signoff < '2025-10-01') {
                return [
                    'valid' => false,
                    'reason' => 'Date Restriction',
                    'description' => 'Sales before October 1st, 2025 are not eligible for import',
                ];
            }
        }

        // Type-specific validations (using strict comparison)
        if ($this->type == 'Pocomos') {
            // Note: Using <= for Pocomos (inclusive) vs < for other domains (exclusive)
            // This is intentional - Pocomos excludes sales on or before 2024-12-31
            if ($record->customer_signoff <= '2024-12-31') {
                return [
                    'valid' => false,
                    'reason' => 'Date Restriction',
                    'description' => 'Sales on or before December 31st, 2024 are not eligible for import',
                ];
            }
        }

        // Insight Pest FL, AL & MT domain validations
        if (in_array($domainName, ['insightpestfl', 'insightpestmt', 'insightpestal'], true)) {
            if ($record->customer_signoff < '2026-01-01') {
                return [
                    'valid' => false,
                    'reason' => 'Date Restriction',
                    'description' => 'Sales before January 1st, 2026 are not eligible for import in Insight Pest FL, AL & MT domain',
                ];
            }
        }

        return [
            'valid' => true,
            'reason' => null,
            'description' => null,
        ];
    }

    /**
     * Mark a record as failed with error details
     */
    private function markRecordAsFailed(LegacyApiRawDataHistory $record, string $reason, string $description): void
    {
        try {
            // Use database transaction for consistency
            DB::transaction(function () use ($record, $reason, $description) {
                // Only mark if not already marked as failed to prevent overwriting
                // Handle both null and '0' values as pending records
                if ($record->import_to_sales == '0' || $record->import_to_sales === null) {
                    $record->import_to_sales = '2';
                    $record->import_status_reason = $reason;
                    $record->import_status_description = $description;
                    $record->save();
                } else {
                    // Log if we're trying to mark an already failed record
                    Log::debug('SaleMasterJob: Attempted to mark already failed record', [
                        'record_id' => $record->id,
                        'pid' => $record->pid,
                        'current_status' => $record->import_to_sales,
                        'current_reason' => $record->import_status_reason,
                        'attempted_reason' => $reason,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('SaleMasterJob: Failed to mark record as failed', [
                'record_id' => $record->id,
                'pid' => $record->pid,
                'attempted_reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e)
    {
        // Log when job is definitively considered failed by Laravel
        Log::error('SaleMasterJob marked as FAILED by Laravel queue system', [
            'type' => $this->type,
            'final_attempt' => $this->attempts(),
            'error_message' => $e->getMessage(),
            'memory_usage_final' => memory_get_usage(true) / 1024 / 1024 .'MB',
        ]);
    }
}
