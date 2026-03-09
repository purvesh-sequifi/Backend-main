<?php

namespace App\Console\Commands;

use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogLegacyApiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:log-legacy-api-data {--delete : Delete records from source table after logging} {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy data from legacy_api_raw_data_histories to log table where import_to_sales != 0 and optionally delete the source records';

    /**
     * Execute the console command.
     */
    /**
     * Delete records from the source table
     *
     * @param  array  $recordIds  IDs of records to delete
     * @return int Number of records deleted
     */
    protected function deleteRecords(array $recordIds): int
    {
        if (empty($recordIds)) {
            return 0;
        }

        $totalDeleted = 0;
        $chunkSize = 1000; // Delete in chunks to prevent memory issues

        // Split the record IDs into chunks for processing
        $chunks = array_chunk($recordIds, $chunkSize);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();
            try {
                $deleted = LegacyApiRawDataHistory::whereIn('id', $chunk)->delete();
                DB::commit();
                $totalDeleted += $deleted;
                $this->info("Deleted {$deleted} records from source table in this chunk");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Error deleting records: {$e->getMessage()}");
                Log::error("Failed to delete records: {$e->getMessage()}");
            }
        }

        return $totalDeleted;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Starting to log legacy API data...');

            // Check if we should delete records after logging
            $shouldDelete = $this->option('delete');
            $force = $this->option('force');

            if ($shouldDelete) {
                $this->info('Records will be deleted from source table after logging.');

                // Only ask for confirmation if not forced
                if (! $force && ! $this->confirm('Are you sure you want to delete records from the source table after logging?')) {
                    $this->info('Operation canceled.');

                    return 0;
                }
            }

            // Initialize counters
            $loggedCount = 0;
            $deletedCount = 0;
            $recordIds = []; // Store IDs of successfully logged records for deletion
            $chunkSize = 1000; // Process in chunks of 1000 records

            // Get count of records where import_to_sales != 0
            $recordsCount = LegacyApiRawDataHistory::whereNotNull('import_to_sales')
                ->where('import_to_sales', '!=', 0)
                ->count();

            $this->info('Found '.$recordsCount.' records to process.');

            // If no records found, exit early
            if ($recordsCount == 0) {
                $this->info('No records to process. Exiting.');

                return 0;
            }

            // Array to collect IDs of records that were successfully logged
            $recordIdsToDelete = [];

            // Process records in chunks to prevent memory issues
            LegacyApiRawDataHistory::whereNotNull('import_to_sales')
                ->where('import_to_sales', '!=', 0)
                ->orderBy('id')
                ->chunk($chunkSize, function ($records) use (&$loggedCount, &$recordIdsToDelete) {
                    // Begin transaction for this chunk
                    DB::beginTransaction();
                    $chunkSuccessful = true;

                    try {
                        foreach ($records as $record) {
                            try {
                                // Create a new log entry using the Eloquent model
                                $logEntry = new LegacyApiRawDataHistoryLog;

                                // Set log-specific fields
                                $logEntry->action_type = 'dump';
                                $logEntry->original_id = $record->id;
                                $logEntry->changed_by = Auth::id() ?? 1;
                                $logEntry->changed_at = now();

                                // Copy attributes from the original record to the log entry
                                foreach ($record->getAttributes() as $key => $value) {
                                    // Skip the ID field as we'll have our own ID
                                    if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                                        // Only set the attribute if it's fillable in the log model
                                        if (in_array($key, $logEntry->getFillable())) {
                                            $logEntry->{$key} = $value;
                                        }
                                    }
                                }

                                // Save the log entry
                                $logEntry->save();

                                // Add to list of successfully logged records for potential deletion
                                $recordIdsToDelete[] = $record->id;

                                $loggedCount++;

                                // Show progress periodically
                                if ($loggedCount % 100 == 0) {
                                    echo "Processed {$loggedCount} records so far...\n";
                                }
                            } catch (\Exception $e) {
                                $chunkSuccessful = false;
                                echo "Error processing record ID {$record->id}: {$e->getMessage()}\n";
                                Log::warning("Failed to process record ID {$record->id}: {$e->getMessage()}");
                                // Continue with next record in chunk
                            }
                        }

                        // Commit the transaction for log entries
                        if ($chunkSuccessful) {
                            DB::commit();
                            $this->info("Successfully processed chunk of {$records->count()} records");
                        } else {
                            // Some records in this chunk failed, but we processed what we could
                            DB::commit();
                            $this->warn('Processed chunk with some errors');
                        }
                    } catch (\Exception $e) {
                        // An error occurred that affected the entire chunk
                        DB::rollBack();
                        $this->error("Failed to process chunk: {$e->getMessage()}");
                        Log::error("Failed to process chunk: {$e->getMessage()}");
                    }
                });

            // Delete records if required, AFTER all logging is completed
            $deletedCount = 0;
            if ($shouldDelete && ! empty($recordIdsToDelete)) {
                $this->info('Now deleting records from source table...');
                $deletedCount = $this->deleteRecords($recordIdsToDelete);
            }

            $this->info("Successfully logged {$loggedCount} records.");
            if ($shouldDelete) {
                $this->info("Successfully deleted {$deletedCount} records from source table.");
            }

            Log::info("Legacy API data logging completed: {$loggedCount} records processed, {$deletedCount} records deleted.");

            return 0;
        } catch (\Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");
            Log::error("Legacy API data logging error: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Check if the exception is related to a database connection error
     */
    protected function isConnectionError(\Illuminate\Database\QueryException $e): bool
    {
        $message = $e->getMessage();
        $connectionErrors = [
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'server closed the connection unexpectedly',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
            'query_wait_timeout',
            'reset by peer',
            'Physical connection is not usable',
            'TCP Provider: Error code 0x68',
            'ORA-03114',
            'Packets out of order',
            'Error while reading greeting packet',
            'Connection timed out',
        ];

        foreach ($connectionErrors as $errorMessage) {
            if (stripos($message, $errorMessage) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Attempt to reconnect to the database
     */
    protected function reconnectDatabase(): void
    {
        try {
            DB::disconnect('mysql');
            DB::reconnect('mysql');
            $this->info('Successfully reconnected to the database.');
        } catch (\Exception $e) {
            $this->error('Failed to reconnect to the database: '.$e->getMessage());
            Log::error('Database reconnection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle database exceptions with proper logging and messaging
     */
    protected function handleDatabaseException(\Illuminate\Database\QueryException $e, string $context): void
    {
        $message = $e->getMessage();
        $this->error("$context: $message");

        Log::error("$context", [
            'error' => $message,
            'code' => $e->getCode(),
            'sql' => $e->getSql() ?? 'Unknown SQL',
            'trace' => $e->getTraceAsString(),
        ]);

        // Check if this is a connection error and attempt to reconnect
        if ($this->isConnectionError($e)) {
            $this->warn('Detected database connection error. Attempting to reconnect...');
            $this->reconnectDatabase();
        }
    }
}
