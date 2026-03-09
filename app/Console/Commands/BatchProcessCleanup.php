<?php

namespace App\Console\Commands;

use App\Models\BatchProcessTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BatchProcessCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:cleanup
                            {--days=30 : Delete batch processes older than this many days}
                            {--status= : Only delete processes with specific status (queued, processing, dispatched, completed, error)}
                            {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old batch process tracking records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->option('days');
        $status = $this->option('status');
        $force = $this->option('force');

        $query = BatchProcessTracker::where('created_at', '<', Carbon::now()->subDays($days));

        if ($status) {
            $query->where('status', $status);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No batch processes found to clean up.');

            return 0;
        }

        $this->info("Found {$count} batch processes that are older than {$days} days".($status ? " with status '{$status}'" : '').'.');

        if (! $force && ! $this->confirm('Do you want to delete these records?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            $deleted = $query->delete();
            $this->info("Successfully deleted {$deleted} batch process records.");

            Log::info("BatchProcessCleanup: Deleted {$deleted} batch process records older than {$days} days".($status ? " with status '{$status}'" : '').'.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error cleaning up batch processes: '.$e->getMessage());

            Log::error('BatchProcessCleanup: Error cleaning up batch processes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
