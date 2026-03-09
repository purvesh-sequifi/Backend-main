<?php

namespace App\Console\Commands;

use App\Models\JobProgressLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CleanStuckJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup-stuck
                            {--hours=2 : Mark jobs as failed if they\'ve been processing for this many hours}
                            {--dry-run : Only show what would be cleaned up without making changes}
                            {--force : Skip confirmation and forcibly mark jobs as failed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and clean up stuck jobs that have been in processing state too long';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info("Looking for jobs stuck in 'processing' state for more than {$hours} hours...");

        // Find jobs that have been in processing state for too long
        $cutoffTime = Carbon::now()->subHours($hours);
        $stuckJobs = JobProgressLog::where('status', 'processing')
            ->where('updated_at', '<', $cutoffTime)
            ->get();

        if ($stuckJobs->isEmpty()) {
            $this->info('No stuck jobs found.');

            return Command::SUCCESS;
        }

        $this->info('Found '.$stuckJobs->count().' stuck jobs.');

        $table = [];
        foreach ($stuckJobs as $job) {
            $table[] = [
                'ID' => $job->id,
                'Job ID' => $job->job_id,
                'Job Class' => $job->job_class,
                'Queue' => $job->queue,
                'Type' => $job->type,
                'Started' => $job->started_at ? $job->started_at->format('Y-m-d H:i:s') : 'Unknown',
                'Last Updated' => $job->updated_at->format('Y-m-d H:i:s'),
                'Duration' => $job->updated_at->diffForHumans($job->started_at ?? $job->created_at),
            ];
        }

        $this->table(['ID', 'Job ID', 'Job Class', 'Queue', 'Type', 'Started', 'Last Updated', 'Duration'], $table);

        if ($dryRun) {
            $this->info('Dry run mode - no changes made. Use without --dry-run to actually clean up these jobs.');

            return Command::SUCCESS;
        }

        $force = $this->option('force');
        if (! $force && ! $this->confirm('Do you want to mark these jobs as failed?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($stuckJobs as $job) {
            $job->status = 'failed';
            $job->message = 'FORCED CLOSE: Job marked as failed automatically due to being stuck in processing state for over '.$hours.' hours';
            $job->completed_at = now();

            // Add error information
            $error = $job->error ?? [];
            $error['automatic_cleanup'] = true;
            $error['reason'] = 'Job was stuck in processing state';
            $error['cleanup_time'] = now()->toDateTimeString();

            $job->error = $error;
            $job->save();

            $count++;
        }

        $this->info("Successfully marked {$count} stuck jobs as failed.");

        // Also clean up any stuck jobs in the Laravel jobs table
        try {
            $deletedCount = DB::table('jobs')
                ->where('created_at', '<', $cutoffTime)
                ->delete();

            if ($deletedCount > 0) {
                $this->info("Also removed {$deletedCount} old job entries from the Laravel jobs table.");
            }
        } catch (\Exception $e) {
            $this->error('Error cleaning up Laravel jobs table: '.$e->getMessage());
        }

        return Command::SUCCESS;
    }
}
