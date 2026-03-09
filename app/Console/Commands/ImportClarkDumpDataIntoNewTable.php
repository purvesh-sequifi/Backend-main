<?php

namespace App\Console\Commands;

use App\Models\ClarkExcelRawData;
use App\Models\Products;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use App\Services\ClarkExcelImportService;
use App\Services\ClarkSftpService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportClarkDumpDataIntoNewTable extends Command
{
    /**
     * Flag for debug mode
     *
     * @var bool
     */
    protected $debugEnabled = false;

    /**
     * Service dependencies
     */
    protected $sftpService;

    protected $excelImportService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:clark-dump-data-into-new-table
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
    protected $description = 'Import Clark dump data into the new table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function __construct()
    {
        parent::__construct();
        $this->sftpService = new ClarkSftpService;
        $this->excelImportService = new ClarkExcelImportService;
    }

    public function handle(string $sftpPath = '/clark-momentum/sftpuser', $output = null): int
    {
        // Set memory and time limits from command options
        $memoryLimit = $this->option('memory-limit');
        $timeoutLimit = $this->option('timeout');
        $optimizeMemory = $this->option('optimize-memory');
        $batchSize = $this->option('batch');
        $this->debugEnabled = $this->option('debug');

        if ($this->debugEnabled) {
            $this->info('DEBUG MODE ENABLED');
            $this->info("Memory limit: {$memoryLimit}M, Timeout: {$timeoutLimit}s, Batch size: {$batchSize}, Optimize memory: ".($optimizeMemory ? 'Yes' : 'No'));
        }

        // Apply limits
        ini_set('memory_limit', $memoryLimit.'M');
        ini_set('max_execution_time', $timeoutLimit);

        // Initialize stats array with all required keys
        $stats = [
            'total_files' => 0,
            'skipped_files' => 0,
            'processed_files' => 0,
            'total_records' => 0,
            'processed_records' => 0,
            'inserted' => 0,
            'updated' => 0,
            'error_records' => 0,
            'skipped_records' => 0,
            'error_files' => 0,
            'errors' => 0,
            'batch_errors' => [],
        ];

        try {
            Log::channel('clark_excel')->info("Starting Clark Excel import from path: {$sftpPath}");

            // Get list of files from SFTP
            if ($this->debugEnabled) {
                $this->info("Attempting to connect to SFTP path: {$sftpPath}");
            }

            try {
                $s3Path = 'sftpuser';
                $files = Storage::disk('aws_s3')->allFiles($s3Path);
                $stats['total_files'] = count($files);
                Log::channel('clark_excel')->info("Found {$stats['total_files']} files in SFTP path: {$sftpPath}", ['files' => $files]);

                if ($this->debugEnabled) {
                    $this->info("Found {$stats['total_files']} files in SFTP path");
                    if (count($files) > 0) {
                        $this->info('Sample files: '.implode(', ', array_slice($files, 0, 3)));
                    } else {
                        $this->warn('No files found in SFTP path! Testing with local sample file instead.');
                        // For testing purposes, use a local Excel file if no SFTP files found
                        $localTestFile = storage_path('app/clark_test.xlsx');
                        if (file_exists($localTestFile)) {
                            $files = ['local:'.$localTestFile];
                            $stats['total_files'] = 1;
                            $this->info("Using local test file: {$localTestFile}");
                        } else {
                            $this->error("No local test file found at {$localTestFile}");
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::channel('clark_excel')->error('Failed to list SFTP files: '.$e->getMessage());
                if ($this->debugEnabled) {
                    $this->error('Failed to list SFTP files: '.$e->getMessage());
                    $this->warn('Testing with local sample file instead.');
                    // For testing purposes, use a local Excel file if SFTP connection fails
                    $localTestFile = storage_path('app/clark_test.xlsx');
                    if (file_exists($localTestFile)) {
                        $files = ['local:'.$localTestFile];
                        $stats['total_files'] = 1;
                        $this->info("Using local test file: {$localTestFile}");
                    } else {
                        $this->error("No local test file found at {$localTestFile}");
                        $files = [];
                    }
                }
            }

            Log::channel('clark_excel')->info("Processing {$stats['total_files']} files");
            foreach ($files as $file) {
                $aws_file = $file;
                $file = basename($file);
                Log::channel('clark_excel')->info("Processing file: {$file}");
                $exists = DB::table('imported_files')->where('filename', $file)->exists();
                if (! $exists) {
                    DB::beginTransaction();
                    try {
                        // Get file content
                        Log::channel('clark_excel')->info("Attempting to read file: {$file}");

                        if (strpos($file, 'local:') === 0) {
                            // Handle local file for testing
                            $localPath = substr($file, 6); // Remove 'local:' prefix
                            $content = file_get_contents($localPath);
                            if ($this->debugEnabled) {
                                $this->info("Reading local file: {$localPath}");
                            }
                        } else {
                            // Normal SFTP file handling
                            $content = Storage::disk('aws_s3')->get($aws_file);

                        }

                        Log::channel('clark_excel')->info("Successfully read file: {$file}, content length: ".strlen($content));

                        if ($this->debugEnabled) {
                            $this->info('Successfully read file, content length: '.strlen($content));
                        }

                        // Process based on file type                           // Check if content is HTML
                        if (stripos($content, '<tr>') !== false) {
                            Log::channel('clark_excel')->info('Detected HTML content, parsing table data');

                            // Create a DOMDocument to parse HTML
                            $dom = new \DOMDocument;
                            @$dom->loadHTML($content, LIBXML_NOERROR | LIBXML_NOWARNING);

                            // Find all table rows
                            $rows = $dom->getElementsByTagName('tr');

                            // Convert to array format
                            $data = [];
                            foreach ($rows as $row) {
                                $rowData = [];
                                $cells = $row->getElementsByTagName('td');

                                foreach ($cells as $cell) {
                                    // Clean and normalize the cell content
                                    $value = trim($cell->textContent);
                                    $value = str_replace('&nbsp;', '', $value);
                                    $rowData[] = $value;
                                }

                                if (! empty($rowData)) {
                                    $data[] = $rowData;
                                }
                            }

                            // Create temporary CSV file
                            $tempFile = tempnam(sys_get_temp_dir(), 'clark_data_');
                            $tempFile .= '.csv';

                            // Write data to CSV
                            $fp = fopen($tempFile, 'w');
                            foreach ($data as $row) {
                                fputcsv($fp, $row);
                            }
                            fclose($fp);

                        } else {
                            // Handle as regular Excel file
                            Log::channel('clark_excel')->info('Processing as Excel file');
                            $tempFile = tempnam(sys_get_temp_dir(), 'clark_excel_');
                            $tempFile .= '.xls';
                            file_put_contents($tempFile, $content);
                        }

                        // Debug: Check temp file
                        Log::channel('clark_excel')->info('Temp file path: '.$tempFile);
                        Log::channel('clark_excel')->info('Temp file size: '.filesize($tempFile));
                        Log::channel('clark_excel')->info('Temp file mime type: '.mime_content_type($tempFile));

                        $rows = [];

                        if (pathinfo($tempFile, PATHINFO_EXTENSION) === 'csv') {
                            // Read CSV file
                            Log::channel('clark_excel')->info('Reading CSV file');
                            if (($handle = fopen($tempFile, 'r')) !== false) {
                                while (($data = fgetcsv($handle)) !== false) {
                                    $rows[] = $data;
                                }
                                fclose($handle);
                            }
                        } else {
                            // Handle Excel file
                            Log::channel('clark_excel')->info('Reading Excel file');
                            $inputFileType = IOFactory::identify($tempFile);
                            Log::channel('clark_excel')->info('Excel file type identified as: '.$inputFileType);

                            $reader = IOFactory::createReader($inputFileType);
                            $reader->setReadDataOnly(true);

                            $spreadsheet = $reader->load($tempFile);
                            $worksheet = $spreadsheet->getActiveSheet();
                            $rows = $worksheet->toArray();
                        }

                        // Clean up temp file
                        unlink($tempFile);

                        // Skip header row
                        array_shift($rows);
                        $stats['total_records'] += count($rows);

                        // Setup progress bar
                        $totalRecords = count($rows);
                        $batchSize = 100;
                        $batchCount = ceil($totalRecords / $batchSize);
                        $batchNumber = 1;
                        $batch = [];
                        $updateBatch = [];
                        $dataSourceTypes = [];

                        if ($output) {
                            $progressBar = $output->createProgressBar($totalRecords);
                            $progressBar->start();
                            $output->newLine();
                            $output->info("Processing file: {$file} with {$totalRecords} records");
                        }
                        foreach ($rows as $row) {
                            try {
                                $processedData = $this->prepareRowData($row);
                                if ($processedData) {
                                    $pid = $processedData['pid'];
                                    $batch[$pid] = $processedData;

                                    // Collect source type for every valid row
                                    if (! empty($row[2])) {
                                        $sourceType = 'Clark-'.trim($row[2]);
                                        $dataSourceTypes[$sourceType] = $sourceType;
                                    }
                                }

                                // Process batch when it reaches the size limit
                                if (count($batch) >= $batchSize) {
                                    if ($output) {
                                        $output->info("Processing batch {$batchNumber}/{$batchCount} with ".count($batch).' records');
                                    }

                                    Log::channel('clark_excel')->info('Processing batch with '.count($batch).' records');
                                    $result = $this->processBatch($batch, $stats);
                                    Log::channel('clark_excel')->info('Batch processing result', ['success' => $result['success'], 'count' => $result['recordCount']]);
                                    $stats['processed_records'] += $result['recordCount'];

                                    if ($output) {
                                        if ($result['success']) {
                                            $output->info("Successfully processed batch {$batchNumber}/{$batchCount}");
                                        } else {
                                            $stats['batch_errors'][] = "Batch {$batchNumber} in {$file} failed to process completely";
                                        }
                                        $progressBar->advance(count($batch));
                                    }

                                    $batch = [];
                                    $batchNumber++;

                                    // Memory optimization
                                    if ($optimizeMemory) {
                                        gc_collect_cycles();
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::channel('clark_excel')->error('Error processing row in file '.$file.': '.$e->getMessage());
                                $stats['error_records']++;

                                continue;
                            }
                        }

                        // Process remaining batch
                        if (! empty($batch)) {
                            if ($output) {
                                $output->info("Processing final batch {$batchNumber}/{$batchCount} with ".count($batch).' records');
                            }

                            Log::channel('clark_excel')->info('Processing batch with '.count($batch).' records');
                            $result = $this->processBatch($batch, $stats);
                            Log::channel('clark_excel')->info('Batch processing result', ['success' => $result['success'], 'count' => $result['recordCount']]);
                            $stats['processed_records'] += $result['recordCount'];

                            if ($output) {
                                if ($result['success']) {
                                    $output->info("Successfully processed final batch {$batchNumber}/{$batchCount}");
                                } else {
                                    $stats['batch_errors'][] = "Final batch in {$file} failed to process completely";
                                }
                                $progressBar->advance(count($batch));
                            }
                        }

                        if ($output) {
                            $progressBar->finish();
                            $output->newLine();
                            $output->info("Completed processing file: {$file}");
                        }

                        DB::table('imported_files')->insert([
                            'filename' => $file,
                            'source_type' => 'clark_excel',
                            'imported_at' => Carbon::now(),
                        ]);

                        DB::commit();
                        $stats['processed_files']++;

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::channel('clark_excel')->error('Error processing Excel file: '.$file.' - '.$e->getMessage());
                        $stats['error_files']++;
                    }

                } else {
                    Log::channel('clark_excel')->info("Skipped already imported file: $file");
                }
            }

        } catch (\Exception $e) {
            Log::channel('clark_excel')->error('SFTP connection error: '.$e->getMessage());

            throw $e;
        }

        Log::channel('clark_excel')->info('Excel files processing completed', $stats);

        if ($output) {
            $output->info('Import completed with the following statistics:');
            $output->table(
                ['Metric', 'Value'],
                [
                    ['Total Files', $stats['total_files']],
                    ['Processed Files', $stats['processed_files']],
                    ['Total Records', $stats['total_records']],
                    ['Processed Records', $stats['processed_records']],
                    ['Updated Records', $stats['updated']],
                    ['Skipped Records', $stats['skipped_records']],
                    ['Error Records', $stats['error_records']],
                    ['Error Files', $stats['error_files']],
                ]
            );

            if (! empty($stats['batch_errors'])) {
                $output->error('The following batch errors occurred:');
                foreach ($stats['batch_errors'] as $error) {
                    $output->error($error);
                }
            }
        }

        // Output final summary if output is available
        if ($output) {
            if ($stats['inserted'] > 0 || $stats['updated'] > 0) {
                $output->success("Successfully processed records: {$stats['inserted']} inserted, {$stats['updated']} updated out of {$stats['total_records']} total records");
                $output->info("Processed {$stats['processed_files']} files, skipped {$stats['skipped_files']} files");
            } else {
                $output->info('No records were inserted or updated.');
            }
        } else {
            // Log summary when no output available
            Log::channel('clark_excel')->info(
                "Summary: {$stats['inserted']} inserted, {$stats['updated']} updated out of {$stats['total_records']} total records. ".
                "Processed {$stats['processed_files']} files, skipped {$stats['skipped_files']} files."
            );
        }

        DB::table('system_settings')
            ->where('key', 'clark_sync_last_run')
            ->update([
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        return Command::SUCCESS;
    }

    private function prepareRowData(array $row): ?array
    {
        // Skip empty rows
        if (empty(array_filter($row))) {
            return null;
        }

        $now = Carbon::now();
        // Handle dropdown value for salesperson
        $salesPersonName = $row[3] ?? '';
        // Clean up the name
        $salesPersonName = trim(preg_replace('/\s+/', ' ', $salesPersonName));
        $sales_rep_email = $salesPersonName ? $this->findUserEmailByName($salesPersonName) : null;

        // Get user_id and import_to_sales status
        $user = null;
        if ($sales_rep_email) {
            $user = User::where('email', $sales_rep_email)->first();
            if (! $user) {
                $additionalEmail = UsersAdditionalEmail::with('user')->where('email', $sales_rep_email)->first();
                if ($additionalEmail) {
                    $user = $additionalEmail->user;
                }
            }
        }
        $user_id = $user ? $user->id : null;

        // Handle product lookup with caching
        $descriptionValue = $row[4] ?? null;
        $productCode = $descriptionValue ? strtolower(str_replace(' ', '', $descriptionValue)) : null;

        static $productsCache = [];
        if ($productCode) {
            if (! isset($productsCache[$productCode])) {
                $product = Products::withTrashed()->where('product_id', $productCode)->first();
                if (! $product) {
                    if (! isset($productsCache['default'])) {
                        $productsCache['default'] = Products::withTrashed()
                            ->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))
                            ->first();
                    }
                    $product = $productsCache['default'];
                }
                $productsCache[$productCode] = $product;
            }
            $product = $productsCache[$productCode];
            $product_id = $product ? $product->id : null;
        } else {
            $product_id = null;
        }

        // Handle dates and trigger date array
        $m1_date = $this->parseDate($row[10] ?? null);
        $initial_service_date = $this->parseDate($row[10] ?? null);
        $trigger_date = [
            ['date' => $initial_service_date ? $initial_service_date : null],
            ['date' => null],
        ];

        // Map Excel columns to database fields
        $data = [
            // Required fields
            'pid' => $row[0],
            'customer_name' => str_replace(',', '', $row[1]),
            'data_source_type' => 'Clark-'.($row[2] ?? ''),
            'source_created_at' => $now,
            'source_updated_at' => $now,

            // Optional fields (can be null)
            'closer1_id' => $user_id,
            'sales_rep_name' => $salesPersonName,
            'sales_rep_email' => $sales_rep_email,
            'customer_signoff' => $this->parseDate($row[7]),
            'date_cancelled' => $this->parseDate($row[12]),
            'initial_service_date' => $initial_service_date,
            'trigger_date' => ! empty($trigger_date) ? json_encode($trigger_date) : null,
            'product' => $descriptionValue,
            'product_id' => $product_id,
            'gross_account_value' => $this->cleanAmount($row[11]),
            'initial_service_cost' => $this->cleanAmount($row[14]),
            'auto_pay' => $row[13] ?? null,
            'job_status' => $row[6] ?? null,
        ];

        try {
            // Validate required fields
            if (empty($data['pid']) || empty($data['customer_name']) || empty($data['data_source_type'])) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            Log::channel('clark_excel')->error('Error preparing row data', [
                'row' => $row,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function processBatch(array $batch, array &$stats)
    {
        $result = [
            'success' => false,
            'recordCount' => 0,
            'updateCount' => 0,
            'insertCount' => 0,
            'errors' => [],
        ];

        try {
            $records = array_values($batch);

            // Add debug logging for the batch
            if ($this->debugEnabled) {
                $sampleRecord = json_encode(reset($records));
                $this->info('Processing batch of '.count($records).' records');
                $this->info('Sample record: '.substr($sampleRecord, 0, 150).'...');
                Log::channel('clark_excel')->info('Processing batch of '.count($records).' records', [
                    'record_count' => count($records),
                    'sample_record' => $sampleRecord,
                ]);
            }

            $now = now()->toDateTimeString();
            $inserts = [];
            $updates = [];

            // Collect PIDs for batch lookup
            $pids = array_column($records, 'pid');
            $existingRecords = ClarkExcelRawData::whereIn('pid', $pids)->get()->keyBy('pid');

            foreach ($records as $record) {
                $pid = $record['pid'];

                // Check if record exists
                if ($existingRecords->has($pid)) {
                    $existing = $existingRecords->get($pid);
                    $needsUpdate = false;

                    // Compare fields that trigger updates
                    $fieldsToCompare = [
                        'customer_name',
                        'sales_rep_name',
                        'customer_signoff',
                        'job_status',
                        'gross_account_value',
                        'date_cancelled',
                        'initial_service_date',
                    ];

                    foreach ($fieldsToCompare as $field) {
                        if (isset($record[$field]) && $record[$field] != $existing->$field) {
                            $needsUpdate = true;
                            break;
                        }
                    }

                    if ($needsUpdate) {
                        // Update only if there are changes
                        $record['updated_at'] = $now;
                        $record['last_updated_date'] = $now;
                        $updates[] = $record;

                        // Update record
                        ClarkExcelRawData::where('pid', $pid)->update($record);
                        $result['updateCount']++;
                        $stats['updated'] = ($stats['updated'] ?? 0) + 1;

                        if ($this->debugEnabled) {
                            $this->info("Updated record with PID: {$pid}");
                        }
                    }
                } else {
                    // Insert new record
                    $record['created_at'] = $now;
                    $record['updated_at'] = $now;
                    $record['last_updated_date'] = $now;
                    $inserts[] = $record;
                }
            }

            // Bulk insert new records
            if (! empty($inserts)) {
                ClarkExcelRawData::insert($inserts);
                $result['insertCount'] = count($inserts);
                $stats['inserted'] += count($inserts);

                if ($this->debugEnabled) {
                    $this->info("Inserted {$result['insertCount']} new records");
                }
            }

            $result['success'] = true;
            $result['recordCount'] = count($records);

            if ($this->debugEnabled) {
                $this->info("Successfully processed batch: {$result['insertCount']} inserts, {$result['updateCount']} updates");
            }

        } catch (\Exception $e) {
            Log::channel('clark_excel')->error('Error in batch processing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $stats['error_records'] += count($batch);
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    private function findUserEmailByName(string $fullName): ?string
    {
        // Clean and normalize the input name
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));

        // Try exact match first
        $user = User::where(DB::raw("CONCAT(first_name, ' ', last_name)"), $fullName)
            ->orWhere(DB::raw("CONCAT(last_name, ' ', first_name)"), $fullName)
            ->first();

        if ($user) {
            // Check if user has additional email
            $additionalEmail = UsersAdditionalEmail::where('user_id', $user->id)->first();

            return $additionalEmail ? $additionalEmail->email : $user->email;
        }

        // If no exact match, try partial match
        $user = User::where(function ($query) use ($fullName) {
            $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', '%'.$fullName.'%')
                ->orWhere(DB::raw("CONCAT(last_name, ' ', first_name)"), 'LIKE', '%'.$fullName.'%');
        })->first();

        if ($user) {
            // Check if user has additional email
            $additionalEmail = UsersAdditionalEmail::where('user_id', $user->id)->first();

            return $additionalEmail ? $additionalEmail->email : $user->email;
        }

        return null;
    }

    private function parseDate(?string $value): ?string
    {

        if (in_array($value, ["\u{00A0}", '', ' ', '', '1/1/1900'])) {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::channel('clark_excel')->warning('Failed to parse date: '.$value);

            return null;
        }
    }

    /**
     * Clean amount values by removing '$' and ',' characters
     */
    private function cleanAmount(?string $amount): ?float
    {
        if (empty($amount)) {
            return null;
        }

        // Remove '$' and ',' characters and convert to float
        $cleaned = str_replace(['$', ','], '', trim($amount));

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}
