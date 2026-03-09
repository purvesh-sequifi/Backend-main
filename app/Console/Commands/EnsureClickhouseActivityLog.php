<?php

namespace App\Console\Commands;

use App\Services\ClickHouseConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureClickhouseActivityLog extends Command
{
    protected $signature = 'clickhouse:ensure-activity-log-table 
                            {--skip-migration : Skip data migration, only ensure table exists} 
                            {--batch-size=5000 : Number of records to process per batch}
                            {--resume : Resume from last checkpoint if migration was interrupted}
                            {--force-restart : Force restart migration from beginning}
                            {--max-retries=3 : Maximum retries per batch}';

    protected $description = 'Ensure ClickHouse database and activity_log table exist, migrate all data if empty, then keep only 1 day in MySQL.';

    private $checkpointKey = 'clickhouse_ensure_checkpoint';

    private $totalProcessed = 0;

    private $totalFailed = 0;

    public function handle(): int
    {
        try {
            Log::info('[ClickHouse Ensure] Starting ensure process for massive data migration.');

            // Pre-warm ClickHouse connection for better reliability
            if (! $this->preWarmClickHouse()) {
                return 1;
            }

            // Get database name from config first
            $database = config('clickhouse.connections.default.database');
            if (! $database) {
                $this->error('ClickHouse database not configured.');
                Log::error('[ClickHouse Ensure] ClickHouse database not configured.');

                return 1;
            }

            // Get ClickHouse client WITHOUT specifying a database initially
            // This allows us to connect to ClickHouse server and then create the database
            $client = ClickHouseConnectionService::getClientWithoutDatabase();
            if (! $client) {
                $this->error('Failed to establish ClickHouse connection.');
                Log::error('[ClickHouse Ensure] Failed to establish ClickHouse connection.');

                return 1;
            }

            // 1. Check if database exists and create it if needed
            $dbs = collect($client->select('SHOW DATABASES')->rows())->pluck('name')->toArray();
            if (! in_array($database, $dbs)) {
                $this->info("Database '$database' does not exist. Creating...");
                $client->write("CREATE DATABASE IF NOT EXISTS `$database`");
                $this->info("Database '$database' created.");
            } else {
                $this->info("Database '$database' already exists.");
            }

            // 2. Now switch to the target database
            $client->database($database);

            // Check if activity_log table exists
            $tables = collect($client->select('SHOW TABLES')->rows())->pluck('name')->toArray();
            if (! in_array('activity_log', $tables)) {
                $this->info("Table 'activity_log' does not exist. Creating...");
                $client->write(<<<'SQL'
                    CREATE TABLE activity_log (
                    id UInt64,
                    log_name Nullable(String),
                    description String,
                    subject_type Nullable(String),
                    event Nullable(String),
                    subject_id Nullable(UInt64),
                    causer_type Nullable(String),
                    causer_id Nullable(UInt64),
                    properties Nullable(String),
                    batch_uuid Nullable(String),
                    created_at DateTime,
                    updated_at Nullable(DateTime)
                ) ENGINE = MergeTree()
                ORDER BY (created_at, id)
                SQL
                );
                $this->info("Table 'activity_log' created with the correct schema.");
            } else {
                $this->info("Table 'activity_log' already exists.");
            }

            // Check if ClickHouse activity_log is empty
            $clickhouseCount = (int) $client->select('SELECT count() as cnt FROM activity_log')->rows()[0]['cnt'];

            // Step 1: Migration logic
            $migrationPerformed = false;
            $mysqlTotalCount = DB::table('activity_log')->count();

            if ($this->option('skip-migration')) {
                $this->info('Migration skipped due to --skip-migration flag.');
                $this->info('MySQL cleanup also skipped - no migration performed.');

                return 0; // Exit early, no cleanup needed
            } elseif ($clickhouseCount === 0) {
                // Fresh migration - ClickHouse is empty
                $this->info('ClickHouse activity_log is empty. Starting massive data migration from MySQL...');

                if (! $this->performMassiveMigration($client)) {
                    $this->error('Migration failed. Please check logs and retry with --resume flag.');

                    return 1;
                }
                $migrationPerformed = true;
            } elseif ($this->option('resume') || $this->option('force-restart')) {
                // Continue migration - ClickHouse has some data but migration requested
                $remaining = $mysqlTotalCount - $clickhouseCount;
                $this->info("ClickHouse activity_log contains $clickhouseCount records.");
                $this->info('Continuing migration for remaining '.number_format($remaining).' records...');

                if (! $this->performMassiveMigration($client)) {
                    $this->error('Migration failed. Please check logs and retry with --resume flag.');

                    return 1;
                }
                $migrationPerformed = true;
            } else {
                // No migration flags, check if cleanup is safe
                $this->info("ClickHouse activity_log already contains $clickhouseCount records. Skipping migration.");

                if ($clickhouseCount < ($mysqlTotalCount * 0.8)) { // Less than 80% of MySQL data
                    $this->warn("ClickHouse has insufficient data ($clickhouseCount) compared to MySQL ($mysqlTotalCount).");
                    $this->warn('MySQL cleanup skipped for safety. Run full migration first with --resume or --force-restart.');

                    return 0; // Exit early, cleanup not safe
                }
            }

            // Step 2: Cleanup (only run if migration was performed OR ClickHouse has sufficient data)
            if ($migrationPerformed || $clickhouseCount > 0) {
                $this->info('Performing MySQL cleanup - keeping only 1 day of records...');
            } else {
                $this->info('Skipping MySQL cleanup - no migration performed and ClickHouse insufficient.');

                return 0;
            }

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
                                Log::info("[ClickHouse Ensure] Data integrity verified. Safely deleted $deleted records from MySQL activity_log.", [
                                    'deleted_count' => $deleted,
                                    'max_id' => $maxIdToDelete,
                                    'cutoff_date' => $cutoffDate,
                                    'clickhouse_count' => $clickhouseCount,
                                    'mysql_count' => $mysqlCount,
                                ]);
                            } else {
                                // Data integrity check failed - DO NOT DELETE
                                $this->error("Data integrity check FAILED. ClickHouse count ($clickhouseCount) < MySQL count ($mysqlCount). Skipping deletion to prevent data loss.");
                                Log::error('[ClickHouse Ensure] Data integrity check FAILED. Skipping deletion to prevent data loss.', [
                                    'clickhouse_count' => $clickhouseCount,
                                    'mysql_count' => $mysqlCount,
                                    'max_id' => $maxIdToDelete,
                                    'cutoff_date' => $cutoffDate,
                                ]);
                            }
                        } else {
                            $this->info('No MySQL records eligible for safe deletion (older than 1 day).');
                            Log::info('[ClickHouse Ensure] No MySQL records eligible for safe deletion (older than 1 day).', [
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
                Log::warning('[ClickHouse Ensure] Cleanup transaction warning: '.$cleanupException->getMessage());
                // Don't re-throw - cleanup completed successfully even if there's a connection cleanup issue
            }

            // Ensure proper connection cleanup
            try {
                DB::disconnect();
            } catch (\Exception $disconnectException) {
                // Silently handle disconnect issues
                Log::debug('[ClickHouse Ensure] Connection disconnect: '.$disconnectException->getMessage());
            }

            $this->info('Command completed successfully.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Command failed: '.$e->getMessage());
            Log::error('[ClickHouse Ensure] Command failed: '.$e->getMessage(), ['exception' => $e]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }

            return 1;
        }
    }

    private function preWarmClickHouse(): bool
    {
        $this->info('Pre-warming ClickHouse connection for massive data migration...');

        if (! ClickHouseConnectionService::wakeUpDeepSleepingInstance(7, 180)) {
            $this->error('Failed to pre-warm ClickHouse connection. Please check your ClickHouse server.');
            Log::error('[ClickHouse Ensure] Failed to pre-warm ClickHouse connection');

            return false;
        }

        $this->info('ClickHouse connection pre-warmed successfully');

        return true;
    }

    private function performMassiveMigration($client): bool
    {
        try {
            // Handle checkpoint logic
            $checkpoint = $this->getCheckpoint();
            $startId = $checkpoint ? $checkpoint['last_id'] : 0;

            $total = DB::table('activity_log')->count();
            $this->info('Total records to migrate: '.number_format($total));

            if ($total > 1000000) {
                $this->warn('MASSIVE dataset detected ('.number_format($total).' records). This will take significant time...');
                $this->warn('You can safely interrupt and resume with --resume flag if needed.');
            }

            $batchSize = max(1000, min(10000, (int) $this->option('batch-size')));
            $maxRetries = (int) $this->option('max-retries');
            $checkpointInterval = 10; // Save checkpoint every 10 batches

            $this->info("Using batch size: $batchSize, Max retries: $maxRetries");

            return $this->processMigrationBatches($client, $startId, $batchSize, $maxRetries, $checkpointInterval, $total);

        } catch (\Exception $e) {
            Log::error('[ClickHouse Ensure] Migration failed: '.$e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    private function processMigrationBatches($client, int $startId, int $batchSize, int $maxRetries, int $checkpointInterval, int $total): bool
    {
        $currentId = $startId;
        $batchNumber = 0;
        $consecutiveFailures = 0;
        $maxConsecutiveFailures = 5;

        while (true) {
            $batchNumber++;

            // Get batch data using cursor-based pagination
            $batchData = $this->getMigrationBatchData($currentId, $batchSize);

            if (empty($batchData)) {
                $this->info('Migration completed - no more records to process');
                break;
            }

            // Process batch with retries
            $batchResult = $this->processMigrationBatchWithRetries($client, $batchData, $maxRetries, $batchNumber);

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
                    $this->error("Too many consecutive failures ($consecutiveFailures). Aborting migration.");
                    Log::error('[ClickHouse Ensure] Too many consecutive failures. Aborting migration.');

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
            usleep(200000); // 0.2 second for massive data
        }

        $this->info('Migration completed successfully!');
        $this->info('Total processed: '.number_format($this->totalProcessed));
        $this->info('Total failed: '.number_format($this->totalFailed));

        // Clear checkpoint on successful completion
        $this->clearCheckpoint();

        return true;
    }

    private function getMigrationBatchData(int $startId, int $batchSize): array
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
                'created_at' => $row->created_at ? date('Y-m-d H:i:s', strtotime($row->created_at)) : date('Y-m-d H:i:s'),
                'updated_at' => $row->updated_at ? date('Y-m-d H:i:s', strtotime($row->updated_at)) : null,
            ];
        }

        return $data;
    }

    private function processMigrationBatchWithRetries($client, array $batchData, int $maxRetries, int $batchNumber): array
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

                // Insert batch to ClickHouse
                $client->insert('activity_log', $batchData, [
                    'id', 'log_name', 'description', 'subject_type', 'event', 'subject_id',
                    'causer_type', 'causer_id', 'properties', 'batch_uuid', 'created_at', 'updated_at',
                ]);

                Log::info("[ClickHouse Ensure] Migration batch $batchNumber completed successfully on attempt $attempt");

                return [
                    'success' => true,
                    'processed' => count($batchData),
                    'attempts' => $attempt,
                ];

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning("[ClickHouse Ensure] Migration batch $batchNumber attempt $attempt failed: ".$e->getMessage());

                if ($attempt < $maxRetries) {
                    // Progressive backoff
                    $sleepTime = min(pow(2, $attempt), 60);
                    $this->warn("Retrying batch $batchNumber in {$sleepTime}s (attempt $attempt/$maxRetries)");
                    sleep($sleepTime);

                    // Try to re-establish connection
                    $client = ClickHouseConnectionService::getClient(180, true);
                }
            }
        }

        // All attempts failed
        Log::error("[ClickHouse Ensure] Migration batch $batchNumber failed after $maxRetries attempts", [
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
            Log::warning('[ClickHouse Ensure] Connection verification failed: '.$e->getMessage());

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
            // First, try to get checkpoint from cache
            $checkpoint = Cache::get($this->checkpointKey);
            if ($checkpoint) {
                $this->info("Found cached checkpoint: Last processed ID {$checkpoint['last_id']}, Batch {$checkpoint['batch_number']}");

                return $checkpoint;
            }

            // If cache is cleared, try to recover from ClickHouse itself
            $this->warn('Cache checkpoint not found. Attempting to recover from ClickHouse data...');

            return $this->recoverCheckpointFromClickHouse();
        }

        return null;
    }

    /**
     * Recover checkpoint by finding the highest ID already in ClickHouse
     * This prevents data duplication when cache is cleared
     */
    private function recoverCheckpointFromClickHouse(): ?array
    {
        try {
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                $this->error('Cannot recover checkpoint: ClickHouse connection failed');

                return null;
            }

            // Get the highest ID already migrated to ClickHouse
            $result = $client->select('SELECT max(toUInt64(id)) as max_id FROM activity_log WHERE id REGEXP \'^[0-9]+$\'')->rows();
            $lastMigratedId = isset($result[0]['max_id']) ? (int) $result[0]['max_id'] : 0;

            if ($lastMigratedId > 0) {
                // Count how many records are already in ClickHouse
                $clickhouseCount = (int) $client->select('SELECT count() as cnt FROM activity_log')->rows()[0]['cnt'];

                $recoveredCheckpoint = [
                    'last_id' => $lastMigratedId,
                    'batch_number' => 'recovered',
                    'timestamp' => now()->toIso8601String(),
                    'total_processed' => $clickhouseCount,
                    'total_failed' => 0,
                    'recovered_from_clickhouse' => true,
                ];

                $this->info('✅ Successfully recovered checkpoint from ClickHouse!');
                $this->info('   → Last migrated ID: '.number_format($lastMigratedId));
                $this->info('   → Records in ClickHouse: '.number_format($clickhouseCount));
                $this->info('   → Will resume from ID: '.number_format($lastMigratedId + 1));

                // Save the recovered checkpoint to cache for future use
                Cache::put($this->checkpointKey, $recoveredCheckpoint, now()->addDays(7));
                Log::info('[ClickHouse Ensure] Checkpoint recovered from ClickHouse data', $recoveredCheckpoint);

                return $recoveredCheckpoint;
            }

            $this->warn('No data found in ClickHouse to recover from. Will start from beginning.');

            return null;

        } catch (\Exception $e) {
            $this->error('Failed to recover checkpoint from ClickHouse: '.$e->getMessage());
            Log::error('[ClickHouse Ensure] Checkpoint recovery failed', ['error' => $e->getMessage()]);

            return null;
        }
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

        Cache::put($this->checkpointKey, $checkpoint, now()->addDays(7));

        Log::info("[ClickHouse Ensure] Checkpoint saved: Last ID $lastId, Batch $batchNumber");
    }

    private function clearCheckpoint(): void
    {
        Cache::forget($this->checkpointKey);
        Log::info('[ClickHouse Ensure] Checkpoint cleared');
    }
}
