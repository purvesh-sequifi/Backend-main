<?php

namespace App\Console\Commands;

use App\Services\ClickHouseConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverMissingClickHouseRecords extends Command
{
    protected $signature = 'clickhouse:recover-missing-records 
                            {--batch-size=1000 : Number of records to process per batch}
                            {--max-retries=5 : Maximum retries per batch}
                            {--dry-run : Show what would be recovered without actually doing it}
                            {--sample-size=10 : Number of sample records to show in dry-run mode}';

    protected $description = 'Identify and recover missing records between MySQL and ClickHouse activity_log tables';

    private $totalRecovered = 0;

    private $totalFailed = 0;

    public function handle(): int
    {
        try {
            $this->info('🔍 Starting recovery of missing ClickHouse records...');

            // Get ClickHouse client
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                $this->error('Failed to establish ClickHouse connection.');

                return 1;
            }

            // Step 1: Identify missing records
            $missingRecords = $this->identifyMissingRecords($client);

            if (empty($missingRecords)) {
                $this->info('✅ No missing records found! All data is synchronized.');

                return 0;
            }

            $totalMissing = count($missingRecords);
            $this->warn("📊 Found {$totalMissing} missing records in ClickHouse");

            if ($this->option('dry-run')) {
                $this->info('🔍 DRY RUN MODE - Showing what would be recovered:');
                $this->displayMissingRecordsSample($missingRecords);

                return 0;
            }

            // Step 2: Recover missing records in batches
            $batchSize = (int) $this->option('batch-size');
            $maxRetries = (int) $this->option('max-retries');

            $this->info("🚀 Starting recovery with batch size: {$batchSize}, max retries: {$maxRetries}");

            $batches = array_chunk($missingRecords, $batchSize);
            $totalBatches = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                $batchNumber = $batchIndex + 1;
                $this->info("Processing batch {$batchNumber}/{$totalBatches} (".count($batch).' records)');

                $result = $this->recoverBatchWithRetries($client, $batch, $maxRetries, $batchNumber);

                if ($result['success']) {
                    $this->totalRecovered += $result['processed'];
                    $progress = round(($batchNumber / $totalBatches) * 100, 1);
                    $this->info("✅ Batch {$batchNumber} completed: {$result['processed']} records recovered ({$progress}% total)");
                } else {
                    $this->totalFailed += count($batch);
                    $this->error("❌ Batch {$batchNumber} failed after {$maxRetries} retries");
                }

                // Small delay to prevent overwhelming ClickHouse
                usleep(100000); // 0.1 second
            }

            // Step 3: Final verification
            $this->info('🔍 Performing final verification...');
            $finalMissing = $this->identifyMissingRecords($client);

            $this->info('📈 Recovery Summary:');
            $this->info('   → Records recovered: '.number_format($this->totalRecovered));
            $this->info('   → Records failed: '.number_format($this->totalFailed));
            $this->info('   → Records still missing: '.count($finalMissing));

            if (empty($finalMissing)) {
                $this->info('🎉 SUCCESS! All records have been recovered.');
                Log::info('[ClickHouse Recovery] All missing records successfully recovered', [
                    'recovered' => $this->totalRecovered,
                    'failed' => $this->totalFailed,
                ]);

                return 0;
            } else {
                $this->warn('⚠️  Some records are still missing. You may need to investigate data issues.');
                Log::warning('[ClickHouse Recovery] Some records still missing after recovery', [
                    'recovered' => $this->totalRecovered,
                    'failed' => $this->totalFailed,
                    'still_missing' => count($finalMissing),
                ]);

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('Recovery failed: '.$e->getMessage());
            Log::error('[ClickHouse Recovery] Recovery failed: '.$e->getMessage(), ['exception' => $e]);

            return 1;
        }
    }

    private function identifyMissingRecords($client): array
    {
        $this->info('🔍 Identifying missing records...');

        // Get total counts first for progress tracking
        $mysqlTotal = DB::table('activity_log')->count();
        $clickhouseTotal = (int) $client->select('SELECT count() as cnt FROM activity_log')->rows()[0]['cnt'];

        $this->info('   → MySQL total: '.number_format($mysqlTotal));
        $this->info('   → ClickHouse total: '.number_format($clickhouseTotal));
        $this->info('   → Expected missing: '.number_format($mysqlTotal - $clickhouseTotal));

        // Use a more efficient approach for large datasets
        // Get all ClickHouse IDs in chunks and build a hash set
        $clickhouseIds = [];
        $offset = 0;
        $batchSize = 50000;

        $this->info('🔍 Loading ClickHouse IDs...');
        while (true) {
            $result = $client->select("SELECT id FROM activity_log ORDER BY id LIMIT {$batchSize} OFFSET {$offset}");
            $batch = array_column($result->rows(), 'id');

            if (empty($batch)) {
                break;
            }

            foreach ($batch as $id) {
                $clickhouseIds[$id] = true; // Use hash for O(1) lookup
            }

            $offset += $batchSize;

            if ($offset % 100000 === 0) {
                $this->info('   → Loaded '.number_format(count($clickhouseIds)).' ClickHouse IDs...');
            }
        }

        $this->info('🔍 Comparing with MySQL records...');

        // Find missing records by checking MySQL IDs against ClickHouse hash
        $missingIds = [];
        $offset = 0;
        $batchSize = 10000;
        $checked = 0;

        while (true) {
            $mysqlIds = DB::table('activity_log')
                ->orderBy('id')
                ->offset($offset)
                ->limit($batchSize)
                ->pluck('id')
                ->toArray();

            if (empty($mysqlIds)) {
                break;
            }

            foreach ($mysqlIds as $id) {
                if (! isset($clickhouseIds[$id])) {
                    $missingIds[] = $id;
                }
                $checked++;
            }

            $offset += $batchSize;

            if ($checked % 50000 === 0) {
                $this->info('   → Checked '.number_format($checked).' MySQL records, found '.count($missingIds).' missing...');
            }
        }

        $this->info('✅ Analysis complete: Found '.count($missingIds).' missing records');

        return $missingIds;
    }

    private function displayMissingRecordsSample(array $missingIds): void
    {
        $sampleSize = min((int) $this->option('sample-size'), count($missingIds));
        $sample = array_slice($missingIds, 0, $sampleSize);

        $this->info('Sample of missing record IDs:');
        foreach ($sample as $id) {
            $record = DB::table('activity_log')->where('id', $id)->first();
            if ($record) {
                $this->line("   → ID: {$id} | Created: {$record->created_at} | Event: {$record->event}");
            } else {
                $this->line("   → ID: {$id} | Record not found in MySQL");
            }
        }

        if (count($missingIds) > $sampleSize) {
            $remaining = count($missingIds) - $sampleSize;
            $this->info('   ... and '.number_format($remaining).' more records');
        }

        // Show some statistics
        $this->info("\n📊 Missing Records Analysis:");

        // Get date range of missing records
        if (! empty($missingIds)) {
            $sampleIds = array_slice($missingIds, 0, 1000); // Sample for analysis
            $dateStats = DB::table('activity_log')
                ->whereIn('id', $sampleIds)
                ->selectRaw('MIN(created_at) as earliest, MAX(created_at) as latest, COUNT(*) as sample_count')
                ->first();

            if ($dateStats) {
                $this->info("   → Date range (sample): {$dateStats->earliest} to {$dateStats->latest}");
                $this->info("   → Sample size: {$dateStats->sample_count}");
            }
        }
    }

    private function recoverBatchWithRetries($client, array $missingIds, int $maxRetries, int $batchNumber): array
    {
        // Get the actual record data for these IDs
        $batchData = DB::table('activity_log')
            ->whereIn('id', $missingIds)
            ->get()
            ->map(function ($row) {
                return [
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
            })
            ->toArray();

        if (empty($batchData)) {
            $this->warn("   → No data found for batch {$batchNumber} IDs in MySQL");

            return ['success' => false, 'processed' => 0, 'attempts' => 0, 'error' => 'No data found'];
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                // Verify connection
                if (! $this->verifyConnection($client)) {
                    throw new \Exception('ClickHouse connection lost');
                }

                // Insert batch to ClickHouse
                $client->insert('activity_log', $batchData, [
                    'id', 'log_name', 'description', 'subject_type', 'event', 'subject_id',
                    'causer_type', 'causer_id', 'properties', 'batch_uuid', 'created_at', 'updated_at',
                ]);

                Log::info("[ClickHouse Recovery] Batch {$batchNumber} recovered successfully on attempt {$attempt}");

                return [
                    'success' => true,
                    'processed' => count($batchData),
                    'attempts' => $attempt,
                ];

            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning("[ClickHouse Recovery] Batch {$batchNumber} attempt {$attempt} failed: ".$e->getMessage());

                if ($attempt < $maxRetries) {
                    $sleepTime = min(pow(2, $attempt), 30);
                    $this->warn("   → Retrying in {$sleepTime}s (attempt {$attempt}/{$maxRetries})");
                    sleep($sleepTime);

                    // Re-establish connection
                    $client = ClickHouseConnectionService::getClient(120, true);
                    if (! $client) {
                        throw new \Exception('Failed to re-establish ClickHouse connection');
                    }
                }
            }
        }

        Log::error("[ClickHouse Recovery] Batch {$batchNumber} failed after {$maxRetries} attempts", [
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
            Log::warning('[ClickHouse Recovery] Connection verification failed: '.$e->getMessage());

            return false;
        }
    }
}
