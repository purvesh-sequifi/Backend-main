<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUserBigQueryBatchJob;
use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUserOnBigQueryHourly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usersynconbigquery:hourly {--chunk-size=50 : Number of users to process in each batch} {--max-batches=0 : Maximum number of batches to process, 0 for all} {--timeout=1800 : Command timeout in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync users to BigQuery in parallel batches for better performance';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Set command timeout
            $timeout = (int) $this->option('timeout');
            set_time_limit($timeout);

            // Check if BigQuery integration is enabled
            $bigQueryService = new BigQueryService;
            if (! $bigQueryService->isEnabled()) {
                $this->warn('BigQuery integration is disabled. Skipping user sync job.');

                return Command::SUCCESS;
            }

            $chunkSize = (int) $this->option('chunk-size');
            $maxBatches = (int) $this->option('max-batches');

            $startTime = now();

            // Use standard User query without any active filtering
            $query = User::query();

            // Log total user count
            $this->line('Total users: '.User::count());

            // Get total count for progress reporting
            $totalUsers = $query->count();
            $this->info("Starting BigQuery sync for {$totalUsers} users in batches of {$chunkSize}");

            // Calculate total number of batches
            $totalBatches = ceil($totalUsers / $chunkSize);

            // Process in batches
            $batchCount = 0;
            $processedUsers = 0;

            $query->select('id')->chunkById($chunkSize, function ($users) use (&$batchCount, &$processedUsers, $maxBatches, $totalUsers, $totalBatches) {
                // Extract user IDs for this batch
                $userIds = $users->pluck('id')->toArray();
                $batchCount++;
                $processedUsers += count($userIds);

                // Skip if we've reached max batches
                if ($maxBatches > 0 && $batchCount > $maxBatches) {
                    $this->info("Reached maximum batch limit of {$maxBatches}. Stopping.");

                    return false;
                }

                // Log batch dispatch
                $this->line("Dispatching batch {$batchCount} with ".count($userIds)." users. Progress: {$processedUsers}/{$totalUsers}");
                Log::channel('bigquery')->info('Dispatching user batch', [
                    'batch_number' => $batchCount,
                    'batch_size' => count($userIds),
                    'total_users' => $totalUsers,
                    'processed_users' => $processedUsers,
                ]);

                // Dispatch job for this batch with a small delay to prevent queue overload
                ProcessUserBigQueryBatchJob::dispatch($userIds, $batchCount, $totalBatches)
                    ->delay(now()->addSeconds($batchCount * 2 % 30)); // Stagger jobs to prevent queue overload

                return true;
            });

            $duration = now()->diffInSeconds($startTime);
            $this->info("Finished dispatching {$batchCount} batch jobs for {$processedUsers} users in {$duration} seconds");
            $this->line("Jobs are now processing in the queue. Run 'php artisan queue:work' to process them if not already running.");

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            // Log the error
            Log::error('BigQuery user sync failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to let Laravel handle it
            throw $e;
        }
    }
}
