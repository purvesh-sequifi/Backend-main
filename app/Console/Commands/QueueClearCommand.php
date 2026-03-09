<?php

namespace App\Console\Commands;

use App\Models\JobProgressLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:clear
                            {queue? : The name of the queue to clear (default: all)}
                            {--completed : Only clear jobs that are marked as completed}
                            {--failed : Only clear jobs that are marked as failed}
                            {--all : Clear all jobs from the queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear stuck or completed jobs from the queue';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queue = $this->argument('queue');
        $onlyCompleted = $this->option('completed');
        $onlyFailed = $this->option('failed');
        $clearAll = $this->option('all');

        if (! $queue && ! $clearAll) {
            $this->info('Please specify a queue name or use the --all option to clear all queues');

            return 1;
        }

        $this->info('Clearing jobs from queue'.($queue ? " '$queue'" : 's'));

        // Build query
        $query = DB::table('jobs');

        if ($queue) {
            $query->where('queue', $queue);
        }

        // If we're only clearing completed jobs
        if ($onlyCompleted) {
            // Get job IDs that are completed
            $completedJobIds = JobProgressLog::whereIn('status', ['completed', 'partially_completed'])
                ->pluck('job_id')
                ->filter() // Remove null values
                ->toArray();

            if (empty($completedJobIds)) {
                $this->info('No completed jobs found to clear');

                return 0;
            }

            // We need to find jobs in the jobs table that reference our job IDs
            // This requires a different approach since job_id doesn't directly map to a column
            $jobIds = $completedJobIds; // Store for later use

            // Instead of using the query builder, we'll handle this manually
            $deletedCount = 0;
            foreach ($jobIds as $jobId) {
                $deleteResult = DB::table('jobs')
                    ->where('queue', $queue)
                    ->where('payload', 'like', '%"'.$jobId.'"%')
                    ->delete();

                $deletedCount += $deleteResult;
            }

            $this->info("Cleared {$deletedCount} completed jobs from the queue");

            return 0; // Exit early since we've handled the deletion manually
        }

        // Count jobs to be deleted
        $count = $query->count();

        if ($count === 0) {
            $this->info('No jobs found matching your criteria');

            return 0;
        }

        // Confirm before deletion unless --all is specified
        if (! $clearAll && ! $this->confirm("Are you sure you want to delete $count job(s)?")) {
            $this->info('Operation cancelled');

            return 0;
        }

        // Delete the jobs
        $deleted = $query->delete();

        $this->info("Successfully cleared $deleted job(s) from the queue");

        // Also clear failed jobs if requested
        if ($onlyFailed || $clearAll) {
            $failedQuery = DB::table('failed_jobs');

            if ($queue) {
                $failedQuery->where('queue', $queue);
            }

            $failedCount = $failedQuery->count();

            if ($failedCount > 0) {
                $failedDeleted = $failedQuery->delete();
                $this->info("Successfully cleared $failedDeleted failed job(s)");
            } else {
                $this->info('No failed jobs to clear');
            }
        }

        return 0;
    }
}
