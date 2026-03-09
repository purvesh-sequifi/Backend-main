<?php

namespace App\Jobs;

use App\Models\LegacyApiRawDataHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPocomosDataBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The batch of Pocomos data to process.
     *
     * @var array
     */
    protected $batch;

    /**
     * The data source type to assign to the records.
     *
     * @var string
     */
    protected $dataSourceType;

    /**
     * The integration ID associated with this batch.
     *
     * @var int
     */
    protected $integrationId;

    /**
     * The branch ID associated with this batch.
     *
     * @var string|null
     */
    protected $branchId;

    /**
     * Create a new job instance.
     *
     * @param  array  $batch  The batch of data to process
     * @param  string  $dataSourceType  The data source type
     * @param  int  $integrationId  The integration ID
     * @param  string|null  $branchId  The branch ID
     * @return void
     */
    public function __construct(array $batch, string $dataSourceType, int $integrationId, ?string $branchId = null)
    {
        $this->batch = $batch;
        $this->dataSourceType = $dataSourceType;
        $this->integrationId = $integrationId;
        $this->branchId = $branchId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batchSize = count($this->batch);
        $newRecords = 0;
        $errors = 0;

        Log::channel('pocomos')->info('Starting batch processing', [
            'batch_size' => $batchSize,
            'integration_id' => $this->integrationId,
            'branch_id' => $this->branchId,
        ]);

        try {
            // Start a database transaction for bulk insert
            DB::beginTransaction();

            // Add timestamps to each record in the batch
            $now = now();
            foreach ($this->batch as &$record) {
                // For new records, set both created_at and updated_at
                if (! isset($record['created_at'])) {
                    $record['created_at'] = $now;
                    $record['updated_at'] = $now;
                }
                // We don't update updated_at for existing records in a bulk insert
                // as this would typically be handled by the update() method instead
            }

            // Insert all records in a single bulk operation
            LegacyApiRawDataHistory::insert($this->batch);

            // Commit the transaction
            DB::commit();

            // Count new records
            $newRecords = $batchSize;

            Log::channel('pocomos')->info('Successfully processed Pocomos data batch', [
                'integration_id' => $this->integrationId,
                'branch_id' => $this->branchId,
                'records_processed' => $batchSize,
                'new_records' => $newRecords,
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            DB::rollBack();

            // Log the error
            Log::channel('pocomos')->error("Error processing Pocomos data batch: {$e->getMessage()}", [
                'integration_id' => $this->integrationId,
                'branch_id' => $this->branchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Handle database reconnection if needed
            if (
                strpos($e->getMessage(), 'MySQL server has gone away') !== false ||
                strpos($e->getMessage(), 'Lost connection') !== false ||
                strpos($e->getMessage(), 'Error while reading greeting packet') !== false
            ) {
                Log::channel('pocomos')->warning(
                    'Database connection lost in Pocomos batch job. Attempting to reconnect...',
                    [
                        'integration_id' => $this->integrationId,
                        'branch_id' => $this->branchId,
                    ]
                );

                try {
                    // Disconnect and reconnect to the database
                    DB::disconnect('mysql');
                    DB::reconnect('mysql');

                    Log::channel('pocomos')->info(
                        'Database reconnection successful. Retrying batch processing...',
                        [
                            'integration_id' => $this->integrationId,
                            'branch_id' => $this->branchId,
                        ]
                    );

                    // Retry the bulk insertion after reconnection
                    DB::beginTransaction();

                    // Add timestamps to each record in the batch
                    $now = now();
                    foreach ($this->batch as &$record) {
                        // For new records, set both created_at and updated_at
                        if (! isset($record['created_at'])) {
                            $record['created_at'] = $now;
                            $record['updated_at'] = $now;
                        }
                        // We don't update updated_at for existing records in a bulk insert
                        // as this would typically be handled by the update() method instead
                    }

                    LegacyApiRawDataHistory::insert($this->batch);
                    DB::commit();

                    $newRecords = $batchSize;

                    Log::channel('pocomos')->info('Successfully processed Pocomos data batch after reconnection', [
                        'integration_id' => $this->integrationId,
                        'branch_id' => $this->branchId,
                        'records_processed' => $batchSize,
                        'new_records' => $newRecords,
                    ]);
                } catch (\Exception $retryException) {
                    DB::rollBack();
                    $errors++;

                    Log::channel('pocomos')->error(
                        "Retry failed after database reconnection: {$retryException->getMessage()}",
                        [
                            'integration_id' => $this->integrationId,
                            'branch_id' => $this->branchId,
                            'error' => $retryException->getMessage(),
                            'trace' => $retryException->getTraceAsString(),
                        ]
                    );

                    // For database reconnection failures, we'll try one more approach:
                    // Process records individually to identify problematic ones
                    $this->processRecordsIndividually();
                }
            } else {
                // For other types of errors, try individual processing as a fallback
                $this->processRecordsIndividually();
            }
        }
    }

    /**
     * Process records individually as a fallback when bulk insert fails
     */
    protected function processRecordsIndividually(): void
    {
        Log::channel('pocomos')->info(
            'Falling back to individual record processing after batch failure',
            [
                'integration_id' => $this->integrationId,
                'batch_size' => count($this->batch),
            ]
        );

        $successCount = 0;
        $failureCount = 0;

        foreach ($this->batch as $record) {
            try {
                LegacyApiRawDataHistory::create($record);
                $successCount++;
            } catch (\Exception $e) {
                $failureCount++;

                Log::channel('pocomos')->warning(
                    "Failed to process individual record: {$e->getMessage()}",
                    [
                        'integration_id' => $this->integrationId,
                        'branch_id' => $this->branchId,
                        'pid' => $record['pid'] ?? 'unknown',
                    ]
                );
            }
        }

        Log::channel('pocomos')->info(
            'Individual processing complete',
            [
                'integration_id' => $this->integrationId,
                'branch_id' => $this->branchId,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]
        );
    }
}
