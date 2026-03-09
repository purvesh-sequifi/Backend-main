<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MetricsDataSourceFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * ApiPerformanceDashboardController
 * 
 * Dashboard controller that supports both SQLite and InfluxDB storage backends.
 * Uses MetricsDataSourceFactory to automatically select the configured data source.
 */
class ApiPerformanceDashboardController extends Controller
{
    /**
     * Display the main dashboard
     */
    public function index()
    {
        try {
            $stats = $this->getDashboardStats();
            $topEndpoints = $this->getTopEndpointsData();
            $slowEndpoints = $this->getSlowEndpointsData();
            $systemHealth = $this->getSystemHealth();

            return view('api-performance.dashboard', compact(
                'stats', 'topEndpoints', 'slowEndpoints', 'systemHealth'
            ));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Dashboard error: '.$e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Get real-time dashboard statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        $timeRange = $request->get('range', '1h'); // 1h, 6h, 24h, 7d

        try {
            $dataSource = MetricsDataSourceFactory::create();

        $stats = [
                'overview' => $dataSource->getOverviewStats($timeRange),
                'endpoints' => $dataSource->getTopEndpoints($timeRange, 10, 'requests'),
                'performance' => $dataSource->getPerformanceHistory($timeRange),
                'errors' => $dataSource->getErrorStats($timeRange),
                'system' => $dataSource->getSystemHealth(),
        ];

        return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get endpoint-specific analytics
     */
    public function getEndpointAnalytics(Request $request, $endpoint): JsonResponse
    {
        $endpoint = urldecode($endpoint);
        $timeRange = $request->get('range', '24h');
        $method = $request->get('method');

        try {
            $dataSource = MetricsDataSourceFactory::create();

            $analytics = $dataSource->getEndpointAnalytics($endpoint, $method, $timeRange);

        return response()->json($analytics);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch endpoint analytics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show endpoint analytics HTML view
     */
    public function showEndpointAnalytics(Request $request, $endpoint): View
    {
        $endpoint = urldecode($endpoint);
        $timeRange = $request->get('range', '24h');
        $method = $request->get('method');

        try {
            $dataSource = MetricsDataSourceFactory::create();

            $analytics = $dataSource->getEndpointAnalytics($endpoint, $method, $timeRange);

        return view('api-performance.endpoint-analytics', [
            'endpoint' => $endpoint,
            'method' => $method,
            'timeRange' => $timeRange,
            'analytics' => $analytics,
            ]);
        } catch (\Exception $e) {
            return view('api-performance.endpoint-analytics', [
                'endpoint' => $endpoint,
                'method' => $method,
                'timeRange' => $timeRange,
                'analytics' => [],
                'error' => $e->getMessage(),
        ]);
        }
    }

    /**
     * Get slow endpoints detection
     */
    public function getSlowEndpoints(Request $request): JsonResponse
    {
        $timeRange = $request->get('range', '1h');
        $threshold = (float) $request->get('threshold', config('api-performance.thresholds.response_time.warning_ms', 1000));

        try {
            $dataSource = MetricsDataSourceFactory::create();
            
            $slowEndpoints = $dataSource->getSlowEndpoints($timeRange, $threshold, 20);

        return response()->json([
                'slow_endpoints' => $slowEndpoints,
            'threshold_ms' => $threshold,
            'time_range' => $timeRange,
        ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch slow endpoints: ' . $e->getMessage(),
                'slow_endpoints' => [],
            ], 500);
        }
    }

    /**
     * Get performance history for charts
     */
    public function getPerformanceHistory(Request $request): JsonResponse
    {
        $timeRange = $request->get('range', '24h');
        $endpoint = $request->get('endpoint');
        $metric = $request->get('metric', 'response_time'); // response_time, memory, throughput, errors

        try {
            $dataSource = MetricsDataSourceFactory::create();
            
            $history = $dataSource->getPerformanceHistory($timeRange);

        return response()->json([
            'history' => $history,
            'metric' => $metric,
            'endpoint' => $endpoint,
        ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch performance history: ' . $e->getMessage(),
                'history' => [],
            ], 500);
        }
    }

    /**
     * Get top performing/problematic endpoints
     */
    public function getTopEndpoints(Request $request): JsonResponse
    {
        $timeRange = $request->get('range', '1h');
        $sortBy = $request->get('sort', 'requests'); // requests, response_time, errors, memory
        $order = $request->get('order', 'desc');
        $limit = (int) $request->get('limit', 50);

        try {
            $dataSource = MetricsDataSourceFactory::create();

            $endpoints = $dataSource->getTopEndpoints($timeRange, $limit, $sortBy);

        return response()->json([
            'endpoints' => $endpoints,
            'sort_by' => $sortBy,
            'time_range' => $timeRange,
        ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch top endpoints: ' . $e->getMessage(),
                'endpoints' => [],
            ], 500);
        }
    }

    /**
     * Get system health data for API
     */
    public function getSystemHealthApi(): JsonResponse
    {
        return response()->json($this->getSystemHealth());
    }

    /**
     * Export metrics data
     * 
     * Note: For InfluxDB, this will query and export recent data.
     * For large datasets, consider implementing streaming or pagination.
     */
    public function exportMetrics(Request $request)
    {
        $format = $request->get('format', 'csv'); // csv, json
        $timeRange = $request->get('range', '24h');
        $endpoint = $request->get('endpoint');

        try {
            // Use ClickHouse data source for exports (SQLite connection removed)
            $dataSource = MetricsDataSourceFactory::create();
            
            // Get data based on whether specific endpoint requested
            if ($endpoint) {
                // Get specific endpoint data
                $data = $dataSource->getEndpointAnalytics($endpoint, null, $timeRange);
                $exportData = [$data]; // Wrap in array for consistent format
            } else {
                // Get top endpoints for export
                $exportData = $dataSource->getTopEndpoints($timeRange, 10000, 'requests');
            }

            if ($format === 'csv') {
                return $this->exportToCsv(collect($exportData), $endpoint);
            }

            return response()->json(['data' => $exportData]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCriticalErrors(Request $request)
    {
        $timeRange = $request->get('range', '1h');

        try {
            // Get HTTP errors from metrics data source
            $dataSource = MetricsDataSourceFactory::create();
            $errorStats = $dataSource->getErrorStats($timeRange);

            // Get Laravel log errors (last 10)
            $laravelErrors = $this->getLaravelLogErrors();

            // Get Apache log errors (last 10)
            $apacheErrors = $this->getApacheLogErrors();

            // Get System log errors (last 10)
            $systemErrors = $this->getSystemLogErrors();

            // Combine all errors
            $allErrors = array_merge($laravelErrors, $apacheErrors, $systemErrors);

            // Sort by timestamp (newest first)
            usort($allErrors, function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            // Calculate combined http_errors for backward compatibility
            $errors4xx = $errorStats['4xx'] ?? 0;
            $errors5xx = $errorStats['5xx'] ?? 0;
            $totalHttpErrors = $errors4xx + $errors5xx;
            
            // Get affected endpoints from HTTP metrics (not log errors)
            // Use dedicated getEndpointsWithErrors() method from data source
            $affectedEndpoints = $dataSource->getEndpointsWithErrors($timeRange, 10);

            return response()->json([
                'total_errors' => $errorStats['total'] ?? 0,
                // Backward compatible field (sum of 4xx + 5xx)
                'http_errors' => $totalHttpErrors,
                // New granular fields
                'http_errors_4xx' => $errors4xx,
                'http_errors_5xx' => $errors5xx,
                'laravel_errors' => count($laravelErrors),
                'apache_errors' => count($apacheErrors),
                'system_errors' => count($systemErrors),
                'recent_errors' => array_slice($allErrors, 0, 20), // Show top 20
                'error_sources' => [
                    'HTTP' => $errorStats['total'] ?? 0,
                    'Laravel' => count($laravelErrors),
                    'Apache' => count($apacheErrors),
                    'System' => count($systemErrors),
                ],
                // Backward compatible fields
                'error_breakdown' => $this->getErrorBreakdown($allErrors, $errorStats),
                'affected_endpoints' => $affectedEndpoints,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch critical errors: '.$e->getMessage(),
                'total_errors' => 0,
                'recent_errors' => [],
                'error_sources' => [],
            ]);
        }
    }

    // Private helper methods

    private function getDashboardStats(): array
    {
        $cacheKey = 'api_performance_dashboard_stats';

        return Cache::remember($cacheKey, 60, function () {
            try {
                $dataSource = MetricsDataSourceFactory::create();
                
                // Get 5-minute overview
                $stats = $dataSource->getOverviewStats('1h'); // Use 1h, filter to 5min in display

            return [
                    'total_requests_5min' => $stats['total_requests'] ?? 0,
                    'avg_response_time_5min' => $stats['avg_response_time_ms'] ?? 0,
                    'error_rate_5min' => $stats['error_count'] ?? 0,
                    'active_endpoints' => 0, // TODO: Implement in data source interface
                ];
            } catch (\Exception $e) {
                return [
                    'total_requests_5min' => 0,
                    'avg_response_time_5min' => 0,
                    'error_rate_5min' => 0,
                    'active_endpoints' => 0,
            ];
            }
        });
    }

    private function getTopEndpointsData(): array
    {
        try {
            $dataSource = MetricsDataSourceFactory::create();
            return $dataSource->getTopEndpoints('1h', 10, 'requests');
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getSlowEndpointsData(): array
    {
        try {
            $dataSource = MetricsDataSourceFactory::create();
            return $dataSource->getSlowEndpoints('1h', 1000.0, 10);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getSystemHealth(): array
    {
        try {
            $dataSource = MetricsDataSourceFactory::create();
            $metricsHealth = $dataSource->getSystemHealth();
            
        return [
                'metrics_storage' => [
                    'type' => $dataSource->getType(),
                    'status' => $metricsHealth['status'] ?? 'unknown',
                    'message' => $metricsHealth['message'] ?? '',
                ],
            'cache' => $this->checkCache(),
            'disk_space' => $this->getDiskSpace(),
            'memory_usage' => $this->getMemoryUsage(),
        ];
        } catch (\Exception $e) {
            return [
                'metrics_storage' => [
                    'type' => 'unknown',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
                'cache' => ['status' => 'unknown'],
                'disk_space' => ['status' => 'unknown'],
                'memory_usage' => ['status' => 'unknown'],
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'api_performance_health_check_'.time();
            Cache::put($testKey, 'test', 1);
            $value = Cache::get($testKey);

            return ['status' => $value === 'test' ? 'healthy' : 'error'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getDiskSpace(): array
    {
        try {
            $total = disk_total_space(storage_path());
            $free = disk_free_space(storage_path());
            $used = $total - $free;
            $percentage = round(($used / $total) * 100, 2);

            return [
                'status' => $percentage > 90 ? 'critical' : ($percentage > 80 ? 'warning' : 'healthy'),
                'used_percentage' => $percentage,
                'free_space' => $this->formatBytes($free),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Unable to check disk space'];
        }
    }

    private function getMemoryUsage(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->getMemoryLimit();
            $percentage = round(($memoryUsage / $memoryLimit) * 100, 2);

            return [
                'status' => $percentage > 90 ? 'critical' : ($percentage > 80 ? 'warning' : 'healthy'),
                'used_percentage' => $percentage,
                'current_usage' => $this->formatBytes($memoryUsage),
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Unable to check memory usage'];
        }
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
            if ($matches[2] == 'M') {
                return (int) $matches[1] * 1024 * 1024;
            } elseif ($matches[2] == 'K') {
                return (int) $matches[1] * 1024;
            } elseif ($matches[2] == 'G') {
                return (int) $matches[1] * 1024 * 1024 * 1024;
            }
        }

        return (int) $memoryLimit;
    }

    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }

    private function exportToCsv($data, $endpoint)
    {
        $filename = 'api_metrics_'.($endpoint ? str_replace('/', '_', $endpoint) : 'all').'_'.date('Y-m-d_H-i-s').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV headers for aggregated data (ClickHouse provides aggregated metrics, not raw requests)
            fputcsv($file, [
                'Endpoint', 'Method', 'Total Requests', 'Avg Response Time (ms)',
            ]);

            foreach ($data as $row) {
                // Handle both array and object formats
                $rowData = is_array($row) ? $row : (array) $row;
                
                fputcsv($file, [
                    $rowData['endpoint'] ?? '',
                    $rowData['method'] ?? '',
                    $rowData['requests'] ?? $rowData['request_count'] ?? $rowData['total_requests'] ?? 0,
                    $rowData['avg_response_time'] ?? $rowData['avg_response_time_ms'] ?? 0,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function applyTimeRange($query, string $timeRange): void
    {
        $start = match ($timeRange) {
            '1h' => Carbon::now()->subHour(),
            '6h' => Carbon::now()->subHours(6),
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            default => Carbon::now()->subHour(),
        };

        $query->where('timestamp', '>=', $start);
    }

    private function getLaravelLogErrors(): array
    {
        $errors = [];
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return $errors;
        }

        try {
            // Read last 100 lines to find error entries
            $lines = $this->readLastLines($logPath, 100);
            $errorCount = 0;

            foreach (array_reverse($lines) as $line) {
                if ($errorCount >= 10) {
                    break;
                }

                // Parse Laravel log format: [timestamp] level.CHANNEL: message
                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*\.(ERROR|CRITICAL|EMERGENCY):\s*(.+)/', $line, $matches)) {
                    // Laravel logs are written in the timezone set in config/app.php
                    // Read directly from file as env() may override config()
                    $appTimezone = $this->getActualLogTimezone();
                    try {
                        $carbon = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $matches[1], $appTimezone);
                        $utcTimestamp = $carbon->utc()->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $utcTimestamp = gmdate('Y-m-d H:i:s');
                    }
                    
                    $errors[] = [
                        'source' => 'Laravel',
                        'level' => strtolower($matches[2]),
                        'message' => trim($matches[3]),
                        'context' => ['raw_line' => $line],
                        'timestamp' => $utcTimestamp,
                    ];
                    $errorCount++;
                }
            }
        } catch (\Exception $e) {
            // Fail silently, return what we have
        }

        return $errors;
    }

    private function getApacheLogErrors(): array
    {
        $errors = [];
        $apachePaths = [
            '/var/log/apache2/error.log',
            '/var/log/httpd/error_log',
            '/opt/lampp/logs/error_log',
        ];

        foreach ($apachePaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                try {
                    $lines = $this->readLastLines($logPath, 50);
                    $errorCount = 0;

                    foreach (array_reverse($lines) as $line) {
                        if ($errorCount >= 10) {
                            break;
                        }

                        // Parse Apache error log format
                        if (preg_match('/\[([^\]]+)\]\s*\[([^\]]+)\]\s*(.+)/', $line, $matches)) {
                            $level = $this->normalizeLogLevel($matches[2]);
                            if (in_array($level, ['error', 'critical', 'emergency'])) {
                                $errors[] = [
                                    'source' => 'Apache',
                                    'level' => $level,
                                    'message' => trim($matches[3]),
                                    'context' => ['raw_line' => $line],
                                    'timestamp' => $this->parseApacheTimestamp($matches[1]),
                                ];
                                $errorCount++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next path
                }
                break; // Use first available log
            }
        }

        return $errors;
    }

    private function getSystemLogErrors(): array
    {
        $errors = [];
        $systemPaths = [
            '/var/log/syslog',
            '/var/log/messages',
        ];

        foreach ($systemPaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                try {
                    $lines = $this->readLastLines($logPath, 50);
                    $errorCount = 0;

                    foreach (array_reverse($lines) as $line) {
                        if ($errorCount >= 10) {
                            break;
                        }

                        // Parse syslog format and look for error keywords
                        if (preg_match('/(\w{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+(\S+)\s+(.+)/', $line, $matches)) {
                            $message = $matches[3];
                            if (preg_match('/\b(error|critical|emergency|fatal|fail|exception)\b/i', $message)) {
                                $errors[] = [
                                    'source' => 'System',
                                    'level' => 'error',
                                    'message' => trim($message),
                                    'context' => [
                                        'hostname' => $matches[2],
                                        'raw_line' => $line,
                                    ],
                                    'timestamp' => $this->parseSystemTimestamp($matches[1]),
                                ];
                                $errorCount++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to next path
                }
                break; // Use first available log
            }
        }

        return $errors;
    }

    private function readLastLines(string $filePath, int $lineCount): array
    {
        $lines = [];

        if (! file_exists($filePath)) {
            return $lines;
        }

        $file = fopen($filePath, 'r');
        if (! $file) {
            return $lines;
        }

        // Read from end of file
        fseek($file, -1, SEEK_END);
        $pos = ftell($file);
        $line = '';
        $linesRead = 0;

        while ($pos >= 0 && $linesRead < $lineCount) {
            fseek($file, $pos, SEEK_SET);
            $char = fgetc($file);

            if ($char === "\n" || $pos === 0) {
                if (! empty(trim($line))) {
                    array_unshift($lines, $line);
                    $linesRead++;
                }
                $line = '';
            } else {
                $line = $char.$line;
            }
            $pos--;
        }

        fclose($file);

        return $lines;
    }

    private function normalizeLogLevel(string $level): string
    {
        $level = strtolower(trim($level));

        // Map Apache log levels to standard levels
        $mapping = [
            'emerg' => 'emergency',
            'alert' => 'alert',
            'crit' => 'critical',
            'err' => 'error',
            'error' => 'error',
            'warn' => 'warning',
            'notice' => 'notice',
            'info' => 'info',
            'debug' => 'debug',
        ];

        return $mapping[$level] ?? $level;
    }

    private function parseApacheTimestamp(string $timestamp): string
    {
        try {
            // Try to parse Apache timestamp format and convert to UTC
            $parsed = strtotime($timestamp);
            return $parsed !== false ? gmdate('Y-m-d H:i:s', $parsed) : gmdate('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return gmdate('Y-m-d H:i:s'); // Current time as fallback in UTC
        }
    }

    private function parseSystemTimestamp(string $timestamp): string
    {
        try {
            // System logs (syslog) are in UTC and don't include year
            // Format: "Nov 19 12:19:49" -> "2025-11-19 12:19:49"
            $year = gmdate('Y'); // Use UTC year
            
            // Parse using DateTime with explicit UTC timezone
            $dateTime = \DateTime::createFromFormat('Y M j H:i:s', "$year $timestamp", new \DateTimeZone('UTC'));
            
            if ($dateTime !== false) {
                return $dateTime->format('Y-m-d H:i:s');
            }
            
            // Fallback: current time in UTC
            return gmdate('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return gmdate('Y-m-d H:i:s'); // Current time as fallback in UTC
        }
    }
    
    /**
     * Get the actual timezone used for Laravel logs.
     * Reads from config/app.php file as env() override may differ.
     */
    private function getActualLogTimezone(): string
    {
        static $cachedTimezone = null;
        
        if ($cachedTimezone !== null) {
            return $cachedTimezone;
        }
        
        // Try to read from config file directly
        $configPath = config_path('app.php');
        if (file_exists($configPath)) {
            $content = file_get_contents($configPath);
            // Match: 'timezone' => 'America/New_York',
            if (preg_match("/'timezone'\s*=>\s*'([^']+)'/", $content, $matches)) {
                $cachedTimezone = $matches[1];
                return $cachedTimezone;
            }
        }
        
        // Fallback to config value
        $cachedTimezone = config('app.timezone', 'UTC');
        return $cachedTimezone;
    }

    /**
     * Get error breakdown by type/category.
     * Provides backward compatibility for frontend dashboard.
     * 
     * @param array $errors Log errors (Laravel, Apache, System)
     * @param array $errorStats HTTP error stats from metrics data source
     * @return array<string, int>
     */
    private function getErrorBreakdown(array $errors, array $errorStats): array
    {
        $breakdown = [
            // HTTP errors from metrics data source (not from logs)
            'http_4xx' => (int) ($errorStats['4xx'] ?? 0),
            'http_5xx' => (int) ($errorStats['5xx'] ?? 0),
            // Log errors counted from $errors array
            'laravel_exceptions' => 0,
            'apache_errors' => 0,
            'system_errors' => 0,
        ];

        foreach ($errors as $error) {
            $source = strtolower($error['source'] ?? 'unknown');
            switch ($source) {
                case 'laravel':
                    $breakdown['laravel_exceptions']++;
                    break;
                case 'apache':
                    $breakdown['apache_errors']++;
                    break;
                case 'system':
                    $breakdown['system_errors']++;
                    break;
            }
        }

        return $breakdown;
    }

}
