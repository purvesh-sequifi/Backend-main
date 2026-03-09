<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FieldRoutesChainedSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fieldroutes:chained-sync {from_date} {to_date} {--all} {--save}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run FieldRoutes get-subscriptions followed by sync-data in sequence';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Increase memory limit for large data processing
        ini_set('memory_limit', '512M');

        $fromDate = $this->argument('from_date');
        $toDate = $this->argument('to_date');

        // Build command arguments
        $arguments = [$fromDate, $toDate];
        if ($this->option('all')) {
            $arguments[] = '--all';
        }
        if ($this->option('save')) {
            $arguments[] = '--save';
        }

        $this->info('Starting FieldRoutes chained synchronization...');

        try {
            // Step 1: Run get-subscriptions
            $this->info('Step 1: Running fieldroutes:get-subscriptions...');
            $getSubscriptionsStart = now();

            $exitCode = Artisan::call('fieldroutes:get-subscriptions', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                '--all' => $this->option('all'),
                '--save' => $this->option('save'),
            ]);

            $getSubscriptionsEnd = now();
            $duration = $getSubscriptionsStart->diffInSeconds($getSubscriptionsEnd);

            if ($exitCode !== 0) {
                $this->error("fieldroutes:get-subscriptions failed with exit code: {$exitCode}");
                Log::error('FieldRoutes chained sync: get-subscriptions failed', [
                    'exit_code' => $exitCode,
                    'duration' => $duration,
                ]);

                return $exitCode;
            }

            $this->info("Step 1 completed successfully in {$duration} seconds");
            Log::info('FieldRoutes chained sync: get-subscriptions completed', [
                'duration' => $duration,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            // Step 2: Run sync-data
            $this->info('Step 2: Running fieldroutes:sync-data...');
            $syncDataStart = now();

            $exitCode = Artisan::call('fieldroutes:sync-data');

            $syncDataEnd = now();
            $syncDuration = $syncDataStart->diffInSeconds($syncDataEnd);

            if ($exitCode !== 0) {
                $this->error("fieldroutes:sync-data failed with exit code: {$exitCode}");
                Log::error('FieldRoutes chained sync: sync-data failed', [
                    'exit_code' => $exitCode,
                    'duration' => $syncDuration,
                ]);

                return $exitCode;
            }

            $this->info("Step 2 completed successfully in {$syncDuration} seconds");
            $totalDuration = $getSubscriptionsStart->diffInSeconds($syncDataEnd);

            $this->info('✅ FieldRoutes chained synchronization completed successfully!');
            $this->info("Total duration: {$totalDuration} seconds");

            Log::info('FieldRoutes chained sync: completed successfully', [
                'get_subscriptions_duration' => $duration,
                'sync_data_duration' => $syncDuration,
                'total_duration' => $totalDuration,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('FieldRoutes chained sync failed: '.$e->getMessage());
            Log::error('FieldRoutes chained sync: exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
