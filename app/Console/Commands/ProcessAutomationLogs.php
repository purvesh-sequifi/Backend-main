<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AutomationActionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessAutomationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:process-logs {--batch-size=150 : Number of logs to process in this batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending automation logs in batch and ensure email delivery';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');

        // Get pending logs that need processing
        $pendingLogs = AutomationActionLog::query()
            ->whereNull('deleted_at')
            ->where('email_sent', 0)
            ->where('status', 1) // Ready to be processed
            ->whereNotNull('email') // Has email data
            ->limit($batchSize)
            ->get();

        if ($pendingLogs->isEmpty()) {
            return Command::SUCCESS;
        }

        $this->info("Processing {$pendingLogs->count()} automation logs...");

        $processed = 0;
        $failed = 0;

        foreach ($pendingLogs as $log) {
            try {
                // Use the existing ProcessAutomationLog command logic
                Artisan::call('automation:process-log', [
                    'log_id' => $log->id,
                ]);

                $processed++;

                if ($processed % 10 === 0) {
                    $this->info("Processed {$processed} logs...");
                }
            } catch (\Throwable $th) {
                $failed++;
                Log::error('ProcessAutomationLogs: Failed to process log', [
                    'log_id' => $log->id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        // Only log when there are failures
        if ($failed > 0) {
            Log::warning('ProcessAutomationLogs: Batch completed with failures', [
                'total' => $pendingLogs->count(),
                'processed' => $processed,
                'failed' => $failed,
            ]);
        }

        $this->info("Completed: {$processed} processed, {$failed} failed");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
