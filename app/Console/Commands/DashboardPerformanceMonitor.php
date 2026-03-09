<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardPerformanceMonitor extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'dashboard:monitor-performance';

    /**
     * The console command description.
     */
    protected $description = 'Monitor dashboard API performance and alert on slowdowns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Dashboard Performance Monitoring...');

        // 1. Check database query performance
        $this->checkDatabasePerformance();

        // 2. Check index utilization
        $this->checkIndexUtilization();

        // 3. Monitor memory usage
        $this->checkMemoryUsage();

        // 4. Test dashboard API response time
        $this->testDashboardResponseTime();

        $this->info('Performance monitoring completed!');
    }

    /**
     * Check database performance metrics
     */
    private function checkDatabasePerformance()
    {
        $slowQueries = DB::select("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        $totalQueries = DB::select("SHOW GLOBAL STATUS LIKE 'Questions'");

        if (! empty($slowQueries) && ! empty($totalQueries)) {
            $slowQueryRate = ($slowQueries[0]->Value / $totalQueries[0]->Value) * 100;

            if ($slowQueryRate > 5) { // Alert if >5% slow queries
                Log::warning("High slow query rate detected: {$slowQueryRate}%");
                $this->error("WARNING: Slow query rate is {$slowQueryRate}%");
            } else {
                $this->info("Database performance OK - Slow query rate: {$slowQueryRate}%");
            }
        }
    }

    /**
     * Check if critical indexes are being used
     */
    private function checkIndexUtilization()
    {
        $criticalTables = [
            'user_override_history',
            'user_redlines',
            'user_commission_history',
            'sale_master_process',
            'approvals_and_requests',
        ];

        foreach ($criticalTables as $table) {
            try {
                $indexStats = DB::select("
                    SELECT 
                        table_name,
                        index_name,
                        cardinality
                    FROM information_schema.statistics 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                    AND index_name LIKE '%action_status%'
                ", [$table]);

                if (empty($indexStats)) {
                    Log::error("Missing critical index on table: {$table}");
                    $this->error("CRITICAL: Missing action_item_status index on {$table}");
                } else {
                    $this->info("Index OK on {$table}");
                }
            } catch (\Exception $e) {
                Log::error("Error checking indexes on {$table}: ".$e->getMessage());
            }
        }
    }

    /**
     * Monitor memory usage patterns
     */
    private function checkMemoryUsage()
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024; // MB

        if ($memoryUsage > 256) { // Alert if using >256MB
            Log::warning("High memory usage detected: {$memoryUsage}MB");
            $this->error("WARNING: High memory usage - {$memoryUsage}MB");
        } else {
            $this->info("Memory usage OK - {$memoryUsage}MB (peak: {$peakMemory}MB)");
        }
    }

    /**
     * Test actual dashboard API response time
     */
    private function testDashboardResponseTime()
    {
        $startTime = microtime(true);

        try {
            // Simulate dashboard API call (you'll need to adjust this)
            $response = app(\App\Http\Controllers\API\Dashboard\DashboardController::class)
                ->dashboardItemSection(request());

            $responseTime = (microtime(true) - $startTime) * 1000; // ms

            if ($responseTime > 5000) { // Alert if >5 seconds
                Log::critical("Dashboard API extremely slow: {$responseTime}ms");
                $this->error("CRITICAL: Dashboard response time: {$responseTime}ms");
            } elseif ($responseTime > 1000) { // Warning if >1 second
                Log::warning("Dashboard API slow: {$responseTime}ms");
                $this->warn("WARNING: Dashboard response time: {$responseTime}ms");
            } else {
                $this->info("Dashboard API performance OK: {$responseTime}ms");
            }

            // Cache the result for trending
            Cache::put('dashboard_performance_'.date('Y-m-d-H'), $responseTime, 3600);

        } catch (\Exception $e) {
            Log::error('Dashboard API test failed: '.$e->getMessage());
            $this->error('CRITICAL: Dashboard API test failed - '.$e->getMessage());
        }

        return Command::SUCCESS;
    }
}
