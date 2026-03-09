<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\ClickHouseConnectionService;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ClickHouseApiMetricsCollector
 * 
 * Collects and writes API performance metrics to ClickHouse with:
 * - Multi-tenant support via domain_name column
 * - Synchronous direct writes (queues removed for simplicity)
 * - Batch writes for efficiency  
 * - EC2 instance metadata tagging
 * - 90-day auto-retention via TTL
 * 
 * Database: Api_metrices (shared across all 54 tenants)
 * Table: api_requests (with domain_name column for tenant isolation)
 */
class ClickHouseApiMetricsCollector
{
    protected bool $enabled;
    protected float $samplingRate;
    protected string $domainName;
    protected string $instanceId;
    protected string $availabilityZone;
    protected string $database;

    public function __construct()
    {
        $this->enabled = config('api-performance.clickhouse.enabled', false);
        $this->samplingRate = (float) config('api-performance.collection.sampling_rate', 1.0);
        $this->domainName = config('api-performance.clickhouse.domain_name', config('app.domain_name', 'unknown'));
        $this->database = config('api-performance.clickhouse.database', 'Api_metrices');
        
        // Get EC2 instance metadata
        [$this->instanceId, $this->availabilityZone] = $this->getEC2Metadata();
    }

    /**
     * Get EC2 instance metadata for tagging.
     * Uses static cache (per-worker) + Redis cache (cross-worker) to minimize latency.
     * Only fetches from IMDS once per deployment.
     * 
     * @return array{string, string} [instance_id, availability_zone]
     */
    protected function getEC2Metadata(): array
    {
        // Static cache: Eliminates fetch overhead within same worker process
        static $metadata = null;
        
        if ($metadata !== null) {
            return $metadata;
        }
        
        // Redis cache: Shares metadata across all workers (1 hour TTL)
        $cacheKey = 'ec2_metadata_' . gethostname();
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        
        if ($cached) {
            $metadata = $cached;
            return $metadata;
        }
        
        try {
            // EC2 IMDSv2 (more secure) - only called once per hour per server
            $token = $this->getIMDSv2Token();
            
            if ($token) {
                $instanceId = $this->fetchMetadata('instance-id', $token);
                $availabilityZone = $this->fetchMetadata('placement/availability-zone', $token);
                
                $metadata = [$instanceId, $availabilityZone];
                
                // Cache for 1 hour
                \Illuminate\Support\Facades\Cache::put($cacheKey, $metadata, 3600);
                
                return $metadata;
            }
        } catch (Exception $e) {
            // Fallback for local development
        }
        
        // Fallback for local/non-EC2 environments
        $metadata = [gethostname() ?: 'unknown', 'local'];
        
        // Cache fallback value too (5 minutes)
        \Illuminate\Support\Facades\Cache::put($cacheKey, $metadata, 300);
        
        return $metadata;
    }

    protected function getIMDSv2Token(): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'PUT',
                    'header' => 'X-aws-ec2-metadata-token-ttl-seconds: 21600',
                    'timeout' => 1,
                ],
            ]);
            
            $token = @file_get_contents(
                'http://169.254.169.254/latest/api/token',
                false,
                $context
            );
            
            return $token ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function fetchMetadata(string $path, string $token): string
    {
        $context = stream_context_create([
            'http' => [
                'header' => "X-aws-ec2-metadata-token: $token",
                'timeout' => 1,
            ],
        ]);
        
        $data = @file_get_contents(
            "http://169.254.169.254/latest/meta-data/$path",
            false,
            $context
        );
        
        return trim($data ?: '');
    }

    /**
     * Collect and write metrics directly to ClickHouse.
     * 
     * @param array<string, mixed> $metrics
     */
    public function collectDirect(array $metrics): void
    {
        if (!$this->enabled || !$this->shouldSample()) {
            return;
        }

        try {
            // Use the fixed getClient() method that connects to the correct API metrics server
            $client = $this->getClient();
            
            if (!$client) {
                Log::channel('daily')->warning('ClickHouse client not available');
                return;
            }
            
            // Prepare row in column order expected by api_requests schema
            // See DESCRIBE TABLE Api_metrices.api_requests
            $row = [
                $this->domainName,
                $metrics['endpoint'] ?? 'unknown',
                $metrics['method'] ?? 'unknown',
                (int) ($metrics['status_code'] ?? 0),
                (float) ($metrics['response_time_ms'] ?? 0),
                (float) ($metrics['memory_usage_mb'] ?? 0),
                (float) ($metrics['peak_memory_mb'] ?? 0),
                (float) ($metrics['cpu_usage_percent'] ?? 0),
                (float) ($metrics['request_size_kb'] ?? 0),
                (float) ($metrics['response_size_kb'] ?? 0),
                (int) ($metrics['user_id'] ?? 0),
                (string) ($metrics['ip_address'] ?? ''),
                (string) ($metrics['user_agent_hash'] ?? ''),
                $this->instanceId,
                $this->availabilityZone,
                // Handle both Unix timestamp (int) and Carbon object properly
                $this->formatTimestamp($metrics['timestamp'] ?? time()),
            ];
            
            // Use standard insert API: table name + rows + column list
            $client->insert("{$this->database}.api_requests", [$row], [
                'domain_name',
                'endpoint',
                'method',
                'status_code',
                'response_time_ms',
                'memory_usage_mb',
                'peak_memory_mb',
                'cpu_usage_percent',
                'request_size_kb',
                'response_size_kb',
                'user_id',
                'ip_address',
                'user_agent_hash',
                'instance_id',
                'availability_zone',
                'timestamp',
            ]);
            
        } catch (Exception $e) {
            // Silent failure to protect API performance
            // Metrics are non-critical analytics data - don't interrupt API responses
            if (config('app.debug')) {
                Log::channel('daily')->debug('ClickHouse write failed', [
                    'error' => $e->getMessage(),
                    'endpoint' => $metrics['endpoint'] ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * Batch write multiple metrics to ClickHouse (more efficient).
     * 
     * @param array<array<string, mixed>> $metricsArray
     */
    public function collectBatch(array $metricsArray): void
    {
        if (!$this->enabled || empty($metricsArray)) {
            return;
        }

        try {
            $client = $this->getClient();
            
            if (!$client) {
                Log::channel('daily')->warning('ClickHouse metrics client not available');
                return;
            }
            
            $rows = [];
            foreach ($metricsArray as $metrics) {
                $rows[] = [
                    $this->domainName,
                    $metrics['endpoint'] ?? 'unknown',
                    $metrics['method'] ?? 'unknown',
                    (int) ($metrics['status_code'] ?? 0),
                    (float) ($metrics['response_time_ms'] ?? 0),
                    (float) ($metrics['memory_usage_mb'] ?? 0),
                    (float) ($metrics['peak_memory_mb'] ?? 0),
                    (float) ($metrics['cpu_usage_percent'] ?? 0),
                    (float) ($metrics['request_size_kb'] ?? 0),
                    (float) ($metrics['response_size_kb'] ?? 0),
                    (int) ($metrics['user_id'] ?? 0),
                    (string) ($metrics['ip_address'] ?? ''),
                    (string) ($metrics['user_agent_hash'] ?? ''),
                    $this->instanceId,
                    $this->availabilityZone,
                    $this->formatTimestamp($metrics['timestamp'] ?? time()),
                ];
            }
            
            // Batch insert using standard insert API
            $client->insert("{$this->database}.api_requests", $rows, [
                'domain_name',
                'endpoint',
                'method',
                'status_code',
                'response_time_ms',
                'memory_usage_mb',
                'peak_memory_mb',
                'cpu_usage_percent',
                'request_size_kb',
                'response_size_kb',
                'user_id',
                'ip_address',
                'user_agent_hash',
                'instance_id',
                'availability_zone',
                'timestamp',
            ]);
            
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse batch write failed', [
                'error' => $e->getMessage(),
                'count' => count($metricsArray),
            ]);
            
            throw $e;
        }
    }

    /**
     * Determine if this request should be sampled.
     */
    protected function shouldSample(): bool
    {
        // Convert rate to percentage if needed (1.0 = 100%)
        $rate = $this->samplingRate > 1 ? $this->samplingRate : $this->samplingRate * 100;

        // Adaptive sampling removed - was broken (queries ClickHouse on every request)
        // Use fixed sampling rate from config instead
        // To enable sampling: set API_METRICS_SAMPLING_RATE=0.1 for 10% sampling

        return rand(1, 100) <= $rate;
    }

    /**
     * Get ClickHouse client instance for API metrics.
     * Uses api-performance.clickhouse config instead of the default clickhouse config.
     * This ensures we connect to the correct ClickHouse server for metrics storage.
     * 
     * @return \ClickHouseDB\Client|null
     */
    protected function getClient(): ?\ClickHouseDB\Client
    {
        try {
            $host = config('api-performance.clickhouse.host');
            $port = config('api-performance.clickhouse.port');
            $database = config('api-performance.clickhouse.database');
            $username = config('api-performance.clickhouse.username');
            $password = config('api-performance.clickhouse.password');
            $protocol = config('api-performance.clickhouse.protocol');
            $timeout = config('api-performance.clickhouse.timeout');
            $connectTimeout = config('api-performance.clickhouse.connect_timeout');

            // Validate required configuration
            if (empty($host) || empty($port) || empty($database) || empty($username) || empty($password)) {
                Log::channel('daily')->warning('ClickHouse API metrics configuration is incomplete');
                return null;
            }

            $config = [
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'https' => $protocol === 'https',
                'database' => $database,
                'timeout' => (float) $timeout,
                'connect_timeout' => (float) $connectTimeout,
            ];

            $client = new \ClickHouseDB\Client($config);
            
            // Test connection
            $client->ping();
            
            // Explicitly set the database to ensure we're using the correct one
            $client->database($database);
            
            return $client;
            
        } catch (\Exception $e) {
            Log::channel('daily')->error('Failed to connect to ClickHouse for API metrics', [
                'error' => $e->getMessage(),
                'host' => config('api-performance.clickhouse.host'),
            ]);
            return null;
        }
    }
    
    /**
     * Safely escape domain names for ClickHouse queries.
     * Strict validation for tenant isolation security.
     * 
     * @param string $domainName Domain name to escape
     * @return string Escaped and quoted string literal
     */
    protected function escapeLiteral(string $domainName): string
    {
        // Strict validation for domain names only
        if (!preg_match('/^[a-zA-Z0-9.\-_]+$/', $domainName)) {
            throw new \InvalidArgumentException("Invalid domain name format: {$domainName}");
        }
        
        // Escape single quotes and backslashes for ClickHouse
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $domainName);
        return "'{$escaped}'";
    }

    /**
     * Format timestamp to ClickHouse-compatible string IN UTC.
     * ClickHouse stores DateTime in UTC and uses now() in UTC, so we must convert.
     * Handles both Unix timestamp (int) and Carbon objects.
     * 
     * @param mixed $timestamp Unix timestamp (int), Carbon object, or null
     * @return string Y-m-d H:i:s formatted timestamp in UTC
     */
    protected function formatTimestamp($timestamp): string
    {
        // Handle Carbon objects - convert to UTC
        if ($timestamp instanceof \Carbon\Carbon || $timestamp instanceof \Illuminate\Support\Carbon) {
            return $timestamp->clone()->utc()->format('Y-m-d H:i:s');
        }
        
        // Handle Unix timestamp (int or numeric string) - gmdate for UTC
        if (is_numeric($timestamp)) {
            return gmdate('Y-m-d H:i:s', (int) $timestamp);
        }
        
        // Handle string dates (try to parse) - convert to UTC
        if (is_string($timestamp)) {
            $parsed = strtotime($timestamp);
            if ($parsed !== false) {
                return gmdate('Y-m-d H:i:s', $parsed);
            }
            // If strtotime fails, fall through to default
        }
        
        // Default: current time in UTC
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Test ClickHouse connection.
     */
    public function testConnection(): bool
    {
        try {
            $client = $this->getClient();
            return $client !== null;
        } catch (Exception $e) {
            Log::channel('daily')->error('ClickHouse health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

