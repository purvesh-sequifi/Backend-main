<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HorizonClearAll extends Command
{
    protected $signature = 'horizon:clear-all 
                            {--completed : Clear only completed jobs}
                            {--failed : Clear only failed jobs}';

    protected $description = 'Clear all Horizon job history (completed, failed, and metrics)';

    public function handle(): int
    {
        $clearCompleted = $this->option('completed');
        $clearFailed = $this->option('failed');
        $clearAll = !$clearCompleted && !$clearFailed; // If no options, clear all

        if (!$this->confirm('This will permanently delete Horizon job history. Continue?', true)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->info('🧹 Cleaning Horizon data...');

        try {
            $horizonPrefix = config('horizon.prefix', 'horizon');

            // Step 1: Clear Redis using redis-cli (bypasses Laravel prefix issues)
            if ($clearAll || $clearCompleted) {
                $this->info('Clearing completed jobs from Redis...');
                shell_exec("redis-cli -n 0 DEL {$horizonPrefix}:completed_jobs 2>&1");
                shell_exec("redis-cli -n 0 DEL {$horizonPrefix}:recent_jobs 2>&1");
                
                // Delete individual job UUID keys
                $deleteCmd = "redis-cli -n 0 --scan --pattern '{$horizonPrefix}:*-*-*-*-*' | xargs -r redis-cli -n 0 DEL 2>&1";
                shell_exec($deleteCmd);
            }

            if ($clearAll || $clearFailed) {
                $this->info('Clearing failed jobs from Redis...');
                shell_exec("redis-cli -n 0 DEL {$horizonPrefix}:failed_jobs 2>&1");
                shell_exec("redis-cli -n 0 DEL {$horizonPrefix}:recent_failed_jobs 2>&1");
                
                $this->info('Clearing failed jobs from database...');
                DB::table('failed_job_details')->delete();
                DB::table('failed_jobs')->delete();
            }

            if ($clearAll || $clearCompleted) {
                $this->info('Clearing job performance logs...');
                $deleted = DB::table('job_performance_logs')->delete();
                $this->info("Deleted {$deleted} performance log records.");
            }

            // Step 2: Restart services
            $this->info('Restarting Horizon...');
            shell_exec('sudo supervisorctl restart sequifi-horizon 2>&1');
            sleep(2);
            
            $this->info('Restarting Octane...');
            shell_exec('sudo supervisorctl restart sequifi-octane 2>&1');
            sleep(2);

            // Step 3: Create fresh snapshot
            $this->info('Creating fresh snapshot...');
            $this->call('horizon:snapshot');

            $this->newLine();
            $this->info('✅ Horizon cleanup completed successfully!');
            $this->info('🔄 Hard refresh your browser (Ctrl+Shift+R) to see changes.');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Error during cleanup: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
