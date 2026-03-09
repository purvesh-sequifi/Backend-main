<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeFieldRoutesDataCommand extends Command
{
    protected $signature = 'fieldroutes:analyze-data';

    protected $description = 'Analyze FieldRoutes data to show processing statistics';

    public function handle(): int
    {
        $this->info('Analyzing FieldRoutes Data...');

        // Get total counts
        $rawCount = DB::table('FieldRoutes_Raw_Data')->count();
        $legacyCount = DB::table('legacy_api_raw_data_histories')->count();
        $failedCount = DB::table('field_routes_failed_records')->count();

        $this->info("\nTotal Records:");
        $this->info("FieldRoutes_Raw_Data: $rawCount");
        $this->info("legacy_api_raw_data_histories: $legacyCount");
        $this->info("field_routes_failed_records: $failedCount");

        // Get failed records by type
        $failedByType = DB::table('field_routes_failed_records')
            ->select('failure_type', DB::raw('count(*) as count'))
            ->groupBy('failure_type')
            ->get();

        if ($failedByType->count() > 0) {
            $this->info("\nFailed Records by Type:");
            foreach ($failedByType as $type) {
                $this->info("{$type->failure_type}: {$type->count}");
            }
        }

        // Check for records that might be filtered out by import_to_sales logic
        $filteredByDate = DB::table('FieldRoutes_Raw_Data')
            ->whereDate('date_added', '<=', '2024-12-31')
            ->count();

        $this->info("\nPotential Date Filtering:");
        $this->info("Records with date_added <= 2024-12-31: $filteredByDate");

        // Check sync timestamps
        $this->info("\nSync Timing Analysis:");
        $lastSyncRecord = DB::table('FieldRoutes_Raw_Data')
            ->whereNotNull('last_synced_at')
            ->orderBy('last_synced_at', 'desc')
            ->first();

        if ($lastSyncRecord) {
            $this->info('Last sync attempt: '.$lastSyncRecord->last_synced_at);
        }

        // Analyze records in Raw Data that aren't in legacy table
        $rawRecords = DB::table('FieldRoutes_Raw_Data')
            ->select('id', 'customer_id', 'initial_status_text', 'date_added', 'sync_status', 'last_synced_at', 'sync_notes')
            ->get();

        $processedIds = DB::table('legacy_api_raw_data_histories')
            ->pluck('pid')
            ->toArray();

        $missingRecords = [];
        foreach ($rawRecords as $record) {
            if (! in_array($record->id, $processedIds)) {
                $missingRecords[] = $record;
            }
        }

        $this->info("\nUnprocessed Records Analysis:");
        $this->info('Total unprocessed records: '.count($missingRecords));

        // Group by status
        $statusCounts = [];
        foreach ($missingRecords as $record) {
            $status = $record->initial_status_text ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        $this->info("\nUnprocessed Records by Status:");
        foreach ($statusCounts as $status => $count) {
            $this->info("$status: $count");
        }

        // Check for potential data integrity issues
        $this->info("\nPotential Data Integrity Issues:");

        // Check for records marked as completed but not in legacy table
        $completedButMissing = DB::table('FieldRoutes_Raw_Data')
            ->where('sync_status', 'completed')
            ->whereNotIn('id', $processedIds)
            ->count();

        $this->info("Records marked as completed but not in legacy table: $completedButMissing");

        // Check for records with sync errors but no failed record
        $errorRecords = DB::table('FieldRoutes_Raw_Data')
            ->whereNotNull('sync_notes')
            ->where('sync_notes', '!=', '')
            ->count();

        $this->info("Records with sync notes but no failed record: $errorRecords");

        // Sample some records with sync notes
        $this->info("\nSample Records with Sync Notes:");
        $sampleErrorRecords = DB::table('FieldRoutes_Raw_Data')
            ->whereNotNull('sync_notes')
            ->where('sync_notes', '!=', '')
            ->limit(5)
            ->get();

        foreach ($sampleErrorRecords as $record) {
            $this->info("\nID: {$record->id}");
            $this->info("Status: {$record->sync_status}");
            $this->info("Notes: {$record->sync_notes}");
            $this->info('------------------------');
        }

        return Command::SUCCESS;
    }
}
