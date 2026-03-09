<?php

namespace App\Console\Commands;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\HometeamJsonRawData;
use App\Services\JsonImportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Sentry\CheckInStatus;

class ImportSftpJsonFiles extends Command
{
    protected $signature = 'import:sftp-json
        {--batch=1000 : Number of records to process in each batch}
        {--memory-limit=2048 : Memory limit in MB}
        {--timeout=3600 : Maximum execution time in seconds}
        {--optimize-memory : Use optimized memory mode for very large files}';

    protected $description = 'Import new JSON files from SFTP every 30 minutes';

    /** @var string Sentry monitoring check-in ID */
    protected $checkInId;

    /** @var int The batch size for processing records */
    protected $batchSize;

    /**
     * Execute the console command with improved timeout handling
     */
    public function handle(): int
    {
        // Get batch size from command options
        $this->batchSize = (int) $this->option('batch');
        if ($this->batchSize <= 0) {
            $this->batchSize = 1000; // Default batch size
        }

        // Maximum batch size for database operations (helps prevent memory issues)
        $maxBatchSize = 5000;
        if ($this->batchSize > $maxBatchSize) {
            $this->info("Batch size $this->batchSize exceeds maximum of $maxBatchSize, using $maxBatchSize instead");
            $this->batchSize = $maxBatchSize;
        }

        // Start Sentry monitoring for this cron job - ensuring proper check-in status
        $this->checkInId = \Sentry\captureCheckIn('import-sftp-json', CheckInStatus::inProgress());

        // Check if memory optimization is enabled
        $optimizeMemory = (bool) $this->option('optimize-memory');
        if ($optimizeMemory) {
            $this->info('Memory optimization enabled - will use lower memory footprint at the cost of some performance');
            ini_set('memory_limit', '1G'); // Set a reasonable memory limit for large processing
        }

        // Register shutdown function to ensure check-in is updated even on fatal errors
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (app()->bound('sentry') && $this->checkInId) {
                    \Sentry\captureCheckIn($this->checkInId, CheckInStatus::error());
                    Log::error("Fatal error during SFTP import: {$error['message']} in {$error['file']} on line {$error['line']}");
                }
            }
        });

        try {

            $processedCount = 0;
            $errors = [];

            try {
                $getLastUpdatedDate = DB::table('system_settings')
                    ->select('created_at')
                    ->where('key', 'hometeam_sync_last_run')
                    ->first();

                $dateOnly = \Carbon\Carbon::parse($getLastUpdatedDate->created_at)->toDateString(); // "2025-07-04"

                $jsonArray = HometeamJsonRawData::whereDate('last_updated_date', '>=', $dateOnly)
                    ->get()
                    ->map(function ($item) {
                        return $item->toArray(); // Convert each model to array properly
                    })
                    ->toArray();

                if ($jsonArray) {
                    // Process records in batches
                    $totalRecords = count($jsonArray);
                    $batches = array_chunk($jsonArray, $this->batchSize);
                    $batchCount = count($batches);

                    $this->info("Processing $totalRecords records in $batchCount batches from HometeamJsonRawData");

                    $dataSourceTypes = [];
                    $batchErrors = [];

                    // Initialize the progress bar
                    $progressBar = $this->output->createProgressBar($totalRecords);
                    $this->newLine();

                    // Process batches with transaction support
                    foreach ($batches as $batchNumber => $batchData) {
                        $this->info("Processing batch $batchNumber/$batchCount with ".count($batchData).' records');

                        // Create a closure for progress updates
                        $progressCallback = function () use ($progressBar) {
                            $progressBar->advance();
                        };

                        // Create JsonImportService instance
                        $jsonImportService = new JsonImportService;

                        // Use JsonImportService to process the batch - no need for JSON validation
                        $result = $jsonImportService->processBatch($batchData, $progressCallback);

                        // Report batch results
                        if ($result['success']) {
                            $this->info("Successfully committed batch $batchNumber/$batchCount (inserted {$result['recordCount']} records)");
                        } else {
                            $batchErrors[] = "Batch $batchNumber in files failed to process completely";
                        }

                        // Add any batch-specific errors to our overall errors list
                        if (! empty($result['errors'])) {
                            foreach ($result['errors'] as $error) {
                                $batchErrors[] = "Batch $batchNumber in files: $error";
                            }
                        }

                        $batchNumber++;
                    }
                    // Finish the progress bar
                    $progressBar->finish();
                    $this->newLine();
                    $this->info('All records from files processed successfully.');

                    // Process SaleMasterJob for each data source type

                    // Get total records for this source type
                    $totalRecords = DB::table('legacy_api_raw_data_histories')
                        ->where('data_source_type', 'HomeTeam')
                        ->where('import_to_sales', '0')
                        ->count();

                    // Calculate number of chunks needed (100 records per chunk)
                    $chunkSize = 200;
                    $totalChunks = ceil($totalRecords / $chunkSize);

                    $this->info("Found {$totalRecords} records for source type HomeTeam, processing in {$totalChunks} chunks");

                    // Process in chunks
                    // Create ONE job per source type (SaleMasterJob handles internal chunking)
                    if ($totalRecords > 0) {
                        $this->info("Found {$totalRecords} records for source type HomeTeam, dispatching single SaleMasterJob");

                        // Create ONE job per source type - let SaleMasterJob handle chunking internally
                        dispatch(new SaleMasterJob('HomeTeam', 100, 'HomeTeam-Import'))->onQueue('HomeTeam-Import');

                        $this->info('Dispatched SaleMasterJob for source type: HomeTeam');
                    } else {
                        $this->info('No unprocessed records found for source type: HomeTeam');
                    }

                    // If we had any batch errors, add them to the main errors array
                    if (! empty($batchErrors)) {
                        $errors = array_merge($errors, $batchErrors);
                    }

                    // Mark file as imported regardless of batch errors
                    // DB::table('imported_files')->insert([
                    //     'filename' => 'files',
                    //     'imported_at' => Carbon::now()
                    // ]);
                    $processedCount++;
                    $this->info('Imported files');
                } else {
                    Log::warning('Empty file content');
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to process files', [
                    'error' => $e->getMessage(),
                ]);
                $errors[] = "Files: {$e->getMessage()}";
            }
            // } else {
            //     $this->info("Skipped already imported file: $file");
            // }
            // }
            // }

            // Complete Sentry monitoring with appropriate status
            $status = empty($errors) ? CheckInStatus::ok() : CheckInStatus::error();
            \Sentry\captureCheckIn('import-sftp-json', $status, $this->checkInId);

            // Output summary
            if ($processedCount > 0) {
                DB::table('system_settings')
                    ->where('key', 'hometeam_sync_last_run')
                    ->update([
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                $this->info("Successfully processed $processedCount files");
            } else {
                $this->info('No new files to process');
            }

            if (! empty($errors)) {
                $this->error('Encountered '.count($errors).' errors during import');
                foreach ($errors as $error) {
                    $this->line(" - $error");
                }

                return 1;
            }

        } catch (\Exception $e) {
            Log::error('Exception in SFTP import job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark Sentry check-in as error
            \Sentry\captureCheckIn('import-sftp-json', CheckInStatus::error(), $this->checkInId);

            $this->error('Import job failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
