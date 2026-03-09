<?php

declare(strict_types=1);

namespace App\Services\DataSources;

use App\Contracts\MetricsDataSource;
use App\Services\ClickHouseConnectionService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ClickHouseMetricsDataSource
 * 
 * ClickHouse implementation of metrics data source for dashboard queries.
 * Optimized for time-series data with multi-tenant support.
 * 
 * Database: Api_metrices (shared across all 54 tenants)
 * Tenant Isolation: domain_name column
 */
class ClickHouseMetricsDataSource implements MetricsDataSource
{
    protected string $database;
    protected string $domainName;
    protected $client = null;

    public function __construct()
    {
        $this->database = config('api-performance.clickhouse.database', 'Api_metrices');
        $this->domainName = config('api-performance.clickhouse.domain_name', config('app.domain_name', 'unknown'));
        $this->initializeClient();
    }
    
    /**
     * Initialize dedicated ClickHouse client for API metrics (separate from Activity_log).
     */
    protected function initializeClient(): void
    {
        try {
            $config = [
                'host' => config('api-performance.clickhouse.host'),
                'port' => (int) config('api-performance.clickhouse.port'),
                'username' => config('api-performance.clickhouse.username'),
                'password' => config('api-performance.clickhouse.password'),
                'https' => config('api-performance.clickhouse.protocol') === 'https',
                'database' => $this->database,
                'timeout' => (float) config('api-performance.clickhouse.timeout'),
                'connect_timeout' => (float) config('api-performance.clickhouse.connect_timeout'),
            ];
            
            $this->client = new \ClickHouseDB\Client($config);
            
            // Explicitly set the database to ensure correct context
            $this->client->database($this->database);
        } catch (\Exception $e) {
            Log::channel('daily')->error('ClickHouse metrics data source initialization failed', [
                'error' => $e->getMessage(),
            ]);
            $this->client = null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getOverviewStats(string $timeRange): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            
            $query = "
                SELECT 
                    count() as total_requests,
                    avg(response_time_ms) as avg_response_time_ms,
                    sum(status_code >= 400) as error_count
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= {$rangeFilter}
            ";

            // Use the properly configured client from initializeClient()
            $client = $this->client;
            if (!$client) {
                return $this->getEmptyOverviewStats($timeRange);
            }
            
            $result = $client->select($query);
            $row = $result->rows()[0] ?? [];
            
            $totalRequests = (int) ($row['total_requests'] ?? 0);
            $errorCount = (int) ($row['error_count'] ?? 0);
            $errorRate = $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;

            return [
                'total_requests' => $totalRequests,
                'avg_response_time_ms' => round((float) ($row['avg_response_time_ms'] ?? 0), 2),
                'error_count' => $errorCount,
                'error_rate_percent' => round($errorRate, 2),
                'time_range' => $timeRange,
            ];
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse overview stats query failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domainName,
            ]);
            
            return $this->getEmptyOverviewStats($timeRange);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getTopEndpoints(string $timeRange, int $limit = 10, string $sortBy = 'requests'): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            
            $orderBy = $sortBy === 'requests' ? 'request_count DESC' : 'avg_response_time DESC';
            
            $query = "
                SELECT 
                    endpoint,
                    method,
                    count() as request_count,
                    avg(response_time_ms) as avg_response_time
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= {$rangeFilter}
                GROUP BY endpoint, method
                ORDER BY {$orderBy}
                LIMIT {$limit}
            ";

            // Use the properly configured client from initializeClient()
            $client = $this->client;
            if (!$client) return [];
            
            $result = $client->select($query);
            
            $endpoints = [];
            foreach ($result->rows() as $row) {
                $endpoints[] = [
                    'endpoint' => $row['endpoint'] ?? 'unknown',
                    'method' => $row['method'] ?? 'unknown',
                    'requests' => (int) ($row['request_count'] ?? 0),
                    'avg_response_time' => round((float) ($row['avg_response_time'] ?? 0), 2),
                ];
            }

            return $endpoints;
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse top endpoints query failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domainName,
            ]);
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSlowEndpoints(string $timeRange, float $thresholdMs = 1000.0, int $limit = 10): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            
            $query = "
                SELECT 
                    endpoint,
                    method,
                    avg(response_time_ms) as avg_response_time_ms,
                    count() as request_count
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= {$rangeFilter}
                GROUP BY endpoint, method
                HAVING avg_response_time_ms > {$thresholdMs}
                ORDER BY avg_response_time_ms DESC
                LIMIT {$limit}
            ";

            // Use the properly configured client from initializeClient()
            $client = $this->client;
            if (!$client) return [];
            
            $result = $client->select($query);
            
            $endpoints = [];
            foreach ($result->rows() as $row) {
                $endpoints[] = [
                    'endpoint' => $row['endpoint'] ?? 'unknown',
                    'method' => $row['method'] ?? 'unknown',
                    'avg_response_time_ms' => round((float) ($row['avg_response_time_ms'] ?? 0), 2),
                    'request_count' => (int) ($row['request_count'] ?? 0),
                ];
            }

            return $endpoints;
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse slow endpoints query failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domainName,
            ]);
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPerformanceHistory(string $timeRange): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            $interval = $this->getIntervalForTimeRange($timeRange);
            
            $query = "
                SELECT 
                    toStartOf{$interval}(timestamp) as time_bucket,
                    avg(response_time_ms) as value
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= {$rangeFilter}
                GROUP BY time_bucket
                ORDER BY time_bucket
            ";

            // Use the properly configured client from initializeClient()
            $client = $this->client;
            if (!$client) return [];
            
            $result = $client->select($query);
            
            $history = [];
            foreach ($result->rows() as $row) {
                $history[] = [
                    'timestamp' => $row['time_bucket'] ?? '',
                    'value' => round((float) ($row['value'] ?? 0), 2),
                ];
            }

            return $history;
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse performance history query failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domainName,
            ]);
            return [];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getErrorStats(string $timeRange): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            
            $query = "
                SELECT 
                    sum(status_code >= 400 AND status_code < 500) as errors_4xx,
                    sum(status_code >= 500) as errors_5xx
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= {$rangeFilter}
            ";

            $client = $this->client;
            if (!$client) return ['4xx' => 0, '5xx' => 0, 'total' => 0];
            
            $result = $client->select($query);
            $row = $result->rows()[0] ?? [];
            
            $errors4xx = (int) ($row['errors_4xx'] ?? 0);
            $errors5xx = (int) ($row['errors_5xx'] ?? 0);

            return [
                '4xx' => $errors4xx,
                '5xx' => $errors5xx,
                'total' => $errors4xx + $errors5xx,
            ];
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse error stats query failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domainName,
            ]);
            return ['4xx' => 0, '5xx' => 0, 'total' => 0];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEndpointAnalytics(string $endpoint, ?string $method, string $timeRange): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            $safeEndpoint = $this->escapeLiteral($endpoint);
            $methodFilter = $method ? 'AND method = ' . $this->escapeLiteral($method) : '';
            
            $query = "
                SELECT 
                    avg(response_time_ms) as avg_response_time_ms,
                    count() as request_count
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND endpoint = {$safeEndpoint}
                  {$methodFilter}
                  AND timestamp >= {$rangeFilter}
            ";

            $client = $this->client;
            if (!$client) {
                return [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'avg_response_time_ms' => 0,
                    'request_count' => 0,
                    'time_range' => $timeRange,
                ];
            }
            
            $result = $client->select($query);
            $row = $result->rows()[0] ?? [];

            return [
                'endpoint' => $endpoint,
                'method' => $method,
                'avg_response_time_ms' => round((float) ($row['avg_response_time_ms'] ?? 0), 2),
                'request_count' => (int) ($row['request_count'] ?? 0),
                'time_range' => $timeRange,
            ];
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse endpoint analytics query failed', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'domain' => $this->domainName,
            ]);
            return [
                'endpoint' => $endpoint,
                'method' => $method,
                'avg_response_time_ms' => 0,
                'time_range' => $timeRange,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSystemHealth(): array
    {
        try {
            // Use the properly configured client from initializeClient()
            $client = $this->client;
            
            if (!$client) {
                return [
                    'status' => 'unavailable',
                    'message' => 'ClickHouse metrics client not available',
                ];
            }
            
            $client->ping();
            
            return [
                'status' => 'healthy',
                'message' => 'ClickHouse metrics connection active',
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isHealthy(): bool
    {
        $health = $this->getSystemHealth();
        return $health['status'] === 'healthy';
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return 'clickhouse';
    }

    /**
     * {@inheritDoc}
     */
    public function getEndpointsWithErrors(string $timeRange, int $limit = 10): array
    {
        try {
            $rangeFilter = $this->convertTimeRangeToSQL($timeRange);
            $safeDomainName = $this->escapeDomainName($this->domainName);
            
            $query = "
                SELECT 
                    endpoint,
                    method,
                    sum(status_code >= 400 AND status_code < 500) as errors_4xx,
                    sum(status_code >= 500) as errors_5xx,
                    count() as total_requests,
                    avg(response_time_ms) as avg_response_time_ms
                FROM {$this->database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= {$rangeFilter}
                  AND status_code >= 400
                GROUP BY endpoint, method
                ORDER BY (errors_4xx + errors_5xx) DESC
                LIMIT {$limit}
            ";

            $client = $this->client;
            if (!$client) return [];
            
            $result = $client->select($query);
            
            $endpoints = [];
            foreach ($result->rows() as $row) {
                $errors4xx = (int) ($row['errors_4xx'] ?? 0);
                $errors5xx = (int) ($row['errors_5xx'] ?? 0);
                
                $endpoints[] = [
                    'endpoint' => $row['endpoint'] ?? 'unknown',
                    'method' => $row['method'] ?? 'unknown',
                    'error_count' => $errors4xx + $errors5xx,
                    'errors_4xx' => $errors4xx,
                    'errors_5xx' => $errors5xx,
                    'total_requests' => (int) ($row['total_requests'] ?? 0),
                    'avg_response_time_ms' => round((float) ($row['avg_response_time_ms'] ?? 0), 2),
                ];
            }

            return $endpoints;
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse endpoints with errors query failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domainName,
            ]);
            return [];
        }
    }

    /**
     * Safely escape domain names for ClickHouse queries.
     * Validates domain name format (strict validation for tenant isolation).
     * 
     * @param string $domainName Domain name to escape
     * @return string Escaped and quoted string literal
     */
    protected function escapeDomainName(string $domainName): string
    {
        // Strict validation: alphanumeric, dots, hyphens, underscores only
        if (!preg_match('/^[a-zA-Z0-9.\-_]+$/', $domainName)) {
            throw new \InvalidArgumentException("Invalid domain name format: {$domainName}");
        }
        
        // Escape single quotes and backslashes for ClickHouse
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $domainName);
        return "'{$escaped}'";
    }

    /**
     * Safely escape any string value for ClickHouse queries.
     * More lenient than escapeDomainName() - allows URLs, paths, etc.
     * 
     * @param string $value Value to escape (endpoint, method, etc.)
     * @return string Escaped and quoted string literal
     */
    protected function escapeLiteral(string $value): string
    {
        // Escape single quotes and backslashes for ClickHouse
        // No strict validation - relies on escaping only
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
        return "'{$escaped}'";
    }

    /**
     * Convert time range to ClickHouse SQL format.
     * Supports both fixed formats (1h, 6h, 24h, 7d, 30d) and dynamic formats (5m, 10m, 15m).
     * 
     * @param string $timeRange Time range (e.g., '1h', '24h', '5m', '10m')
     * @return string ClickHouse time interval expression
     */
    protected function convertTimeRangeToSQL(string $timeRange): string
    {
        // Handle dynamic minute formats (5m, 10m, 15m, etc.)
        if (preg_match('/^(\d+)m$/', $timeRange, $matches)) {
            $minutes = (int) $matches[1];
            return "now() - INTERVAL {$minutes} MINUTE";
        }
        
        // Handle fixed formats
        return match ($timeRange) {
            '1h' => 'now() - INTERVAL 1 HOUR',
            '6h' => 'now() - INTERVAL 6 HOUR',
            '24h' => 'now() - INTERVAL 24 HOUR',
            '7d' => 'now() - INTERVAL 7 DAY',
            '30d' => 'now() - INTERVAL 30 DAY',
            default => 'now() - INTERVAL 1 HOUR',
        };
    }

    /**
     * Get appropriate interval function for time range.
     */
    protected function getIntervalForTimeRange(string $timeRange): string
    {
        return match ($timeRange) {
            '1h' => 'Minute',          // toStartOfMinute()
            '6h' => 'FiveMinutes',     // toStartOfFiveMinutes()
            '24h' => 'FifteenMinutes', // toStartOfFifteenMinutes()
            '7d' => 'Hour',            // toStartOfHour()
            '30d' => 'Day',            // toStartOfDay()
            default => 'FiveMinutes',
        };
    }

    protected function getEmptyOverviewStats(string $timeRange): array
    {
        return [
            'total_requests' => 0,
            'avg_response_time_ms' => 0,
            'error_count' => 0,
            'error_rate_percent' => 0,
            'time_range' => $timeRange,
        ];
    }
}


