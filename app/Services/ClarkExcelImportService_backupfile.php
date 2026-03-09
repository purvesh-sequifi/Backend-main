<?php

namespace App\Services;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\LegacyApiRawDataHistory;
use App\Models\Products;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ClarkExcelImportService
{
    private $sftpService;

    public function __construct()
    {
        $this->sftpService = new ClarkSftpService;
    }

    /**
     * Process Excel files directly from SFTP
     *
     * @param  string  $sftpPath  SFTP directory path containing Excel files
     * @return array Import statistics
     */
    public function processExcelFiles(string $sftpPath = '/clark-momentum/sftpuser', $output = null): array
    {
        $stats = [
            'total_files' => 0,
            'processed_files' => 0,
            'total_records' => 0,
            'processed_records' => 0,
            'updated_records' => 0,
            'skipped_records' => 0,
            'error_records' => 0,
            'error_files' => 0,
            'batch_errors' => [],
        ];

        try {
            Log::channel('clark_excel')->info("Starting Clark Excel import from path: {$sftpPath}");

            // Get list of files from SFTP
            $files = $this->sftpService->listFiles($sftpPath);
            Log::channel('clark_excel')->info('Files found in directory', ['path' => $sftpPath, 'files' => $files]);

            $stats['total_files'] = count($files);
            Log::channel('clark_excel')->info("Processing {$stats['total_files']} files");

            foreach ($files as $file) {
                Log::channel('clark_excel')->info("Processing file: {$file}");
                $exists = DB::table('imported_files')->where('filename', $file)->exists();
                if (! $exists) {
                    DB::beginTransaction();
                    try {
                        // Read file content directly from SFTP
                        $content = $this->sftpService->getFile($file, 300);

                        // Check if content is HTML
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

                                    $result = $this->processBatch($batch, $stats);

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

                            $result = $this->processBatch($batch, $stats);

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

                        // Get unique source types from the imported data
                        // $dataSourceTypes = DB::table('legacy_api_raw_data_histories')
                        //     ->where('import_to_sales', '0')
                        //     ->distinct()
                        //     ->pluck('data_source_type')
                        //     ->toArray();

                        if (! empty($dataSourceTypes)) {
                            if ($output) {
                                $output->info('Found '.count($dataSourceTypes).' unique source types');
                                $output->info('Source types: '.implode(', ', $dataSourceTypes));
                            }

                            // Log for debugging
                            Log::channel('clark_excel')->info('Processing source types', [
                                'count' => count($dataSourceTypes),
                                'types' => $dataSourceTypes,
                            ]);

                            foreach ($dataSourceTypes as $sourceType) {
                                // Get total records for this source type
                                $totalRecords = DB::table('legacy_api_raw_data_histories')
                                    ->where('data_source_type', $sourceType)
                                    ->where('import_to_sales', '0')
                                    ->count();

                                // Calculate number of chunks needed (200 records per chunk)
                                $chunkSize = 200;
                                $totalChunks = ceil($totalRecords / $chunkSize);

                                if ($output) {
                                    $output->info("Found {$totalRecords} records for source type {$sourceType}, processing in {$totalChunks} chunks");
                                }

                                // Process in chunks
                                for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
                                    $user = User::find(1);
                                    $dataForPusher = [
                                        'chunk' => $chunk + 1,
                                        'total_chunks' => $totalChunks,
                                        'source_type' => $sourceType,
                                    ];

                                    // Dispatch job for processing
                                    dispatch(new SaleMasterJob($sourceType, 100, 'Clark-Import'))->onQueue('Clark-Import');

                                    if ($output) {
                                        $output->info("Dispatched SaleMasterJob for source type: {$sourceType}, chunk: ".($chunk + 1)." of {$totalChunks}");
                                    }
                                }
                            }
                        } elseif ($output) {
                            $output->info('No source types to process');
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
                    $output->info("Skipped already imported file: $file");
                }
            }

        } catch (\Exception $e) {
            Log::channel('clark_excel')->error('SFTP connection error: '.$e->getMessage());
            throw $e;
        }

        Log::channel('clark_excel')->info('Excel files processing completed', $stats);

        return $stats;
    }

    /**
     * Parse date from various formats
     */
    private function parseDate(?string $value): ?string
    {
        if (empty($value)) {
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
     * Find user email by full name
     */
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

    /**
     * Import a single row from Excel
     */
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
        $import_to_sales = 0;

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
            'm1_date' => $m1_date,
            'date_cancelled' => $this->parseDate($row[12]),
            'initial_service_date' => $initial_service_date,
            'trigger_date' => ! empty($trigger_date) ? json_encode($trigger_date) : null,
            'product' => $descriptionValue,
            'product_id' => $product_id,
            'gross_account_value' => $this->cleanAmount($row[11]),
            'initial_service_cost' => $this->cleanAmount($row[14]),
            'auto_pay' => $row[13] ?? null,
            'job_status' => $row[6] ?? null,
            'import_to_sales' => $import_to_sales,
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

    /**
     * Process a batch of rows efficiently
     */
    private function processBatch(array $batch, array &$stats)
    {
        $result = [
            'success' => false,
            'recordCount' => 0,
            'errors' => [],
        ];

        try {
            // Find existing records
            $existingRecords = LegacyApiRawDataHistory::whereIn('pid', array_keys($batch))
                ->get()
                ->keyBy('pid');

            $toUpdate = [];
            $toCreate = [];

            foreach ($batch as $pid => $data) {
                if (isset($existingRecords[$pid])) {
                    $toUpdate[] = $data;
                } else {
                    $toCreate[] = $data;
                }
            }

            // Batch update
            if (! empty($toUpdate)) {
                DB::transaction(function () use ($toUpdate) {
                    foreach ($toUpdate as $data) {
                        LegacyApiRawDataHistory::where('pid', $data['pid'])
                            ->update($data);
                    }
                });
                $stats['processed_records'] += count($toUpdate);
                $stats['updated_records'] += count($toUpdate);
                $result['recordCount'] += count($toUpdate);
            }

            // Batch insert
            if (! empty($toCreate)) {
                DB::transaction(function () use ($toCreate) {
                    LegacyApiRawDataHistory::insert($toCreate);
                });
                $stats['processed_records'] += count($toCreate);
                $result['recordCount'] += count($toCreate);
            }

            $result['success'] = true;

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
