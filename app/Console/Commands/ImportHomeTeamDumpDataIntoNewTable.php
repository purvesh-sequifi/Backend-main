<?php

namespace App\Console\Commands;

use App\Jobs\Sales\SaleMasterJob;
use App\Services\JsonImportService;
use App\Services\JsonImportServiceDumpData;
use App\Services\SftpService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportHomeTeamDumpDataIntoNewTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:home-team-dump-data-into-new-table
                            {--batch=100 : Number of records to process in one batch}
                            {--memory-limit=512 : Memory limit in MB}
                            {--timeout=0 : Script timeout in seconds}
                            {--optimize-memory : Enable memory optimization mode}
                            {--debug : Enable detailed debugging output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import HomeTeam dump data into the new table';

    /** @var int The batch size for processing records */
    protected $batchSize;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Configure PHP execution time limits
        $maxExecutionTime = (int) $this->option('timeout');
        ini_set('max_execution_time', $maxExecutionTime);
        set_time_limit($maxExecutionTime);

        // Configure memory limit
        $memoryLimit = (int) $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit.'M');

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

        $this->info("Running import with batch size: {$this->batchSize}");
        $this->info("Max execution time set to {$maxExecutionTime} seconds");

        // Check if memory optimization is enabled
        $optimizeMemory = (bool) $this->option('optimize-memory');
        if ($optimizeMemory) {
            $this->info('Memory optimization enabled - will use lower memory footprint at the cost of some performance');
            ini_set('memory_limit', '1G'); // Set a reasonable memory limit for large processing
        }

        // Register shutdown function to log fatal errors
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                Log::error("Fatal error during SFTP import: {$error['message']} in {$error['file']} on line {$error['line']}");
            }
        });

        try {
            // Get SFTP connection details from config
            $remotePath = Config::get('sftp.connections.default.remote_path');
            $sftpService = null;

            try {
                // Initialize SFTP service
                $sftpService = new SftpService('default');

                // Connect to SFTP server
                $this->info('Connecting to SFTP server');
                $sftpService->connect();
                $this->info('SFTP connection successful');

                // List files in directory
                $this->info('Listing files in remote directory');
                $files = $sftpService->listFiles($remotePath);
                $this->info('Found '.count($files).' files in remote directory');

            } catch (\Exception $e) {
                $errorMessage = "SFTP operation error: {$e->getMessage()}";
                Log::error($errorMessage, ['exception' => $e]);

                $this->error($errorMessage);

                return 1;
            }

            $processedCount = 0;
            $errors = [];

            foreach ($files as $file) {
                if (str_ends_with($file, '.Json')) {
                    // Check if this file was already imported
                    $exists = DB::table('imported_files')->where('filename', $file)->exists();
                    if (! $exists) {
                        try {
                            $this->info("Retrieving file {$file}");

                            // Use the SFTP service to get the file
                            $jsonContent = $sftpService->getFile($remotePath.'/'.$file);

                            $this->info('File retrieved successfully');

                            if (empty($jsonContent)) {
                                Log::warning("Empty file content : $file");
                                break;
                            }

                            // Use JsonImportService to validate and process JSON
                            $jsonImportService = new JsonImportServiceDumpData;
                            $jsonArray = $jsonImportService->validateAndProcessJson($jsonContent, $file);

                            if (! is_array($jsonArray)) {
                                throw new \InvalidArgumentException('Invalid or malformed JSON structure: '.$file);
                            }

                            if ($jsonArray) {
                                // Process records in batches
                                $totalRecords = count($jsonArray);
                                $batches = array_chunk($jsonArray, $this->batchSize);
                                $batchCount = count($batches);

                                $this->info("Processing file $file: $totalRecords records in $batchCount batches");

                                $dataSourceTypes = [];
                                $batchErrors = [];
                                $batchNumber = 1;

                                // Create a progress bar for this file
                                $progressBar = $this->output->createProgressBar($totalRecords);
                                $progressBar->start();

                                foreach ($batches as $batch) {
                                    $this->newLine();
                                    $this->info("Processing batch $batchNumber/$batchCount with ".count($batch).' records');

                                    // Calculate records to advance progress bar for this batch
                                    $batchRecordCount = 0;
                                    foreach ($batch as $record) {
                                        $batchRecordCount += count($record);
                                    }

                                    // Use the JsonImportService to process the batch with optimized database operations
                                    // Pass the batch size as the chunk size for efficient bulk inserts
                                    $result = $jsonImportService->processBatch($batch, function ($index) use ($progressBar) {
                                        $progressBar->advance();
                                    }, $this->batchSize);

                                    // Add source types from this batch to overall tracking
                                    if (! empty($result['sourceTypes'])) {
                                        foreach ($result['sourceTypes'] as $sourceType) {
                                            $dataSourceTypes[$sourceType] = 'HomeTeam'; // $sourceType;
                                        }
                                    }

                                    // Report batch results
                                    if ($result['success']) {
                                        $this->info("Successfully committed batch $batchNumber/$batchCount (inserted {$result['recordCount']} records)");
                                    } else {
                                        $batchErrors[] = "Batch $batchNumber in $file failed to process completely";
                                    }

                                    // Add any batch-specific errors to our overall errors list
                                    if (! empty($result['errors'])) {
                                        foreach ($result['errors'] as $error) {
                                            $batchErrors[] = "Batch $batchNumber in $file: $error";
                                        }
                                    }

                                    $batchNumber++;
                                }
                                // Finish the progress bar
                                $progressBar->finish();
                                $this->newLine();
                                $this->info("All records from file $file processed successfully.");

                                // Process SaleMasterJob for each data source type
                                if (! empty($dataSourceTypes)) {
                                    $this->info('Processing source types: '.implode(', ', $dataSourceTypes));
                                } else {
                                    $this->info('No source types to process');
                                }

                                // If we had any batch errors, add them to the main errors array
                                if (! empty($batchErrors)) {
                                    $errors = array_merge($errors, $batchErrors);
                                }

                                // Mark file as imported regardless of batch errors
                                DB::table('imported_files')->insert([
                                    'filename' => $file,
                                    'imported_at' => Carbon::now(),
                                ]);
                                $processedCount++;
                                $this->info("Imported file: $file");
                            } else {
                                Log::warning("Empty file content: $file");
                            }
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::error("Failed to process file: $file", [
                                'error' => $e->getMessage(),
                            ]);
                            $errors[] = "File $file: {$e->getMessage()}";
                        }
                    } else {
                        $this->info("Skipped already imported file: $file");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Exception in SFTP import job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Import job failed: '.$e->getMessage());

            return 1;
        }
    }
}
