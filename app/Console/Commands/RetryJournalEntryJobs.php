<?php

namespace App\Console\Commands;

use App\Jobs\CreateJournalEntryJob;
use App\Models\Crms;
use App\Models\PayrollHistory;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryJournalEntryJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retry:journal-entry-jobs 
                            {--limit=0 : Maximum number of records to process (0 for all)}
                            {--chunk=100 : Number of records to process in each chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs for payroll records missing journal_entry_id';

    /**
     * Maximum retry attempts for database connection.
     */
    private const MAX_RETRIES = 3;

    /**
     * Base delay between retries in seconds.
     */
    private const RETRY_DELAY_SECONDS = 2;

    /**
     * Cache TTL for QuickBooks enabled status (in seconds).
     */
    private const QUICKBOOKS_ENABLED_CACHE_TTL = 300;

    /**
     * Cache TTL when QuickBooks is disabled after max attempts (in seconds).
     */
    private const QUICKBOOKS_DISABLED_CACHE_TTL = 3600;

    /**
     * Maximum consecutive checks before backing off.
     */
    private const MAX_DISABLED_CHECKS = 3;

    /**
     * Cache key for QuickBooks enabled status.
     */
    private const QUICKBOOKS_CACHE_KEY = 'quickbooks_integration_enabled';

    /**
     * Cache key for tracking consecutive disabled checks.
     */
    private const QUICKBOOKS_DISABLED_COUNT_KEY = 'quickbooks_disabled_check_count';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $quickbooksEnabled = $this->isQuickBooksEnabled();

            if (! $quickbooksEnabled) {
                $this->warn('QuickBooks is disabled. Command skipped.');
                createLogFile('quickbooks', '['.now().'] Command skipped: QuickBooks is disabled.');

                return 0;
            }

            $limit = (int) $this->option('limit');
            $chunkSize = (int) $this->option('chunk');

            $totalCount = PayrollHistory::whereNull('quickbooks_journal_entry_id')
                ->whereIn('everee_payment_status', [0, 3])
                ->count();

            if ($totalCount === 0) {
                $this->info('All payroll entries have journal entries.');
                createLogFile('quickbooks', '['.now().'] No payroll_history records with null quickbooks_journal_entry_id found.');

                return 0;
            }

            $recordsToProcess = $limit > 0 ? min($limit, $totalCount) : $totalCount;

            $this->info("Found {$recordsToProcess} payroll records missing journal entries.");
            createLogFile('quickbooks', '['.now().'] Found '.$recordsToProcess.' payroll records missing journal entries.');

            $bar = $this->output->createProgressBar($recordsToProcess);
            $bar->start();

            $processedCount = 0;
            $successCount = 0;
            $failureCount = 0;

            PayrollHistory::whereNull('quickbooks_journal_entry_id')
                ->whereIn('everee_payment_status', [0, 3])
                ->when($limit > 0, function ($query) use ($limit) {
                    return $query->limit($limit);
                })
                ->chunkById($chunkSize, function ($records) use (&$processedCount, &$successCount, &$failureCount, $bar, $limit) {
                    foreach ($records as $record) {
                        if ($limit > 0 && $processedCount >= $limit) {
                            break;
                        }

                        try {
                            $fromDate = $record->pay_period_from;
                            $toDate = $record->pay_period_to;
                            $evereePaymentReqId = $record->everee_payment_requestId;

                            CreateJournalEntryJob::dispatch($fromDate, $toDate, $evereePaymentReqId);

                            createLogFile('quickbooks', '['.now().'] Dispatched job for payroll_history ID: '.
                                $record->id.', pay_period_from: '.$record->pay_period_from);

                            $successCount++;
                        } catch (Exception $e) {
                            createLogFile('quickbooks', '['.now().'] Error dispatching job for payroll_history ID: '.
                                $record->id.'. Error: '.$e->getMessage());

                            if (app()->bound('sentry')) {
                                app('sentry')->captureException($e);
                            }

                            $failureCount++;
                        }

                        $processedCount++;
                        $bar->advance();
                    }
                });

            $bar->finish();
            $this->newLine(2);

            $this->info("Summary: {$successCount} jobs dispatched successfully, {$failureCount} failed.");
            createLogFile('quickbooks', '['.now().'] Command completed. '.
                $successCount.' jobs dispatched successfully, '.$failureCount.' failed.');

            return 0;
        } catch (Exception $e) {
            $this->error('Command failed: '.$e->getMessage());
            createLogFile('quickbooks', '['.now().'] Command failed: '.$e->getMessage());

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            return 1;
        }
    }

    /**
     * Check if QuickBooks integration is enabled.
     * Uses smart caching with backoff.
     */
    private function isQuickBooksEnabled(): bool
    {
        // Use Cache::get() with null check to avoid TOCTOU race condition
        // (Cache::has() + Cache::get() could return null if cache expires between calls)
        $cachedValue = Cache::get(self::QUICKBOOKS_CACHE_KEY);
        if ($cachedValue !== null) {
            return $cachedValue;
        }

        $isEnabled = $this->executeWithRetry(function () {
            return Crms::where('name', 'QuickBooks')
                ->where('status', 1)
                ->exists();
        }, 'QuickBooks status check');

        if ($isEnabled) {
            Cache::forget(self::QUICKBOOKS_DISABLED_COUNT_KEY);
            Cache::put(self::QUICKBOOKS_CACHE_KEY, true, self::QUICKBOOKS_ENABLED_CACHE_TTL);

            return true;
        }

        $disabledCount = (int) Cache::get(self::QUICKBOOKS_DISABLED_COUNT_KEY, 0) + 1;

        if ($disabledCount >= self::MAX_DISABLED_CHECKS) {
            Cache::put(self::QUICKBOOKS_CACHE_KEY, false, self::QUICKBOOKS_DISABLED_CACHE_TTL);
            Cache::put(self::QUICKBOOKS_DISABLED_COUNT_KEY, self::MAX_DISABLED_CHECKS, self::QUICKBOOKS_DISABLED_CACHE_TTL);
            createLogFile('quickbooks', '['.now().'] QuickBooks disabled. Checked '.self::MAX_DISABLED_CHECKS.' times, backing off for 1 hour.');
        } else {
            Cache::put(self::QUICKBOOKS_CACHE_KEY, false, self::QUICKBOOKS_ENABLED_CACHE_TTL);
            Cache::put(self::QUICKBOOKS_DISABLED_COUNT_KEY, $disabledCount, self::QUICKBOOKS_DISABLED_CACHE_TTL);
            createLogFile('quickbooks', '['.now()."] QuickBooks check {$disabledCount}/".self::MAX_DISABLED_CHECKS.' - disabled, will retry.');
        }

        return false;
    }

    /**
     * Execute a database operation with retry logic for connection timeouts.
     */
    private function executeWithRetry(callable $callback, string $operationName = 'database operation'): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                if ($attempt > 1) {
                    DB::reconnect();
                    $delay = self::RETRY_DELAY_SECONDS * $attempt;
                    $this->warn("Retry attempt {$attempt}/".self::MAX_RETRIES." for {$operationName} after {$delay}s delay...");
                    sleep($delay);
                }

                return $callback();
            } catch (QueryException $e) {
                $lastException = $e;

                if ($this->isConnectionTimeoutError($e)) {
                    createLogFile('quickbooks', '['.now()."] Connection timeout on {$operationName}, attempt {$attempt}/".self::MAX_RETRIES);

                    if ($attempt === self::MAX_RETRIES) {
                        createLogFile('quickbooks', '['.now()."] All {$attempt} retry attempts exhausted for {$operationName}");
                        throw $e;
                    }

                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new Exception("Failed to execute {$operationName} after ".self::MAX_RETRIES.' attempts');
    }

    /**
     * Check if the exception is a connection timeout error.
     */
    private function isConnectionTimeoutError(QueryException $e): bool
    {
        $message = $e->getMessage();
        $previousMessage = $e->getPrevious() ? $e->getPrevious()->getMessage() : '';

        return str_contains($message, 'Connection timed out')
            || str_contains($message, '[2002]')
            || str_contains($previousMessage, 'Connection timed out')
            || str_contains($previousMessage, '[2002]');
    }
}
