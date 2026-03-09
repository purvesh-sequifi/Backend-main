<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExcelImportHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralized service for managing Excel import counters with race condition protection.
 *
 * This service provides:
 * - Atomic counter updates using Redis locks
 * - Detailed logging of all counter changes
 * - Detection and alerting of anomalies
 * - Prevention of race conditions in multi-worker environments
 */
class ExcelImportCounterService
{
    /**
     * Lock timeout in seconds
     */
    private const LOCK_TIMEOUT = 5;

    /**
     * Maximum number of retry attempts for lock acquisition
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Base delay in milliseconds for exponential backoff
     */
    private const BASE_DELAY_MS = 100;

    /**
     * Counter types
     */
    private const COUNTER_UPDATED = 'updated_records';
    private const COUNTER_NEW = 'new_records';
    private const COUNTER_ERROR = 'error_records';

    /**
     * Increment the updated records counter
     *
     * @param int $excelId Excel import history ID
     * @param array $context Additional context for logging (e.g., pid, operation)
     * @return bool Success status
     */
    public function incrementUpdated(int $excelId, array $context = []): bool
    {
        return $this->incrementCounter($excelId, self::COUNTER_UPDATED, 1, $context);
    }

    /**
     * Increment the new records counter
     *
     * @param int $excelId Excel import history ID
     * @param array $context Additional context for logging
     * @return bool Success status
     */
    public function incrementNew(int $excelId, array $context = []): bool
    {
        return $this->incrementCounter($excelId, self::COUNTER_NEW, 1, $context);
    }

    /**
     * Increment the error records counter
     *
     * @param int $excelId Excel import history ID
     * @param array $context Additional context for logging
     * @return bool Success status
     */
    public function incrementError(int $excelId, array $context = []): bool
    {
        return $this->incrementCounter($excelId, self::COUNTER_ERROR, 1, $context);
    }

    /**
     * Bulk increment error records with multiple PIDs
     * Used for catastrophic failures where multiple records fail at once
     *
     * @param int $excelId Excel import history ID
     * @param array $failedPids Array of PIDs that failed
     * @param array $context Additional context for logging
     * @return bool Success status
     */
    public function bulkIncrementError(int $excelId, array $failedPids, array $context = []): bool
    {
        if (empty($failedPids)) {
            Log::channel('excel_import_counters')->warning('bulkIncrementError called with empty PIDs array', [
                'excel_id' => $excelId,
                'context' => $context,
            ]);
            return true; // No PIDs to process, but not an error
        }

        $lockKey = $this->getLockKey($excelId);
        $attempt = 0;

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            $attempt++;

            $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

            if ($lock->get()) {
                try {
                    $import = ExcelImportHistory::find($excelId);
                    if (!$import) {
                        Log::channel('excel_import_counters')->error('Excel import not found for bulk error update', [
                            'excel_id' => $excelId,
                            'failed_pids_count' => count($failedPids),
                            'context' => $context,
                        ]);
                        return false;
                    }

                    $previousErrorCount = $import->error_records ?? 0;
                    $errorIncrement = count($failedPids);
                    $newErrorCount = $previousErrorCount + $errorIncrement;

                    // Build the update array
                    $updateData = [
                        'error_records' => DB::raw("error_records + {$errorIncrement}"),
                    ];

                    // Append all failed PIDs to error_pids JSON array
                    // We need to append multiple PIDs at once
                    $currentErrorPids = $import->error_pids ?? [];
                    $mergedErrorPids = array_merge($currentErrorPids, $failedPids);
                    $updateData['error_pids'] = json_encode($mergedErrorPids);

                    // Perform atomic update
                    ExcelImportHistory::where('id', $excelId)->update($updateData);

                    // Refresh and verify
                    $import->refresh();
                    $this->detectAnomalies($import, $context);

                    // Log the bulk update
                    Log::channel('excel_import_counters')->info('Bulk Error Counter Updated', [
                        'excel_id' => $excelId,
                        'counter_type' => self::COUNTER_ERROR,
                        'previous_value' => $previousErrorCount,
                        'new_value' => $import->error_records,
                        'increment_value' => $errorIncrement,
                        'failed_pids_count' => count($failedPids),
                        'failed_pids_sample' => array_slice($failedPids, 0, 10), // Log first 10 PIDs
                        'worker_id' => getmypid(),
                        'attempt' => $attempt,
                        'timestamp' => now()->toIso8601String(),
                        'context' => $context,
                    ]);

                    return true;
                } catch (\Throwable $e) {
                    Log::channel('excel_import_counters')->error('Bulk error counter increment failed', [
                        'excel_id' => $excelId,
                        'failed_pids_count' => count($failedPids),
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'context' => $context,
                    ]);
                    return false;
                } finally {
                    $lock->release();
                }
            }

            // Lock acquisition failed, retry with exponential backoff
            $delay = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
            Log::channel('excel_import_counters')->warning('Bulk error lock acquisition failed, retrying', [
                'excel_id' => $excelId,
                'failed_pids_count' => count($failedPids),
                'attempt' => $attempt,
                'max_attempts' => self::MAX_RETRY_ATTEMPTS,
                'next_delay_ms' => $delay,
                'lock_key' => $lockKey,
                'context' => $context,
            ]);

            usleep($delay * 1000);
        }

        // All retry attempts exhausted
        Log::channel('excel_import_counters')->error('Failed to acquire lock for bulk error update', [
            'excel_id' => $excelId,
            'failed_pids_count' => count($failedPids),
            'max_attempts' => self::MAX_RETRY_ATTEMPTS,
            'lock_key' => $lockKey,
            'context' => $context,
        ]);

        // Send alert to Sentry
        if (app()->bound('sentry')) {
            \Sentry\captureMessage('Excel import bulk error counter lock acquisition failed', [
                'level' => \Sentry\Severity::warning(),
                'extra' => [
                    'excel_id' => $excelId,
                    'failed_pids_count' => count($failedPids),
                    'attempts' => self::MAX_RETRY_ATTEMPTS,
                    'context' => $context,
                ],
            ]);
        }

        return false;
    }

    /**
     * Batch update multiple counters at once
     *
     * @param int $excelId Excel import history ID
     * @param array $counters Array of counter updates ['updated_records' => 5, 'new_records' => 3]
     * @param array $context Additional context for logging
     * @return bool Success status
     */
    public function updateCounters(int $excelId, array $counters, array $context = []): bool
    {
        $lockKey = $this->getLockKey($excelId);
        $attempt = 0;

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            $attempt++;

            $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

            if ($lock->get()) {
                try {
                    // Get current values
                    $import = ExcelImportHistory::find($excelId);
                    if (!$import) {
                        Log::channel('excel_import_counters')->error('Excel import not found', [
                            'excel_id' => $excelId,
                            'context' => $context,
                        ]);
                        return false;
                    }

                    $previousValues = [];
                    $newValues = [];
                    $updates = [];

                    foreach ($counters as $counterType => $incrementValue) {
                        $previousValues[$counterType] = $import->$counterType ?? 0;
                        $newValues[$counterType] = $previousValues[$counterType] + $incrementValue;
                        $updates[$counterType] = DB::raw("{$counterType} + {$incrementValue}");
                    }

                    // Perform atomic update
                    ExcelImportHistory::where('id', $excelId)->update($updates);

                    // Verify and detect anomalies
                    $import->refresh();
                    $this->detectAnomalies($import, $context);

                    // Log the update
                    $this->logCounterUpdate($excelId, 'batch', $previousValues, $newValues, $attempt, $context);

                    return true;
                } catch (\Throwable $e) {
                    Log::channel('excel_import_counters')->error('Batch counter update failed', [
                        'excel_id' => $excelId,
                        'counters' => $counters,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'context' => $context,
                    ]);
                    return false;
                } finally {
                    $lock->release();
                }
            }

            // Lock acquisition failed, log and retry with exponential backoff
            $delay = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
            Log::channel('excel_import_counters')->warning('Lock acquisition failed, retrying', [
                'excel_id' => $excelId,
                'attempt' => $attempt,
                'max_attempts' => self::MAX_RETRY_ATTEMPTS,
                'next_delay_ms' => $delay,
                'lock_key' => $lockKey,
                'context' => $context,
            ]);

            usleep($delay * 1000); // Convert ms to microseconds
        }

        // All retry attempts exhausted
        Log::channel('excel_import_counters')->error('Failed to acquire lock after max attempts', [
            'excel_id' => $excelId,
            'max_attempts' => self::MAX_RETRY_ATTEMPTS,
            'lock_key' => $lockKey,
            'context' => $context,
        ]);

        // Send alert to Sentry
        if (app()->bound('sentry')) {
            \Sentry\captureMessage('Excel import counter lock acquisition failed', [
                'level' => \Sentry\Severity::warning(),
                'extra' => [
                    'excel_id' => $excelId,
                    'attempts' => self::MAX_RETRY_ATTEMPTS,
                    'context' => $context,
                ],
            ]);
        }

        return false;
    }

    /**
     * Increment a specific counter atomically with Redis lock
     *
     * @param int $excelId Excel import history ID
     * @param string $counterType Type of counter (updated_records, new_records, error_records)
     * @param int $incrementValue Value to increment by (default 1)
     * @param array $context Additional context for logging
     * @return bool Success status
     */
    private function incrementCounter(int $excelId, string $counterType, int $incrementValue = 1, array $context = []): bool
    {
        $lockKey = $this->getLockKey($excelId);
        $attempt = 0;

        while ($attempt < self::MAX_RETRY_ATTEMPTS) {
            $attempt++;

            // Try to acquire lock
            $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

            if ($lock->get()) {
                try {
                    // Get current value
                    $import = ExcelImportHistory::find($excelId);
                    if (!$import) {
                        Log::channel('excel_import_counters')->error('Excel import not found', [
                            'excel_id' => $excelId,
                            'counter_type' => $counterType,
                            'context' => $context,
                        ]);
                        return false;
                    }

                    $previousValue = $import->$counterType ?? 0;

                    // Build update array
                    $updateData = [$counterType => DB::raw("{$counterType} + {$incrementValue}")];

                    // Add PID to appropriate JSON array if provided
                    if (isset($context['pid']) && !empty($context['pid'])) {
                        $pidsField = $this->getPidsFieldName($counterType);
                        if ($pidsField) {
                            $quotedPid = DB::getPdo()->quote($context['pid']);
                            $updateData[$pidsField] = DB::raw("JSON_ARRAY_APPEND(IFNULL({$pidsField}, JSON_ARRAY()), '$', {$quotedPid})");
                        }
                    }

                    // Perform atomic increment with PIDs update
                    ExcelImportHistory::where('id', $excelId)->update($updateData);

                    // Get new value for verification
                    $import->refresh();
                    $newValue = $import->$counterType;

                    // Detect anomalies
                    $this->detectAnomalies($import, $context);

                    // Log the successful update
                    $this->logCounterUpdate($excelId, $counterType, $previousValue, $newValue, $attempt, $context);

                    return true;
                } catch (\Throwable $e) {
                    Log::channel('excel_import_counters')->error('Counter increment failed', [
                        'excel_id' => $excelId,
                        'counter_type' => $counterType,
                        'increment_value' => $incrementValue,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'context' => $context,
                    ]);
                    return false;
                } finally {
                    // Always release the lock
                    $lock->release();
                }
            }

            // Lock acquisition failed, retry with exponential backoff
            $delay = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
            Log::channel('excel_import_counters')->warning('Lock acquisition failed, retrying', [
                'excel_id' => $excelId,
                'counter_type' => $counterType,
                'attempt' => $attempt,
                'max_attempts' => self::MAX_RETRY_ATTEMPTS,
                'next_delay_ms' => $delay,
                'lock_key' => $lockKey,
                'context' => $context,
            ]);

            usleep($delay * 1000); // Convert ms to microseconds
        }

        // All retry attempts exhausted
        Log::channel('excel_import_counters')->error('Failed to acquire lock after max attempts', [
            'excel_id' => $excelId,
            'counter_type' => $counterType,
            'max_attempts' => self::MAX_RETRY_ATTEMPTS,
            'lock_key' => $lockKey,
            'context' => $context,
        ]);

        // Send alert to Sentry
        if (app()->bound('sentry')) {
            \Sentry\captureMessage('Excel import counter lock acquisition failed', [
                'level' => \Sentry\Severity::warning(),
                'extra' => [
                    'excel_id' => $excelId,
                    'counter_type' => $counterType,
                    'attempts' => self::MAX_RETRY_ATTEMPTS,
                    'context' => $context,
                ],
            ]);
        }

        return false;
    }

    /**
     * Generate lock key for Redis
     *
     * @param int $excelId Excel import history ID
     * @return string Lock key
     */
    private function getLockKey(int $excelId): string
    {
        return "excel_import_counter_{$excelId}";
    }

    /**
     * Log counter update with detailed context
     *
     * @param int $excelId Excel import history ID
     * @param string $counterType Type of counter
     * @param mixed $previousValue Previous value(s)
     * @param mixed $newValue New value(s)
     * @param int $attempt Attempt number
     * @param array $context Additional context
     * @return void
     */
    private function logCounterUpdate(int $excelId, string $counterType, $previousValue, $newValue, int $attempt, array $context): void
    {
        // Get caller information using debug_backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $caller = $this->extractCaller($backtrace);

        Log::channel('excel_import_counters')->info('Counter Updated', [
            'excel_id' => $excelId,
            'counter_type' => $counterType,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'increment_value' => is_array($newValue) ? null : ($newValue - $previousValue),
            'caller_class' => $caller['class'] ?? 'Unknown',
            'caller_method' => $caller['method'] ?? 'Unknown',
            'caller_file' => $caller['file'] ?? 'Unknown',
            'caller_line' => $caller['line'] ?? 0,
            'worker_id' => getmypid(),
            'attempt' => $attempt,
            'timestamp' => now()->toIso8601String(),
            'tenant_id' => function_exists('tenant') && tenant() ? tenant('id') : null,
            'context' => $context,
        ]);
    }

    /**
     * Extract caller information from backtrace
     *
     * @param array $backtrace Debug backtrace
     * @return array Caller information
     */
    private function extractCaller(array $backtrace): array
    {
        // Skip internal service methods and find the actual caller
        foreach ($backtrace as $trace) {
            $class = $trace['class'] ?? '';
            if ($class !== self::class && $class !== '') {
                return [
                    'class' => $class,
                    'method' => $trace['function'] ?? 'Unknown',
                    'file' => $trace['file'] ?? 'Unknown',
                    'line' => $trace['line'] ?? 0,
                ];
            }
        }

        return [
            'class' => 'Unknown',
            'method' => 'Unknown',
            'file' => 'Unknown',
            'line' => 0,
        ];
    }

    /**
     * Detect and alert on anomalies in counter values
     *
     * @param ExcelImportHistory $import Import record
     * @param array $context Additional context
     * @return void
     */
    private function detectAnomalies(ExcelImportHistory $import, array $context): void
    {
        $totalRecords = $import->total_records ?? 0;
        $updatedRecords = $import->updated_records ?? 0;
        $newRecords = $import->new_records ?? 0;
        $errorRecords = $import->error_records ?? 0;

        $processedTotal = $updatedRecords + $newRecords + $errorRecords;

        // Anomaly: Processed records exceed total records
        if ($processedTotal > $totalRecords) {
            $anomalyData = [
                'anomaly_type' => 'counter_exceeds_total',
                'excel_id' => $import->id,
                'total_records' => $totalRecords,
                'updated_records' => $updatedRecords,
                'new_records' => $newRecords,
                'error_records' => $errorRecords,
                'processed_total' => $processedTotal,
                'overflow' => $processedTotal - $totalRecords,
                'context' => $context,
                'timestamp' => now()->toIso8601String(),
            ];

            Log::channel('excel_import_counters')->error('ANOMALY DETECTED: Processed records exceed total', $anomalyData);

            // Send alert to Sentry
            if (app()->bound('sentry')) {
                \Sentry\captureMessage('Excel import counter anomaly: overflow detected', [
                    'level' => \Sentry\Severity::error(),
                    'extra' => $anomalyData,
                ]);
            }
        }

        // Anomaly: Individual counter exceeds total
        foreach (['updated_records' => $updatedRecords, 'new_records' => $newRecords, 'error_records' => $errorRecords] as $counterName => $counterValue) {
            if ($counterValue > $totalRecords) {
                $anomalyData = [
                    'anomaly_type' => 'individual_counter_exceeds_total',
                    'excel_id' => $import->id,
                    'counter_name' => $counterName,
                    'counter_value' => $counterValue,
                    'total_records' => $totalRecords,
                    'overflow' => $counterValue - $totalRecords,
                    'context' => $context,
                    'timestamp' => now()->toIso8601String(),
                ];

                Log::channel('excel_import_counters')->error('ANOMALY DETECTED: Individual counter exceeds total', $anomalyData);

                // Send alert to Sentry
                if (app()->bound('sentry')) {
                    \Sentry\captureMessage("Excel import counter anomaly: {$counterName} exceeds total", [
                        'level' => \Sentry\Severity::error(),
                        'extra' => $anomalyData,
                    ]);
                }
            }
        }
    }

    /**
     * Get the PIDs field name for a given counter type
     *
     * @param string $counterType Counter type (updated_records, new_records, error_records)
     * @return string|null PIDs field name or null
     */
    private function getPidsFieldName(string $counterType): ?string
    {
        return match ($counterType) {
            self::COUNTER_UPDATED => 'updated_pids',
            self::COUNTER_NEW => 'new_pids',
            self::COUNTER_ERROR => 'error_pids',
            default => null,
        };
    }
}
