<?php

namespace App\Console\Commands;

use App\Models\BatchProcessTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BatchProcessList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:list
                            {--type= : Filter by process type}
                            {--status= : Filter by status (queued, processing, dispatched, completed, error)}
                            {--user= : Filter by user ID}
                            {--days=7 : Only show processes from the last X days}
                            {--limit=50 : Limit the number of results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List batch processes with filtering options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = BatchProcessTracker::query();

        // Apply filters
        if ($this->option('type')) {
            $query->where('process_type', $this->option('type'));
        }

        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }

        if ($this->option('user')) {
            $query->where('user_id', $this->option('user'));
        }

        // Filter by date
        $days = $this->option('days');
        $query->where('created_at', '>=', Carbon::now()->subDays($days));

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        // Apply limit
        $limit = $this->option('limit');
        $query->limit($limit);

        // Get results
        $processes = $query->get();

        if ($processes->isEmpty()) {
            $this->info('No batch processes found matching the criteria.');

            return 0;
        }

        // Format for display
        $headers = ['ID', 'Type', 'Status', 'Records', 'Processed', 'Success', 'Errors', 'Started', 'Completed'];
        $rows = [];

        foreach ($processes as $process) {
            $rows[] = [
                $process->id,
                $process->process_type,
                $process->status,
                $process->total_records,
                $process->processed_records,
                $process->success_count,
                $process->error_count,
                $process->started_at ? $process->started_at->format('Y-m-d H:i:s') : 'N/A',
                $process->completed_at ? $process->completed_at->format('Y-m-d H:i:s') : 'N/A',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
