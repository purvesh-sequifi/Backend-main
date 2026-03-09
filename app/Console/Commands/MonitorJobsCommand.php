<?php

namespace App\Console\Commands;

use App\Models\JobProgressLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:monitor 
                            {--queue= : Filter by queue name}
                            {--status= : Filter by status (queued, processing, completed, failed, partially_completed)}
                            {--type= : Filter by job type (e.g., FR_officeName)}
                            {--live : Watch jobs in real-time updates}
                            {--processing : Show only processing jobs (shortcut)}
                            {--detail= : Show detailed information for a specific job ID}
                            {--parlley : Shortcut to monitor only parlley queue jobs}
                            {--all : Show all jobs regardless of queue}
                            {--hide= : Hide a specific job ID from monitoring}
                            {--show-hidden : Include hidden jobs in monitoring}
                            {--clear-completed : Automatically clear completed jobs from the queue}
                            {--auto-close : Automatically mark stalled jobs as failed}
                            {--stalled-hours=3 : Hours after which a job is considered stalled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor verbose job progress in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Handle request to hide a job
        if ($this->option('hide')) {
            $this->hideJob($this->option('hide'));

            return 0;
        }

        // Clear completed jobs if requested
        if ($this->option('clear-completed')) {
            $this->clearCompletedJobs();

            return 0;
        }

        // Set status filter for processing jobs
        if ($this->option('processing')) {
            $this->input->setOption('status', 'processing');
        }

        if ($this->option('detail')) {
            $this->showJobDetail($this->option('detail'));

            return 0;
        }

        if ($this->option('live')) {
            return $this->liveMonitoring();
        }

        return $this->showJobsSummary();
    }

    /**
     * Show a summary of jobs
     */
    /**
     * Hide a job from monitoring
     */
    protected function hideJob($jobId)
    {
        $job = JobProgressLog::where('job_id', $jobId)->first();

        if (! $job) {
            $this->error("Job with ID $jobId not found");

            return;
        }

        $job->is_hidden = true;
        $job->save();

        $this->info("Job $jobId has been hidden from monitoring view.");
    }

    /**
     * Clear all completed jobs from the queue
     */
    protected function clearCompletedJobs()
    {
        $this->info('Clearing completed jobs from the queue...');

        // Get all completed job IDs
        $completedJobIds = JobProgressLog::whereIn('status', ['completed', 'partially_completed'])
            ->pluck('job_id')
            ->filter()
            ->toArray();

        if (empty($completedJobIds)) {
            $this->info('No completed jobs found to clear.');

            return;
        }

        $cleared = 0;
        $hidden = 0;

        // Process each job ID
        foreach ($completedJobIds as $jobId) {
            // 1. Try to remove from the jobs table (if it's in serialized form)
            try {
                $deleted = DB::table('jobs')
                    ->where('payload', 'like', '%"'.$jobId.'"%')
                    ->delete();
                $cleared += $deleted;
            } catch (\Exception $e) {
                // Ignore errors
            }

            // 2. Mark the job as hidden in the job_progress_logs table
            try {
                $job = JobProgressLog::where('job_id', $jobId)->first();
                if ($job && ! $job->is_hidden) {
                    $job->is_hidden = true;
                    $job->save();
                    $hidden++;
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Also clean any stuck jobs older than 2 hours
        try {
            $oldJobs = DB::table('jobs')
                ->where('created_at', '<', now()->subHours(2))
                ->delete();
            if ($oldJobs > 0) {
                $this->info("Cleared {$oldJobs} stuck jobs older than 2 hours.");
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        $this->info("Cleared {$cleared} jobs from the queue database.");
        $this->info("Hidden {$hidden} completed jobs from the monitoring view.");
    }

    /**
     * Show a summary of jobs
     */
    protected function showJobsSummary()
    {
        $query = JobProgressLog::query();

        // Filter out hidden jobs unless explicitly requested
        if (! $this->option('show-hidden')) {
            $query->where('is_hidden', false);
        }

        // Always filter to show only processing jobs unless explicitly requesting a different status
        if (! $this->option('status')) {
            $query->where('status', 'processing');
        } else {
            $query->where('status', $this->option('status'));
        }

        // Automatically mark all completed jobs as hidden for future views
        $this->autoHideCompletedJobs();

        if ($this->option('queue')) {
            $query->where('queue', $this->option('queue'));
        }

        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }

        if ($this->option('type')) {
            $query->where('type', 'like', '%'.$this->option('type').'%');
        }

        if ($this->option('parlley') && ! $this->option('all')) {
            $query->where('queue', 'parlley');
        }

        $jobs = $query->orderBy('updated_at', 'desc')->take(50)->get();

        if ($jobs->isEmpty()) {
            $this->info('No jobs found matching the specified criteria.');

            return;
        }

        // Define a time threshold for potentially stalled jobs (user configurable, default 3 hours)
        $stalledHours = $this->option('stalled-hours') ?: 3;
        $stalledThreshold = now()->subHours($stalledHours);

        // Check if we have any potentially stalled jobs
        $stalledJobs = $jobs->filter(function ($job) use ($stalledThreshold) {
            return $job->status === 'processing' && $job->updated_at < $stalledThreshold;
        });

        // Auto-close stalled jobs if requested
        if ($stalledJobs->isNotEmpty()) {
            if ($this->option('auto-close')) {
                $this->warn("\nWARNING: ".$stalledJobs->count().' stalled jobs found. Auto-closing enabled.');
                $this->closeStuckJobs($stalledJobs, $stalledHours);

                // Refresh jobs list after closing
                $jobs = $query->orderBy('updated_at', 'desc')->take(50)->get();
            } else {
                $this->warn("\nWARNING: ".$stalledJobs->count()." potentially stalled jobs found (no updates for {$stalledHours}+ hours).");
                $this->info("Run 'php artisan jobs:cleanup-stuck --dry-run' to see more details or use '--auto-close' to mark them as failed automatically.");
            }
        }

        $headers = ['ID', 'Job Class', 'Type', 'Status', 'Progress', 'Started', 'Updated', 'Duration', 'Message'];

        $rows = $jobs->map(function ($job) use ($stalledThreshold) {
            // Calculate duration since job started or was last updated
            $startTime = $job->started_at ?? $job->created_at;
            $duration = $startTime ? $startTime->diffForHumans() : 'Unknown';

            // For processing jobs, highlight if they might be stalled
            $status = $job->status;
            if ($status === 'processing' && $job->updated_at < $stalledThreshold) {
                $status = '<fg=red;options=bold>STALLED ('.$job->updated_at->diffForHumans().')</>';
            } else {
                $status = $this->formatStatus($status);
            }

            return [
                $job->job_id,
                class_basename($job->job_class),
                $job->type ?: '-',
                $status,
                $this->formatProgress($job->progress_percentage),
                $job->started_at ? $job->started_at->format('Y-m-d H:i:s') : 'N/A',
                $job->updated_at->format('Y-m-d H:i:s'),
                $duration,
                $this->truncate($job->message, 40),
            ];
        })->toArray();

        $this->table($headers, $rows);

        // Show queue statistics
        $this->displayQueueStatistics();

        return 0;
    }

    /**
     * Display queue statistics
     */
    protected function displayQueueStatistics()
    {
        $this->newLine();
        $this->components->info('📈 QUEUE STATISTICS');
        $this->components->twoColumnDetail('<fg=blue;options=bold>Status</>', '<fg=blue;options=bold>Count</>');

        // Get counts by status - INCLUDE ALL JOBS regardless of hidden status
        // Use DB::table for more direct queries to ensure accurate counts
        $queued = DB::table('job_progress_logs')->where('status', 'queued')->count();
        $processing = DB::table('job_progress_logs')->where('status', 'processing')->count();
        $completed = DB::table('job_progress_logs')->where('status', 'completed')->count();
        $failed = DB::table('job_progress_logs')->where('status', 'failed')->count();
        $partiallyCompleted = DB::table('job_progress_logs')->where('status', 'partially_completed')->count();
        $total = DB::table('job_progress_logs')->count();

        // Get counts by queue
        $queueCounts = JobProgressLog::select('queue', DB::raw('count(*) as count'))
            ->whereNotNull('queue')
            ->groupBy('queue')
            ->get()
            ->pluck('count', 'queue')
            ->toArray();

        // Get counts by job class
        $jobClassCounts = JobProgressLog::select('job_class', DB::raw('count(*) as count'))
            ->whereNotNull('job_class')
            ->groupBy('job_class')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'job_class' => class_basename($item->job_class),
                    'count' => $item->count,
                ];
            })
            ->pluck('count', 'job_class')
            ->toArray();

        // Status counts
        $this->components->twoColumnDetail('Queued', $queued);
        $this->components->twoColumnDetail('Processing', $processing);
        $this->components->twoColumnDetail('Completed', $completed);
        $this->components->twoColumnDetail('Failed', $failed);
        $this->components->twoColumnDetail('Partially Completed', $partiallyCompleted);
        $this->components->twoColumnDetail('Total Jobs', $total);

        // Queue counts
        $this->newLine();
        $this->components->info('📊 Queue Distribution:');
        foreach ($queueCounts as $queue => $count) {
            $this->components->twoColumnDetail($queue ?: 'default', $count);
        }

        // Top job classes
        $this->newLine();
        $this->components->info('🔝 Top Job Classes:');
        foreach ($jobClassCounts as $jobClass => $count) {
            $this->components->twoColumnDetail($jobClass, $count);
        }
    }

    /**
     * Close stuck jobs
     */
    protected function closeStuckJobs($stalledJobs, $hours)
    {
        $count = 0;
        foreach ($stalledJobs as $job) {
            $job->status = 'failed';
            $job->message = 'Job marked as failed automatically due to being stuck in processing state for over '.$hours.' hours';
            $job->completed_at = now();

            // Add error information
            $error = $job->error ?? [];
            if (! is_array($error)) {
                $error = [];
            }
            $error['automatic_cleanup'] = true;
            $error['reason'] = 'Job was stuck in processing state';
            $error['cleanup_time'] = now()->toDateTimeString();

            $job->error = $error;
            $job->save();

            $count++;
        }

        if ($count > 0) {
            $this->info("Successfully marked {$count} stuck jobs as failed.");
        }

        return $count;
    }

    /**
     * Show detailed information for a specific job
     */
    protected function showJobDetail($jobId)
    {
        $job = JobProgressLog::where('job_id', $jobId)->first();

        if (! $job) {
            $this->error("Job with ID $jobId not found");

            return;
        }

        $this->info("\n🔍 JOB DETAILS");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("ID:            {$job->job_id}");
        $this->info("Class:         {$job->job_class}");
        $this->info("Queue:         {$job->queue}");
        $this->info("Type:          {$job->type}");
        $this->info("Status:        {$this->formatStatus($job->status)}");
        $this->info("Progress:      {$this->formatProgress($job->progress_percentage)}");

        if ($job->total_records) {
            $this->info("Records:       {$job->processed_records} / {$job->total_records}");
        }

        $this->info('Created:       '.$job->created_at->format('Y-m-d H:i:s'));

        if ($job->started_at) {
            $this->info('Started:       '.$job->started_at->format('Y-m-d H:i:s'));
        }

        if ($job->completed_at) {
            $this->info('Completed:     '.$job->completed_at->format('Y-m-d H:i:s'));

            $duration = $job->started_at->diffInSeconds($job->completed_at);
            $this->info('Duration:      '.$this->formatDuration($duration));
        } elseif ($job->started_at) {
            $duration = $job->started_at->diffInSeconds(now());
            $this->info('Duration:      '.$this->formatDuration($duration).' (running)');
        }

        $this->info("Message:       {$job->message}");
        $this->info("Operation:     {$job->current_operation}");

        if ($job->metadata) {
            $this->info("\n📊 METADATA");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            foreach ($job->metadata as $key => $value) {
                $value = is_array($value) ? json_encode($value) : $value;
                $this->info(str_pad($key.':', 15).$value);
            }
        }

        if ($job->error) {
            $this->error("\n❌ ERROR");
            $this->error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->error("Message:  {$job->error['message']}");
            if (isset($job->error['file'])) {
                $this->error("File:     {$job->error['file']} (line {$job->error['line']})");
            }
            if (isset($job->error['trace'])) {
                $this->error('Trace:    '.substr($job->error['trace'], 0, 300).'...');
            }
        }
    }

    /**
     * Live monitoring of jobs with real-time updates
     */
    protected function liveMonitoring()
    {
        $this->info('Starting live monitoring of jobs. Press Ctrl+C to exit.');
        $this->info('Refreshing every 2 seconds with automatic scrolling...');

        while (true) {
            // Get terminal size
            $terminalSize = exec('stty size 2>/dev/null');
            $rows = $terminalSize ? explode(' ', $terminalSize)[0] : 24; // Default to 24 rows if we can't detect

            // Clear screen but keep scrollback buffer to allow scrolling
            $this->output->write("\033[2J\033[H");

            // Capture output to buffer instead of direct output
            ob_start();
            $this->showJobsSummary();
            $this->info('Last updated: '.now()->format('Y-m-d H:i:s'));
            $this->info('Press Ctrl+C to exit live monitoring.');
            $outputContent = ob_get_clean();

            // Output the content
            $this->output->write($outputContent);

            // Add extra newlines to ensure newest content is visible
            // and cursor is positioned at the bottom of the terminal
            $this->output->write("\n\n");

            // Send cursor to bottom of visible area to keep most recent entries visible
            $this->output->write("\033[{$rows};0H");

            sleep(2);
        }
    }

    /**
     * Format job status with colors
     */
    protected function formatStatus($status)
    {
        $colors = [
            'queued' => 'blue',
            'processing' => 'yellow',
            'completed' => 'green',
            'failed' => 'red',
            'partially_completed' => 'bright-yellow',
        ];

        $color = $colors[$status] ?? 'white';

        return "<fg=$color>$status</>";
    }

    /**
     * Format progress percentage
     */
    protected function formatProgress($percentage)
    {
        $percentage = $percentage ?? 0;

        if ($percentage >= 100) {
            return '<fg=green>100%</>';
        }

        if ($percentage > 80) {
            return "<fg=green>{$percentage}%</>";
        }

        if ($percentage > 50) {
            return "<fg=yellow>{$percentage}%</>";
        }

        return "<fg=blue>{$percentage}%</>";
    }

    /**
     * Automatically hide completed jobs to keep the monitoring view focused on active jobs
     * NOTE: We are no longer hiding failed jobs so they appear in the statistics
     */
    protected function autoHideCompletedJobs()
    {
        try {
            // Find all completed jobs that aren't already hidden
            // IMPORTANT: We're no longer hiding failed or partially_completed jobs
            $completedJobs = JobProgressLog::where('status', 'completed')
                ->where('is_hidden', false)
                ->whereNotNull('completed_at')
                ->get();

            // Mark all of them as hidden
            foreach ($completedJobs as $job) {
                $job->is_hidden = true;
                $job->save();
            }

            // Return the count of newly hidden jobs
            return $completedJobs->count();
        } catch (\Exception $e) {
            // Silently fail - this is just a cleanup operation
            return 0;
        }
    }

    /**
     * Format duration in readable format
     */
    protected function formatDuration($seconds)
    {
        if ($seconds < 60) {
            return "$seconds seconds";
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;

            return "$minutes min $seconds sec";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "$hours hr $minutes min";
    }

    /**
     * Format className to be more readable
     */
    protected function formatClassName($className)
    {
        return class_basename($className);
    }

    /**
     * Truncate a string to a maximum length
     */
    protected function truncate(string $string, int $length): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length).'...';
    }
}
