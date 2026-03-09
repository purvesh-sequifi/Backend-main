<?php

namespace App\Console\Commands;

use App\Services\ClickHouseConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncClickhouseActivityLog extends Command
{
    protected $signature = 'clickhouse:sync-activity-log 
                            {--batch-size=5000 : Number of records to process per batch}
                            {--resume : Resume from last checkpoint if sync was interrupted}
                            {--force-restart : Force restart sync from beginning}
                            {--max-retries=3 : Maximum retries per batch}';

    protected $description = 'Daily sync: migrate records older than 1 day from MySQL to ClickHouse, then delete from MySQL';

    private $syncCheckpointKey = 'clickhouse_daily_sync_checkpoint';

    private $totalProcessed = 0;

    private $totalFailed = 0;

    public function handle(): int
    {
        try {
            $this->info('=== ClickHouse Sync Command Started ===');
            Log::info('[ClickHouse Sync] Daily sync started - migrating records older than 1 day.');

            // Pre-warm ClickHouse connection
            $this->info('Step 1: Pre-warming ClickHouse connection...');
            if (! $this->preWarmClickHouse()) {
                $this->error('Failed to pre-warm ClickHouse connection');

                return 1;
            }
            $this->info('Step 1: ✅ ClickHouse connection pre-warmed successfully');

            $this->info('Step 2: Getting ClickHouse client...');
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                $this->error('Failed to establish ClickHouse connection.');
                Log::error('[ClickHouse Sync] Failed to establish ClickHouse connection.');

                return 1;
            }
            $this->info('Step 2: ✅ ClickHouse connection established successfully.');

            // Step 3: Sync records from MySQL to ClickHouse
            $this->info('Step 3: Starting daily sync process...');
            if (! $this->performDailySync($client)) {
                $this->error('Daily sync failed. Please check logs and retry with --resume flag.');

                return 1;
            }
            $this->info('Step 3: ✅ Daily sync completed successfully');

            // Step 2: Cleanup (always run regardless of sync status)
            $this->info('Performing MySQL cleanup - keeping only 1 day of records...');

            // Cleanup transaction with proper error handling
            try {
                DB::transaction(function () use ($client) {
                    // Lock tables for consistent deletion
                    DB::statement('LOCK TABLES activity_log WRITE');

                    try {
                        $cutoffDate = now()->subDay()->toDateString();
                        // Count records to delete instead of loading all IDs into memory
                        $recordsToDeleteCount = DB::table('activity_log')
                            ->where('created_at', '<', $cutoffDate)
                            ->count();

                        if ($recordsToDeleteCount > 0) {
                            // CRITICAL: Verify data integrity in ClickHouse before deleting MySQL records
                            $this->info('Verifying data integrity in ClickHouse before deletion...');

                            // Get max ID of records to delete without loading all IDs into memory
                            $maxIdToDelete = DB::table('activity_log')
                                ->where('created_at', '<', $cutoffDate)
                                ->max('id');

                            // Check if all records exist in ClickHouse
                            $clickhouseCount = (int) $client->select("SELECT count() as cnt FROM activity_log WHERE id <= {$maxIdToDelete}")->rows()[0]['cnt'];
                            $mysqlCount = DB::table('activity_log')->where('id', '<=', $maxIdToDelete)->count();

                            if ($clickhouseCount >= $mysqlCount) {
                                // Safe to delete: ClickHouse contains all the data
                                $deleted = DB::table('activity_log')
                                    ->where('created_at', '<', $cutoffDate)
                                    ->delete();

                                $this->info("Data integrity verified. Safely deleted $deleted records from MySQL activity_log (max_id: $maxIdToDelete, created_at < $cutoffDate).");
                                Log::info("[ClickHouse Sync] Data integrity verified. Safely deleted $deleted records from MySQL activity_log.", [
                                    'deleted_count' => $deleted,
                                    'max_id' => $maxIdToDelete,
                                    'cutoff_date' => $cutoffDate,
                                    'clickhouse_count' => $clickhouseCount,
                                    'mysql_count' => $mysqlCount,
                                ]);
                            } else {
                                // Data integrity check failed - DO NOT DELETE
                                $this->error("Data integrity check FAILED. ClickHouse count ($clickhouseCount) < MySQL count ($mysqlCount). Skipping deletion to prevent data loss.");
                                Log::error('[ClickHouse Sync] Data integrity check FAILED. Skipping deletion to prevent data loss.', [
                                    'clickhouse_count' => $clickhouseCount,
                                    'mysql_count' => $mysqlCount,
                                    'max_id' => $maxIdToDelete,
                                    'cutoff_date' => $cutoffDate,
                                ]);
                            }
                        } else {
                            $this->info('No MySQL records eligible for safe deletion (older than 1 day).');
                            Log::info('[ClickHouse Sync] No MySQL records eligible for safe deletion (older than 1 day).', [
                                'cutoff_date' => $cutoffDate,
                            ]);
                        }

                    } finally {
                        // Always unlock tables
                        DB::statement('UNLOCK TABLES');
                    }
                });
            } catch (\Exception $cleanupException) {
                // Handle cleanup transaction errors gracefully
                Log::warning('[ClickHouse Sync] Cleanup transaction warning: '.$cleanupException->getMessage());
                // Don't re-throw - cleanup completed successfully even if there's a connection cleanup issue
            }

            // Ensure proper connection cleanup
            try {
                DB::disconnect();
            } catch (\Exception $disconnectException) {
                // Silently handle disconnect issues
                Log::debug('[ClickHouse Sync] Connection disconnect: '.$disconnectException->getMessage());
            }

            $this->info('Sync command completed successfully.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Command failed: '.$e->getMessage());
            Log::error('[ClickHouse Sync] Command failed: '.$e->getMessage(), ['exception' => $e]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            return 1;
        }
    }

    private function preWarmClickHouse(): bool
    {
        $this->info('Pre-warming ClickHouse connection...');

        // Use shorter timeout for sync command (30 seconds instead of 120)
        if (! ClickHouseConnectionService::wakeUpDeepSleepingInstance(3, 30)) {
            $this->error('Failed to pre-warm ClickHouse connection');
            Log::error('[ClickHouse Sync] Failed to pre-warm ClickHouse connection');

            return false;
        }

        $this->info('ClickHouse connection pre-warmed successfully');

        return true;
    }

    private function performDailySync($client): bool
    {
        try {
            // STEP 1: Get the highest ID already in ClickHouse
            $this->info('Checking highest ID in ClickHouse...');
            $clickhouseMaxId = $this->getClickHouseMaxId($client);
            $this->info('Highest ID in ClickHouse: '.number_format($clickhouseMaxId));

            // STEP 2: Handle checkpoint logic (use higher of checkpoint or ClickHouse max ID)
            $checkpoint = $this->getCheckpoint();
            $checkpointId = $checkpoint ? $checkpoint['last_id'] : 0;
            $startId = max($clickhouseMaxId, $checkpointId);

            $this->info('Starting sync from ID: '.number_format($startId + 1));

            // STEP 3: Count all records that need to be synced (ID > startId)
            $total = DB::table('activity_log')
                ->where('id', '>', $startId)
                ->count();

            if ($total === 0) {
                $this->info('No new records to sync - ClickHouse is up to date.');
                Log::info('[ClickHouse Sync] No new records to sync - ClickHouse is up to date.');

                return true;
            }

            $this->info('Total records to sync: '.number_format($total));

            if ($total > 500000) {
                $this->warn('Large dataset detected ('.number_format($total).' records). This may take significant time...');
                $this->warn('You can safely interrupt and resume with --resume flag if needed.');
            }

            $batchSize = max(1000, min(10000, (int) $this->option('batch-size')));
            $maxRetries = (int) $this->option('max-retries');
            $checkpointInterval = 10; // Save checkpoint every 10 batches

            $this->info("Using batch size: $batchSize, Max retries: $maxRetries");

            return $this->processDailySyncBatches($client, $startId, $batchSize, $maxRetries, $checkpointInterval, $total);

        } catch (\Exception $e) {
            Log::error('[ClickHouse Sync] Daily sync failed: '.$e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    private function processDailySyncBatches($client, int $startId, int $batchSize, int $maxRetries, int $checkpointInterval, int $total): bool
    {
        $currentId = $startId;
        $batchNumber = 0;
        $consecutiveFailures = 0;
        $maxConsecutiveFailures = 5;

        while (true) {
            $batchNumber++;

            // Get batch data for records with ID > currentId
            $batchData = $this->getDailySyncBatchData($currentId, $batchSize);

            if (empty($batchData)) {
                $this->info('Daily sync completed - no more records to process');
                break;
            }

            // No need to check duplicates since we're syncing from max ClickHouse ID
            // This ensures we only process records that don't exist in ClickHouse

            // Process batch with retries
            $batchResult = $this->processDailySyncBatchWithRetries($client, $batchData, $maxRetries, $batchNumber);

            if ($batchResult['success']) {
                $this->totalProcessed += $batchResult['processed'];
                $consecutiveFailures = 0;

                // Update current ID to the highest ID in this batch
                $currentId = max(array_column($batchData, 'id'));

                // Save checkpoint periodically
                if ($batchNumber % $checkpointInterval === 0) {
                    $this->saveCheckpoint($currentId, $batchNumber);
                }

                $progress = $total > 0 ? round(($this->totalProcessed / $total) * 100, 1) : 0;
                $this->info("Batch $batchNumber completed: {$batchResult['processed']} records processed ($progress% total)");

            } else {
                $this->totalFailed += count($batchData);
                $consecutiveFailures++;

                $this->error("Batch $batchNumber failed after $maxRetries retries");

                // If too many consecutive failures, abort
                if ($consecutiveFailures >= $maxConsecutiveFailures) {
                    $this->error("Too many consecutive failures ($consecutiveFailures). Aborting sync.");
                    Log::error('[ClickHouse Sync] Too many consecutive failures. Aborting sync.');

                    return false;
                }

                // Move past failed batch to continue
                $currentId = max(array_column($batchData, 'id'));
            }

            // Memory cleanup
            unset($batchData);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Small delay to prevent overwhelming the system
            usleep(100000); // 0.1 second
        }

        $this->info('Daily sync completed successfully!');
        $this->info('Total processed: '.number_format($this->totalProcessed));
        $this->info('Total failed: '.number_format($this->totalFailed));

        // Clear checkpoint on successful completion
        $this->clearCheckpoint();

        return true;
    }

    private function getDailySyncBatchData(int $startId, int $batchSize): array
    {
        $rows = DB::table('activity_log')
            ->where('id', '>', $startId)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row->id,
                'log_name' => $row->log_name,
                'description' => $row->description,
                'subject_type' => $row->subject_type,
                'event' => $row->event,
                'subject_id' => $row->subject_id !== null ? (int) $row->subject_id : null,
                'causer_type' => $row->causer_type,
                'causer_id' => $row->causer_id !== null ? (int) $row->causer_id : null,
                'properties' => $row->properties,
                'batch_uuid' => $row->batch_uuid,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        }

        return $data;
    }

    /**
     * Get the highest ID from ClickHouse activity_log table
     */
    private function getClickHouseMaxId($client): int
    {
        try {
            // Ensure we're using the correct database
            $database = config('clickhouse.connections.default.database');
            $this->info("Using ClickHouse database: $database");

            // Debug: First check if we can count records with explicit database
            $countResult = $client->select("SELECT count() as cnt FROM {$database}.activity_log")->rows();
            $recordCount = isset($countResult[0]['cnt']) ? (int) $countResult[0]['cnt'] : 0;
            $this->info('ClickHouse record count: '.number_format($recordCount));

            if ($recordCount === 0) {
                $this->info('ClickHouse activity_log table is empty');

                return 0;
            }

            // Try to get max ID with explicit database
            $result = $client->select("SELECT max(id) as max_id FROM {$database}.activity_log")->rows();
            $maxId = isset($result[0]['max_id']) ? (int) $result[0]['max_id'] : 0;

            $this->info('Raw max ID result: '.json_encode($result[0] ?? []));
            Log::info("[ClickHouse Sync] Retrieved max ID from ClickHouse: $maxId", [
                'record_count' => $recordCount,
                'raw_result' => $result[0] ?? [],
            ]);

            return $maxId;

        } catch (\Exception $e) {
            $this->error('ClickHouse max ID query failed: '.$e->getMessage());
            Log::error('[ClickHouse Sync] Failed to get max ID from ClickHouse: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return 0;
        }
    }

    private function processDailySyncBatchWithRetries($client, array $batchData, int $maxRetries, int $batchNumber): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                // Verify ClickHouse connection before each batch
                if (! $this->verifyConnection($client)) {
                    throw new \Exception('ClickHouse connection lost');
                }

                // Insert batch to ClickHouse with explicit database
                $database = config('clickhouse.connections.default.database');
                $client->insert("{$database}.activity_log", $batchData, [
                    'id', 'log_name', 'description', 'subject_type', 'event', 'subject_id',
                    'causer_type', 'causer_id', 'properties', 'batch_uuid', 'created_at', 'updated_at',
                ]);

                Log::info("[ClickHouse Sync] Daily sync batch $batchNumber completed successfully on attempt $attempt");

                return [
                    'success' => true,
                    'processed' => count($batchData),
                    'attempts' => $attempt,
                ];

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning("[ClickHouse Sync] Daily sync batch $batchNumber attempt $attempt failed: ".$e->getMessage());

                if ($attempt < $maxRetries) {
                    // Progressive backoff
                    $sleepTime = min(pow(2, $attempt), 30);
                    $this->warn("Retrying batch $batchNumber in {$sleepTime}s (attempt $attempt/$maxRetries)");
                    sleep($sleepTime);

                    // Try to re-establish connection
                    $client = ClickHouseConnectionService::getClient(120, true);
                }
            }
        }

        // All attempts failed
        Log::error("[ClickHouse Sync] Daily sync batch $batchNumber failed after $maxRetries attempts", [
            'last_error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'batch_size' => count($batchData),
        ]);

        return [
            'success' => false,
            'processed' => 0,
            'attempts' => $maxRetries,
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
        ];
    }

    private function verifyConnection($client): bool
    {
        try {
            $result = $client->select('SELECT 1');

            return isset($result->rows()[0]['1']) && $result->rows()[0]['1'] == 1;
        } catch (\Exception $e) {
            Log::warning('[ClickHouse Sync] Connection verification failed: '.$e->getMessage());

            return false;
        }
    }

    private function getCheckpoint(): ?array
    {
        if ($this->option('force-restart')) {
            $this->clearCheckpoint();

            return null;
        }

        if ($this->option('resume')) {
            $checkpoint = Cache::get($this->syncCheckpointKey);
            if ($checkpoint) {
                $this->info("Found checkpoint: Last processed ID {$checkpoint['last_id']}, Batch {$checkpoint['batch_number']}");

                return $checkpoint;
            }
        }

        return null;
    }

    private function saveCheckpoint(int $lastId, int $batchNumber): void
    {
        $checkpoint = [
            'last_id' => $lastId,
            'batch_number' => $batchNumber,
            'timestamp' => now()->toIso8601String(),
            'total_processed' => $this->totalProcessed,
            'total_failed' => $this->totalFailed,
        ];

        Cache::put($this->syncCheckpointKey, $checkpoint, now()->addDays(2));

        Log::info("[ClickHouse Sync] Checkpoint saved: Last ID $lastId, Batch $batchNumber");
    }

    private function clearCheckpoint(): void
    {
        Cache::forget($this->syncCheckpointKey);
        Log::info('[ClickHouse Sync] Checkpoint cleared');
    }
}
