<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ShowBigQueryDiagnosticResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bigquery:diagnose-results
                            {--clear : Clear cached diagnostic results}
                            {--detail=10 : Number of missing records to show in detail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the results of the parallel BigQuery diagnostic process';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clearCache = $this->option('clear');
        $detailLimit = (int) $this->option('detail');

        if ($clearCache) {
            Cache::forget('bigquery_diagnostic_summary');
            $this->info('Diagnostic results cache cleared.');

            return 0;
        }

        $this->info('Retrieving BigQuery diagnostic results...');

        // Check if there are still jobs in the queue
        $pendingJobs = $this->getPendingJobs('ProcessBigQueryDiagnosticBatchJob');

        if ($pendingJobs > 0) {
            $this->warn("There are still {$pendingJobs} diagnostic jobs pending in the queue.");
            $this->info('The results shown may be incomplete until all jobs finish processing.');
        }

        // Collect all batch results from cache
        $batchResults = [];
        $totalBatches = 0;
        $totalUsers = 0;
        $totalPresent = 0;
        $totalMissing = 0;
        $totalFixed = 0;
        $totalErrors = 0;
        $missingUsers = [];

        for ($i = 1; $i <= 10000; $i++) {
            $cacheKey = "bigquery_diagnostic_batch_{$i}";
            $batchData = Cache::get($cacheKey);

            if (! $batchData) {
                if ($i === 1) {
                    $this->error('No diagnostic results found in cache. Run the diagnostic command first with --parallel option.');

                    return 1;
                }
                // No more batches in cache
                break;
            }

            $totalBatches++;
            $totalUsers += $batchData['checked_count'] ?? 0;
            $totalPresent += $batchData['present_count'] ?? 0;
            $totalMissing += $batchData['missing_count'] ?? 0;
            $totalFixed += $batchData['fixed_count'] ?? 0;
            $totalErrors += $batchData['error_count'] ?? 0;

            // Collect missing user details
            if (isset($batchData['missing_users']) && is_array($batchData['missing_users'])) {
                $missingUsers = array_merge($missingUsers, $batchData['missing_users']);
            }
        }

        // Store summary in cache for later retrieval
        $summary = [
            'total_batches' => $totalBatches,
            'total_users' => $totalUsers,
            'present' => $totalPresent,
            'missing' => $totalMissing,
            'fixed' => $totalFixed,
            'errors' => $totalErrors,
            'timestamp' => now()->toDateTimeString(),
        ];
        Cache::put('bigquery_diagnostic_summary', $summary, now()->addDay());

        // Display summary
        $this->info("\nBigQuery Diagnostic Results Summary:");
        $this->info("- Total batches processed: {$totalBatches}");
        $this->info("- Total users checked: {$totalUsers}");
        $this->info("- Present in BigQuery: {$totalPresent}");
        $this->info("- Missing from BigQuery: {$totalMissing}");

        if ($totalFixed > 0) {
            $this->info("- Fixed records: {$totalFixed}");
        }

        if ($totalErrors > 0) {
            $this->error("- Errors encountered: {$totalErrors}");
        }

        $syncRate = $totalUsers > 0 ? round(($totalPresent / $totalUsers) * 100, 2) : 0;
        $this->info("- Sync rate: {$syncRate}%");

        // Show missing users in detail
        if ($totalMissing > 0 && $detailLimit > 0) {
            $this->info("\nDetailed view of up to {$detailLimit} missing users:");

            $displayCount = min(count($missingUsers), $detailLimit);

            for ($i = 0; $i < $displayCount; $i++) {
                $user = $missingUsers[$i];
                $this->warn("- User ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}");
            }

            if (count($missingUsers) > $detailLimit) {
                $this->line('... and '.(count($missingUsers) - $detailLimit).' more missing users');
            }
        }

        // Show recommendations
        if ($totalMissing > 0) {
            $this->info("\nRecommendations:");
            $this->line("- Run 'php artisan bigquery:diagnose --parallel --fix' to automatically fix missing records");
            $this->line('- Check the BigQuery API credentials and permissions');
            $this->line("- Verify that the 'usersynconbigquery:hourly' command is running successfully");
        } elseif ($totalUsers > 0) {
            $this->info("\n✅ All checked records are properly synchronized with BigQuery!");
        }

        return 0;
    }

    /**
     * Get the number of pending jobs of a specific type in the queue
     */
    protected function getPendingJobs(string $jobClass): int
    {
        try {
            // Only works with database queue
            return DB::table('jobs')
                ->where('payload', 'like', '%'.$jobClass.'%')
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
