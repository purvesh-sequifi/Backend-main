<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use PDO;

class WriteOptimizedDatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register any write-specific services here
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only set up monitoring, don't immediately configure connections
        // Connection optimization will happen when database is first accessed
        $this->setupWritePerformanceMonitoring();

        // Set up a database connection resolver that applies optimizations when needed
        $this->setupLazyConnectionOptimization();
    }

    /**
     * Configure database connections for optimal write performance
     */
    private function configureWriteOptimizedConnections(): void
    {
        try {
            // Only configure if we have MySQL and the connection is available
            if (! extension_loaded('pdo_mysql')) {
                return;
            }

            // Get the default database connection
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            // Apply PDO attributes from config (respects RDS Proxy settings)
            $pdo->setAttribute(PDO::ATTR_PERSISTENT, config('database.connections.mysql.options.'.PDO::ATTR_PERSISTENT, false));
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, config('database.connections.mysql.options.'.PDO::ATTR_EMULATE_PREPARES, true));
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, config('database.connections.mysql.options.'.PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true));
            $pdo->setAttribute(PDO::ATTR_TIMEOUT, config('database.connections.mysql.options.'.PDO::ATTR_TIMEOUT, 30));

            // Execute additional write-optimized session variables if needed
            $this->executeWriteOptimizations($pdo);

            // Only log success if we're in debug mode to avoid permission issues
            if (config('app.debug', false)) {
                Log::info('Write-optimized database connection configured successfully', [
                    'connection' => config('database.default'),
                    'persistent' => $pdo->getAttribute(PDO::ATTR_PERSISTENT) ? 'enabled' : 'disabled',
                ]);
            }

        } catch (\Exception $e) {
            // Silently fail during deployment - don't break the application
            // Only log errors if we're in debug mode to avoid permission issues with logs
            if (config('app.debug', false)) {
                Log::error('Failed to configure write-optimized database connection', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Execute additional write optimizations on the PDO connection
     */
    private function executeWriteOptimizations(PDO $pdo): void
    {
        try {
            // Get MySQL version to ensure compatibility
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            $isMysql8 = version_compare($version, '8.0', '>=');

            // Only include SESSION variables that normal users can modify
            // Removed GLOBAL variables: innodb_flush_log_at_trx_commit, sync_binlog, innodb_autoinc_lock_mode
            $optimizations = [
                'SET SESSION autocommit=1',
                'SET SESSION bulk_insert_buffer_size=67108864',  // 64MB for bulk operations
            ];

            // Add transaction isolation based on MySQL version
            if ($isMysql8) {
                // MySQL 8.0+ uses newer syntax
                $optimizations[] = "SET SESSION transaction_isolation='READ-COMMITTED'";
            } else {
                // MySQL 5.7 and earlier
                $optimizations[] = "SET SESSION tx_isolation='READ-COMMITTED'";
            }

            foreach ($optimizations as $sql) {
                $pdo->exec($sql);
            }

            Log::info('Write optimizations applied successfully', [
                'mysql_version' => $version,
                'optimizations_count' => count($optimizations),
            ]);

        } catch (\Exception $e) {
            // Silently fail for write optimizations - don't break the application
            // Only log if we're in debug mode to avoid permission issues with logs
            if (config('app.debug', false)) {
                Log::warning('Some write optimizations could not be applied', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Set up monitoring for write performance
     */
    private function setupWritePerformanceMonitoring(): void
    {
        // Only enable monitoring in debug mode or if explicitly enabled
        if (! config('app.debug') && ! config('database.write_optimization.monitor_writes', false)) {
            return;
        }

        DB::listen(function ($query) {
            try {
                // Monitor slow write operations
                if ($query->time > config('database.write_optimization.slow_query_threshold', 1000)) {
                    $statement = strtoupper(substr(trim($query->sql), 0, 6));

                    if (in_array($statement, ['INSERT', 'UPDATE', 'DELETE', 'REPLAC'])) {
                        // Only log if we're in debug mode to avoid permission issues
                        if (config('app.debug', false)) {
                            Log::warning('Slow write query detected', [
                                'sql' => $query->sql,
                                'time_ms' => $query->time,
                                'bindings' => config('app.debug') ? $query->bindings : '[hidden]',
                                'connection' => $query->connectionName ?? 'default',
                            ]);
                        }
                    }
                }

                // Track write query patterns for optimization
                if (config('database.write_optimization.track_write_patterns', false)) {
                    $statement = strtoupper(substr(trim($query->sql), 0, 6));
                    if (in_array($statement, ['INSERT', 'UPDATE', 'DELETE', 'REPLAC'])) {
                        cache()->increment('db_writes_'.$statement.'_count');
                        cache()->increment('db_writes_total_time', $query->time);
                    }
                }
            } catch (\Exception $e) {
                // Silently fail to avoid breaking the application
            }
        });
    }

    /**
     * Set up lazy connection optimization that only triggers when database is accessed
     */
    private function setupLazyConnectionOptimization(): void
    {
        // Use DB::listen to apply optimizations on first database access
        DB::listen(function () {
            static $optimized = false;

            if (! $optimized) {
                $this->configureWriteOptimizedConnections();
                $optimized = true;
            }
        });
    }

    /**
     * Get write performance statistics
     */
    public static function getWriteStats(): array
    {
        try {
            $stats = DB::select("
                SHOW GLOBAL STATUS WHERE Variable_name IN (
                    'Com_insert', 'Com_update', 'Com_delete', 'Com_replace',
                    'Innodb_rows_inserted', 'Innodb_rows_updated', 'Innodb_rows_deleted',
                    'Innodb_buffer_pool_write_requests', 'Innodb_log_writes',
                    'Threads_connected', 'Threads_running', 'Max_used_connections'
                )
            ");

            $formatted = [];
            foreach ($stats as $stat) {
                $formatted[$stat->Variable_name] = $stat->Value;
            }

            return $formatted;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve write statistics', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
