<?php

namespace App\Services;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\ClarkExcelRawData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    public function processExcelFiles($output = null): array
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

            DB::beginTransaction();
            try {
                $getLastUpdatedDate = DB::table('system_settings')
                    ->select('created_at')
                    ->where('key', 'clark_sync_last_run')
                    ->first();

                $dateOnly = \Carbon\Carbon::parse($getLastUpdatedDate->created_at)->toDateString(); // "2025-07-04"

                $rows = ClarkExcelRawData::whereDate('last_updated_date', '>=', $dateOnly)->get();
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
                }

                foreach ($rows as $row) {

                    try {
                        $processedData = $this->prepareRowData($row->toArray());
                        if ($processedData) {
                            $pid = $processedData['pid'];
                            $batch[$pid] = $processedData;

                            // Collect source type for every valid row
                            if (! empty($row['data_source_type'])) {
                                $sourceType = trim($row['data_source_type']);
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
                                }
                                $progressBar->advance(count($batch));
                            }

                            $batch = [];
                            $batchNumber++;
                        }
                    } catch (\Exception $e) {
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
                        }
                        $progressBar->advance(count($batch));
                    }
                }

                if ($output) {
                    $progressBar->finish();
                    $output->newLine();
                }

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
                            dispatch(new SaleMasterJob($sourceType, 100, 'sales-process'))->onQueue('sales-process');

                            if ($output) {
                                $output->info("Dispatched SaleMasterJob for source type: {$sourceType}, chunk: ".($chunk + 1)." of {$totalChunks}");
                            }
                        }
                    }
                } elseif ($output) {
                    $output->info('No source types to process');
                }

                DB::commit();
                $stats['processed_files']++;

            } catch (\Exception $e) {
                DB::rollBack();
                $stats['error_files']++;
            }

        } catch (\Exception $e) {
            Log::channel('clark_excel')->error('SFTP connection error: '.$e->getMessage());
            throw $e;
        }

        Log::channel('clark_excel')->info('Excel files processing completed', $stats);

        return $stats;
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

        // Map Excel columns to database fields
        $data = [
            // Required fields
            'pid' => $row['pid'],
            'customer_name' => str_replace(',', '', $row['customer_name']),
            'data_source_type' => ($row['data_source_type'] ?? ''),
            'source_created_at' => $row['source_created_at'],
            'source_updated_at' => $row['source_updated_at'],
            // Optional fields (can be null)
            'closer1_id' => $row['closer1_id'],
            'sales_rep_name' => $row['sales_rep_name'],
            'sales_rep_email' => $row['sales_rep_email'],
            'customer_signoff' => \Carbon\Carbon::parse($row['customer_signoff'])->toDateString(),
            'm1_date' => $row['initial_service_date'],
            'date_cancelled' => $row['date_cancelled'],
            'initial_service_date' => $row['initial_service_date'],
            'trigger_date' => ! empty($row['trigger_date']) ? json_encode($row['trigger_date']) : null,
            'product' => $row['product'],
            'product_id' => $row['product_id'],
            'gross_account_value' => $this->cleanAmount($row['gross_account_value']),
            'initial_service_cost' => $this->cleanAmount($row['initial_service_cost']),
            'auto_pay' => $row['auto_pay'] ?? null,
            'job_status' => $row['job_status'] ?? null,
            'import_to_sales' => 0,
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
