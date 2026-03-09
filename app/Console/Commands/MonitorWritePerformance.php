<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorWritePerformance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'db:monitor-writes 
                            {--daemon : Run as a daemon with continuous monitoring}
                            {--interval=10 : Monitoring interval in seconds (daemon mode)}
                            {--format=table : Output format (table|json)}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor database write performance and connection statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('daemon')) {
            return $this->runDaemon();
        }

        return $this->runOnce();
    }

    /**
     * Run monitoring once and exit
     */
    private function runOnce(): int
    {
        $stats = $this->getWritePerformanceStats();

        if (isset($stats['error'])) {
            $this->error('Error: '.$stats['error']);

            return 1;
        }

        $this->displayStats($stats);

        return 0;
    }

    /**
     * Run monitoring as a daemon
     */
    private function runDaemon(): int
    {
        $interval = (int) $this->option('interval');

        $this->info("Starting write performance monitor daemon (interval: {$interval}s)");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        while (true) {
            $stats = $this->getWritePerformanceStats();

            if (isset($stats['error'])) {
                $this->error('Error: '.$stats['error']);
                sleep($interval);

                continue;
            }

            $this->displayStats($stats);
            $this->newLine();

            sleep($interval);
        }

        return 0;
    }

    /**
     * Get write performance statistics
     */
    private function getWritePerformanceStats(): array
    {
        try {
            // Get write-related MySQL status variables
            $writeStats = DB::select("
                SHOW GLOBAL STATUS WHERE Variable_name IN (
                    'Com_insert', 'Com_update', 'Com_delete', 'Com_replace',
                    'Innodb_rows_inserted', 'Innodb_rows_updated', 'Innodb_rows_deleted',
                    'Innodb_buffer_pool_write_requests', 'Innodb_log_writes',
                    'Threads_connected', 'Threads_running', 'Max_used_connections',
                    'Connections', 'Aborted_connects'
                )
            ");

            // Get current write processes
            $writeProcesses = DB::select("
                SELECT COUNT(*) as count, COMMAND, STATE 
                FROM INFORMATION_SCHEMA.PROCESSLIST 
                WHERE COMMAND IN ('Query', 'Execute') 
                AND INFO REGEXP '^(INSERT|UPDATE|DELETE|REPLACE)'
                GROUP BY COMMAND, STATE
            ");

            // Get connection information
            $connectionInfo = DB::select("
                SELECT 
                    COUNT(*) as total_processes,
                    SUM(CASE WHEN COMMAND = 'Sleep' THEN 1 ELSE 0 END) as sleeping,
                    SUM(CASE WHEN COMMAND != 'Sleep' THEN 1 ELSE 0 END) as active,
                    AVG(TIME) as avg_time
                FROM INFORMATION_SCHEMA.PROCESSLIST
            ")[0];

            // Get Laravel cache stats if available
            $cacheStats = [];
            if (config('database.track_write_patterns', false)) {
                $cacheStats = [
                    'insert_count' => cache()->get('db_writes_INSERT_count', 0),
                    'update_count' => cache()->get('db_writes_UPDATE_count', 0),
                    'delete_count' => cache()->get('db_writes_DELETE_count', 0),
                    'total_time' => cache()->get('db_writes_total_time', 0),
                ];
            }

            return [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'write_stats' => $writeStats,
                'write_processes' => $writeProcesses,
                'connection_info' => $connectionInfo,
                'cache_stats' => $cacheStats,
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Display statistics in the chosen format
     */
    private function displayStats(array $stats): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));

            return;
        }

        // Table format (default)
        $this->info('=== Database Write Performance Monitor ===');
        $this->info('Time: '.$stats['timestamp']);
        $this->newLine();

        // Connection Overview
        if (isset($stats['connection_info'])) {
            $conn = $stats['connection_info'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Processes', $conn->total_processes],
                    ['Active Processes', $conn->active],
                    ['Sleeping Processes', $conn->sleeping],
                    ['Average Process Time', round($conn->avg_time, 2).'s'],
                ]
            );
        }

        // Write Statistics
        if (! empty($stats['write_stats'])) {
            $writeData = [];
            foreach ($stats['write_stats'] as $stat) {
                $writeData[] = [$stat->Variable_name, number_format($stat->Value)];
            }

            $this->info('MySQL Write Statistics:');
            $this->table(['Variable', 'Value'], $writeData);
        }

        // Active Write Processes
        if (! empty($stats['write_processes'])) {
            $this->info('Active Write Processes:');
            $processData = [];
            foreach ($stats['write_processes'] as $process) {
                $processData[] = [$process->COMMAND, $process->STATE, $process->count];
            }
            $this->table(['Command', 'State', 'Count'], $processData);
        } else {
            $this->info('No active write processes detected');
        }

        // Laravel Cache Statistics
        if (! empty($stats['cache_stats']) && array_sum($stats['cache_stats']) > 0) {
            $this->info('Laravel Write Pattern Cache:');
            $cacheData = [];
            foreach ($stats['cache_stats'] as $key => $value) {
                $cacheData[] = [ucfirst(str_replace('_', ' ', $key)), number_format($value)];
            }
            $this->table(['Metric', 'Value'], $cacheData);
        }
    }
}
