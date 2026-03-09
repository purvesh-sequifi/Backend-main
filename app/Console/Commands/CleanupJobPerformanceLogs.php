<?php

namespace App\Console\Commands;

use App\Models\JobPerformanceLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupJobPerformanceLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:cleanup-performance-logs 
                            {--days=30 : Number of days to keep logs}
                            {--batch-size=1000 : Number of records to delete in each batch}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old job performance logs to maintain database performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');

        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Starting cleanup of job performance logs older than {$days} days ({$cutoffDate->format('Y-m-d H:i:s')})");

        // Count total records to be deleted
        $totalCount = JobPerformanceLog::where('created_at', '<', $cutoffDate)->count();

        if ($totalCount === 0) {
            $this->info('No old performance logs found to clean up.');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} performance logs to clean up.");

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No records will be deleted.');

            // Show some statistics
            $this->showStatistics($cutoffDate);

            return Command::SUCCESS;
        }

        if (! $this->confirm("Are you sure you want to delete {$totalCount} performance log records?")) {
            $this->info('Cleanup cancelled.');

            return Command::SUCCESS;
        }

        $deletedCount = 0;
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        while (true) {
            $batch = JobPerformanceLog::where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->pluck('id');

            if ($batch->isEmpty()) {
                break;
            }

            $batchDeleted = JobPerformanceLog::whereIn('id', $batch)->delete();
            $deletedCount += $batchDeleted;

            $progressBar->advance($batchDeleted);

            // Small delay to prevent overwhelming the database
            usleep(10000); // 10ms
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully deleted {$deletedCount} performance log records.");

        // Show remaining statistics
        $this->showRemainingStatistics();

        return Command::SUCCESS;
    }

    /**
     * Show statistics for dry run
     */
    private function showStatistics($cutoffDate)
    {
        $this->info("\nStatistics for records older than {$cutoffDate->format('Y-m-d')}:");

        $stats = JobPerformanceLog::selectRaw('
                status,
                COUNT(*) as count,
                MIN(created_at) as oldest,
                MAX(created_at) as newest
            ')
            ->where('created_at', '<', $cutoffDate)
            ->groupBy('status')
            ->get();

        $this->table(
            ['Status', 'Count', 'Oldest Record', 'Newest Record'],
            $stats->map(function ($stat) {
                return [
                    $stat->status,
                    number_format($stat->count),
                    Carbon::parse($stat->oldest)->format('Y-m-d H:i:s'),
                    Carbon::parse($stat->newest)->format('Y-m-d H:i:s'),
                ];
            })->toArray()
        );

        // Show queue distribution
        $queueStats = JobPerformanceLog::selectRaw('
                queue,
                COUNT(*) as count
            ')
            ->where('created_at', '<', $cutoffDate)
            ->groupBy('queue')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($queueStats->isNotEmpty()) {
            $this->info("\nTop 10 queues by record count:");
            $this->table(
                ['Queue', 'Records'],
                $queueStats->map(function ($stat) {
                    return [$stat->queue, number_format($stat->count)];
                })->toArray()
            );
        }
    }

    /**
     * Show remaining statistics after cleanup
     */
    private function showRemainingStatistics()
    {
        $totalRemaining = JobPerformanceLog::count();
        $this->info('Total performance logs remaining: '.number_format($totalRemaining));

        if ($totalRemaining > 0) {
            $oldest = JobPerformanceLog::orderBy('created_at')->first();
            $newest = JobPerformanceLog::orderByDesc('created_at')->first();

            $this->info("Date range of remaining logs: {$oldest->created_at->format('Y-m-d')} to {$newest->created_at->format('Y-m-d')}");
        }
    }
}
