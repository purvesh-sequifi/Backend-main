<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ClickHouseConnectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * ClickHouseCheckWrites Command
 * 
 * Checks if metrics are being written to ClickHouse successfully.
 */
class ClickHouseCheckWrites extends Command
{
    protected $signature = 'clickhouse:check-writes {--last=10 : Number of minutes to check}';
    protected $description = 'Check if metrics are being written to ClickHouse';

    public function handle(): int
    {
        $minutes = (int) $this->option('last');
        $this->info("Checking ClickHouse writes for last {$minutes} minutes...");
        $this->newLine();

        // Check configuration (ClickHouse-only architecture)
        $clickhouseEnabled = config('api-performance.clickhouse.enabled', false);
        $domainName = config('api-performance.clickhouse.domain_name', config('app.domain_name', 'unknown'));
        $database = config('api-performance.clickhouse.database', 'Api_metrices');

        $this->info('ClickHouse Metrics Write Verification');
        $this->line('  ClickHouse Enabled: ' . ($clickhouseEnabled ? 'Yes' : 'No'));
        $this->line('  Domain Name: ' . $domainName);
        $this->line('  Database: ' . $database);
        $this->newLine();

        if (!$clickhouseEnabled) {
            $this->warn('⚠️  ClickHouse is not enabled. Set CLICKHOUSE_METRICS_ENABLED=true');
            return Command::FAILURE;
        }

        // Test ClickHouse connection
        try {
            $collector = app(\App\Services\ClickHouseApiMetricsCollector::class);
            if (!$collector->testConnection()) {
                $this->error('❌ ClickHouse metrics connection failed');
                return Command::FAILURE;
            }

            $this->info('✅ ClickHouse metrics connection successful');
            $this->newLine();

            // Query recent writes using data source
            $this->info('Querying recent writes...');
            
            $dataSource = \App\Services\MetricsDataSourceFactory::create();
            $stats = $dataSource->getOverviewStats("{$minutes}m");
            
            // For detailed query, use direct client
            $config = [
                'host' => config('api-performance.clickhouse.host'),
                'port' => (int) config('api-performance.clickhouse.port'),
                'username' => config('api-performance.clickhouse.username'),
                'password' => config('api-performance.clickhouse.password'),
                'https' => config('api-performance.clickhouse.protocol') === 'https',
                'database' => $database,
                'timeout' => (float) config('api-performance.clickhouse.timeout'),
                'connect_timeout' => (float) config('api-performance.clickhouse.connect_timeout'),
            ];
            
            $client = new \ClickHouseDB\Client($config);
            
            // Safely escape domain name to prevent SQL injection
            $safeDomainName = $this->escapeLiteral($domainName);
            
            $query = "
                SELECT count() as cnt
                FROM {$database}.api_requests
                WHERE domain_name = {$safeDomainName}
                  AND timestamp >= now() - INTERVAL {$minutes} MINUTE
            ";
            
            $result = $client->select($query);
            $row = $result->rows()[0] ?? [];
            $totalWrites = (int) ($row['cnt'] ?? 0);

            if ($totalWrites > 0) {
                $this->info("✅ Found {$totalWrites} metrics written in last {$minutes} minutes");
                $ratePerMinute = round($totalWrites / $minutes, 2);
                $this->line("   Write rate: {$ratePerMinute} metrics/minute");
                $this->newLine();
                
                // Show sample data
                $sampleQuery = "
                    SELECT 
                        endpoint, 
                        method, 
                        avg(response_time_ms) as avg_ms,
                        count() as cnt
                    FROM {$database}.api_requests
                    WHERE domain_name = {$safeDomainName}
                      AND timestamp >= now() - INTERVAL {$minutes} MINUTE
                    GROUP BY endpoint, method
                    ORDER BY cnt DESC
                    LIMIT 5
                ";
                
                $sampleResult = $client->select($sampleQuery);
                if (count($sampleResult->rows()) > 0) {
                    $this->info('Top 5 Endpoints:');
                    $this->table(
                        ['Endpoint', 'Method', 'Avg Response (ms)', 'Count'],
                        array_map(fn($row) => [
                            $row['endpoint'] ?? '',
                            $row['method'] ?? '',
                            round($row['avg_ms'] ?? 0, 2),
                            $row['cnt'] ?? 0,
                        ], $sampleResult->rows())
                    );
                }
                
                // Check Redis fallback buffer
                $this->checkRedisBuffer();
                
                return Command::SUCCESS;
            } else {
                $this->warn("⚠️  No metrics found in last {$minutes} minutes");
                $this->line('This could mean:');
                $this->line('  1. No API requests were made');
                $this->line('  2. Sampling rate is very low');
                $this->line('  3. Writes are failing (check logs)');
                $this->newLine();
                
                $this->checkRedisBuffer();
                
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error checking writes');
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function checkRedisBuffer(): void
    {
        try {
            $bufferSize = Redis::connection('cache')->llen('clickhouse:fallback:buffer');
            
            if ($bufferSize > 0) {
                $this->warn("⚠️  Redis fallback buffer has {$bufferSize} pending metrics");
                $this->line('  These are metrics that failed to write to ClickHouse');
            } else {
                $this->info('✅ Redis fallback buffer is empty');
            }
            
        } catch (\Exception $e) {
            $this->line('  (Could not check Redis buffer)');
        }
        
        $this->newLine();
    }

    /**
     * Safely escape domain names for ClickHouse queries.
     * Strict validation for tenant isolation security.
     */
    protected function escapeLiteral(string $value): string
    {
        // Strict validation for domain names only
        if (!preg_match('/^[a-zA-Z0-9.\-_]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid domain name format: {$value}");
        }
        
        // Escape single quotes and backslashes for ClickHouse
        $escaped = str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
        return "'{$escaped}'";
    }

}

