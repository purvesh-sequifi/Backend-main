<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClickHouseSyncMonitorService
{
    private const SYNC_STATUS_CACHE_KEY = 'clickhouse_sync_status';

    private const SYNC_METRICS_CACHE_KEY = 'clickhouse_sync_metrics';

    private const HEALTH_CHECK_CACHE_KEY = 'clickhouse_health_check';

    /**
     * Check the overall health of ClickHouse sync
     */
    public static function checkSyncHealth(): array
    {
        $cacheKey = self::HEALTH_CHECK_CACHE_KEY;

        return Cache::remember($cacheKey, 300, function () { // Cache for 5 minutes
            $health = [
                'status' => 'healthy',
                'issues' => [],
                'metrics' => [],
                'last_check' => now()->toIso8601String(),
            ];

            try {
                // Check ClickHouse connectivity
                $connectionHealth = self::checkClickHouseConnection();
                $health['clickhouse_connection'] = $connectionHealth;

                if (! $connectionHealth['healthy']) {
                    $health['status'] = 'unhealthy';
                    $health['issues'][] = 'ClickHouse connection failed';
                }

                // Check sync lag
                $syncLag = self::checkSyncLag();
                $health['sync_lag'] = $syncLag;

                if ($syncLag['lag_hours'] > 2) {
                    $health['status'] = 'degraded';
                    $health['issues'][] = "Sync lag is {$syncLag['lag_hours']} hours";
                }

                // Check for failed batches
                $failedBatches = self::checkFailedBatches();
                $health['failed_batches'] = $failedBatches;

                if ($failedBatches['count'] > 0) {
                    $health['status'] = 'degraded';
                    $health['issues'][] = "{$failedBatches['count']} failed batches detected";
                }

                // Check data integrity
                $integrityCheck = self::checkDataIntegrity();
                $health['data_integrity'] = $integrityCheck;

                if (! $integrityCheck['healthy']) {
                    $health['status'] = 'unhealthy';
                    $health['issues'][] = 'Data integrity issues detected';
                }

                // Performance metrics
                $health['metrics'] = self::getPerformanceMetrics();

            } catch (\Exception $e) {
                $health['status'] = 'error';
                $health['issues'][] = 'Health check failed: '.$e->getMessage();
                Log::error('[ClickHouse Monitor] Health check failed', ['exception' => $e]);
            }

            return $health;
        });
    }

    /**
     * Check ClickHouse connection health
     */
    private static function checkClickHouseConnection(): array
    {
        try {
            $startTime = microtime(true);
            $success = ClickHouseConnectionService::ping(3, 30, false);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'healthy' => $success,
                'response_time_ms' => $responseTime,
                'last_check' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'last_check' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * Check sync lag between MySQL and ClickHouse
     */
    private static function checkSyncLag(): array
    {
        try {
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                return [
                    'lag_hours' => null,
                    'error' => 'Could not connect to ClickHouse',
                ];
            }

            // Get latest record from MySQL
            $latestMysql = DB::table('activity_log')
                ->orderBy('id', 'desc')
                ->first(['id', 'created_at']);

            if (! $latestMysql) {
                return [
                    'lag_hours' => 0,
                    'message' => 'No records in MySQL',
                ];
            }

            // Get latest record from ClickHouse
            $latestClickHouseResult = $client->select('SELECT max(toUInt64(id)) as max_id FROM activity_log WHERE id REGEXP \'^[0-9]+$\'')->rows();
            $latestClickHouseId = isset($latestClickHouseResult[0]['max_id']) ? (int) $latestClickHouseResult[0]['max_id'] : 0;

            $recordsLag = $latestMysql->id - $latestClickHouseId;

            // Calculate time lag based on the latest synced record
            $timeLag = 0;
            if ($latestClickHouseId > 0) {
                $syncedRecord = DB::table('activity_log')
                    ->where('id', $latestClickHouseId)
                    ->first(['created_at']);

                if ($syncedRecord) {
                    $timeLag = Carbon::parse($latestMysql->created_at)
                        ->diffInHours(Carbon::parse($syncedRecord->created_at));
                }
            }

            return [
                'lag_hours' => $timeLag,
                'records_behind' => $recordsLag,
                'latest_mysql_id' => $latestMysql->id,
                'latest_clickhouse_id' => $latestClickHouseId,
                'last_check' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            return [
                'lag_hours' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check for failed batches in recent sync attempts
     */
    private static function checkFailedBatches(): array
    {
        $checkpoint = Cache::get('clickhouse_sync_checkpoint');

        return [
            'count' => $checkpoint['total_failed'] ?? 0,
            'last_checkpoint' => $checkpoint['timestamp'] ?? null,
            'details' => $checkpoint ? [
                'last_processed_id' => $checkpoint['last_id'],
                'batch_number' => $checkpoint['batch_number'],
                'total_processed' => $checkpoint['total_processed'],
            ] : null,
        ];
    }

    /**
     * Check data integrity between MySQL and ClickHouse
     */
    private static function checkDataIntegrity(): array
    {
        try {
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                return [
                    'healthy' => false,
                    'error' => 'Could not connect to ClickHouse',
                ];
            }

            // Sample check: Compare counts for recent data
            $cutoffDate = now()->subHours(24)->toDateString();

            $mysqlCount = DB::table('activity_log')
                ->where('created_at', '>=', $cutoffDate)
                ->count();

            $clickhouseCount = (int) $client->select("SELECT count() as cnt FROM activity_log WHERE created_at >= '{$cutoffDate}'")->rows()[0]['cnt'];

            $discrepancy = abs($mysqlCount - $clickhouseCount);
            $discrepancyPercent = $mysqlCount > 0 ? round(($discrepancy / $mysqlCount) * 100, 2) : 0;

            return [
                'healthy' => $discrepancyPercent < 5, // Allow 5% discrepancy
                'mysql_count' => $mysqlCount,
                'clickhouse_count' => $clickhouseCount,
                'discrepancy' => $discrepancy,
                'discrepancy_percent' => $discrepancyPercent,
                'check_period' => "Last 24 hours from {$cutoffDate}",
                'last_check' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get performance metrics
     */
    private static function getPerformanceMetrics(): array
    {
        try {
            $client = ClickHouseConnectionService::getClient();
            if (! $client) {
                return ['error' => 'Could not connect to ClickHouse'];
            }

            // Get table size and recent activity
            $tableStats = $client->select('SELECT count() as total_rows, max(created_at) as latest_record FROM activity_log')->rows()[0];

            // Get recent sync performance from logs
            $recentSyncs = self::getRecentSyncPerformance();

            return [
                'total_records' => (int) $tableStats['total_rows'],
                'latest_record' => $tableStats['latest_record'],
                'recent_sync_performance' => $recentSyncs,
                'last_calculated' => now()->toIso8601String(),
            ];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get recent sync performance data from logs
     */
    private static function getRecentSyncPerformance(): array
    {
        // This would ideally read from a sync_performance table or log analysis
        // For now, return basic structure
        return [
            'avg_batch_time_seconds' => null,
            'avg_records_per_second' => null,
            'last_sync_duration' => null,
            'note' => 'Performance tracking not yet implemented',
        ];
    }

    /**
     * Record sync performance metrics
     */
    public static function recordSyncMetrics(array $metrics): void
    {
        $cacheKey = self::SYNC_METRICS_CACHE_KEY;

        $existingMetrics = Cache::get($cacheKey, []);
        $existingMetrics[] = array_merge($metrics, [
            'timestamp' => now()->toIso8601String(),
        ]);

        // Keep only last 100 entries
        if (count($existingMetrics) > 100) {
            $existingMetrics = array_slice($existingMetrics, -100);
        }

        Cache::put($cacheKey, $existingMetrics, now()->addDays(7));
    }

    /**
     * Get sync status summary
     */
    public static function getSyncStatusSummary(): array
    {
        $health = self::checkSyncHealth();

        return [
            'overall_status' => $health['status'],
            'issues_count' => count($health['issues']),
            'critical_issues' => array_filter($health['issues'], function ($issue) {
                return strpos($issue, 'connection failed') !== false ||
                       strpos($issue, 'integrity') !== false;
            }),
            'sync_lag_hours' => $health['sync_lag']['lag_hours'] ?? null,
            'last_health_check' => $health['last_check'],
            'clickhouse_responsive' => $health['clickhouse_connection']['healthy'] ?? false,
        ];
    }

    /**
     * Check if sync recovery is needed
     */
    public static function needsRecovery(): bool
    {
        $health = self::checkSyncHealth();

        return $health['status'] === 'unhealthy' ||
               ($health['sync_lag']['lag_hours'] ?? 0) > 6 ||
               ($health['failed_batches']['count'] ?? 0) > 10;
    }

    /**
     * Get recovery recommendations
     */
    public static function getRecoveryRecommendations(): array
    {
        $health = self::checkSyncHealth();
        $recommendations = [];

        if (! ($health['clickhouse_connection']['healthy'] ?? true)) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Check ClickHouse connection',
                'command' => 'php artisan clickhouse:heartbeat --deep-sleep-mode',
            ];
        }

        if (($health['sync_lag']['lag_hours'] ?? 0) > 2) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Resume sync from checkpoint',
                'command' => 'php artisan clickhouse:sync-activity-log-improved --resume',
            ];
        }

        if (($health['failed_batches']['count'] ?? 0) > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Restart sync with smaller batch size',
                'command' => 'php artisan clickhouse:sync-activity-log-improved --batch-size=2000 --force-restart',
            ];
        }

        if (! ($health['data_integrity']['healthy'] ?? true)) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Perform data integrity check and repair',
                'command' => 'php artisan clickhouse:verify-data-integrity --repair',
            ];
        }

        return $recommendations;
    }
}
