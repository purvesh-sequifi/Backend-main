<?php

namespace App\Console\Commands;

use App\Http\Controllers\QueueDashboardController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestQueueDetection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:test-detection 
                            {--mode= : Test specific detection mode (worker_only, active_jobs_only, all_discovered)}
                            {--show-workers : Show detailed worker information}
                            {--show-jobs : Show current job counts}';

    /**
     * The console command description.
     */
    protected $description = 'Test and debug queue detection modes for the dashboard';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('🔍 Queue Detection Testing Tool');
        $this->newLine();

        $currentMode = config('queue-dashboard.queue_detection_mode', 'worker_only');
        $testMode = $this->option('mode');

        if ($testMode) {
            $this->testSpecificMode($testMode);
        } else {
            $this->testAllModes();
        }

        $this->newLine();
        $this->info("📋 Current configured mode: <comment>{$currentMode}</comment>");
        $this->info('💡 To change mode, edit config/queue-dashboard.php or set QUEUE_DASHBOARD_DETECTION_MODE');

        if ($this->option('show-workers')) {
            $this->showWorkerDetails();
        }

        if ($this->option('show-jobs')) {
            $this->showJobDetails();
        }
    }

    private function testAllModes()
    {
        $this->info('Testing all detection modes:');
        $this->newLine();

        $modes = ['worker_only', 'active_jobs_only', 'all_discovered'];

        foreach ($modes as $mode) {
            $this->testSpecificMode($mode);
            $this->newLine();
        }
    }

    private function testSpecificMode($mode)
    {
        $this->comment("🎯 Mode: {$mode}");

        // Temporarily change config for testing
        $originalMode = config('queue-dashboard.queue_detection_mode');
        config(['queue-dashboard.queue_detection_mode' => $mode]);

        try {
            $controller = new QueueDashboardController;
            $reflection = new \ReflectionClass($controller);

            switch ($mode) {
                case 'worker_only':
                    $method = $reflection->getMethod('getWorkerOnlyQueues');
                    break;
                case 'active_jobs_only':
                    $method = $reflection->getMethod('getActiveJobsOnlyQueues');
                    break;
                case 'all_discovered':
                    $method = $reflection->getMethod('getAllDiscoveredQueues');
                    break;
                default:
                    $this->error("Unknown mode: {$mode}");

                    return;
            }

            $method->setAccessible(true);
            $queues = $method->invoke($controller);

            if ($queues->isEmpty()) {
                $this->warn('  ⚠️  No queues detected');
            } else {
                $this->info("  ✅ Detected {$queues->count()} queue(s):");
                foreach ($queues as $queue) {
                    $this->line("     • {$queue}");
                }
            }

        } catch (\Exception $e) {
            $this->error('  ❌ Error testing mode: '.$e->getMessage());
        } finally {
            // Restore original config
            config(['queue-dashboard.queue_detection_mode' => $originalMode]);
        }
    }

    private function showWorkerDetails()
    {
        $this->newLine();
        $this->comment('👷 Worker Details:');

        try {
            $controller = new QueueDashboardController;
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('getRunningWorkerProcesses');
            $method->setAccessible(true);
            $workers = $method->invoke($controller);

            if (empty($workers)) {
                $this->warn('  No running workers detected');
                $this->info('  💡 Make sure queue workers are running: php artisan queue:work');
            } else {
                $this->info('  Found '.count($workers).' running worker(s):');
                foreach ($workers as $worker) {
                    $queue = $worker['queue'] ?? 'unknown';
                    $pid = $worker['pid'] ?? 'unknown';
                    $status = $worker['status'] ?? 'unknown';
                    $this->line("     • Queue: {$queue} | PID: {$pid} | Status: {$status}");
                }
            }
        } catch (\Exception $e) {
            $this->error('  Error getting worker details: '.$e->getMessage());
        }
    }

    private function showJobDetails()
    {
        $this->newLine();
        $this->comment('📊 Job Details:');

        try {
            // Current pending jobs by queue (using default connection)
            $pendingJobs = DB::connection('mysql')->table('jobs')
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->get();

            if ($pendingJobs->isEmpty()) {
                $this->info('  No pending jobs in queue');
            } else {
                $this->info('  Pending jobs by queue:');
                foreach ($pendingJobs as $job) {
                    $this->line("     • {$job->queue}: {$job->count} jobs");
                }
            }

            // Failed jobs in last 24h by queue (using default connection)
            $failedJobs = DB::connection('mysql')->table('failed_jobs')
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->where('failed_at', '>=', now()->subDay())
                ->groupBy('queue')
                ->get();

            if ($failedJobs->isNotEmpty()) {
                $this->newLine();
                $this->info('  Failed jobs (last 24h) by queue:');
                foreach ($failedJobs as $job) {
                    $this->line("     • {$job->queue}: {$job->count} failed");
                }
            }

        } catch (\Exception $e) {
            $this->error('  Error getting job details: '.$e->getMessage());
        }
    }
}
