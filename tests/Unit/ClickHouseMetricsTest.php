<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClickHouseApiMetricsCollector;
use App\Services\ClickHouseConnectionService;
use App\Services\DataSources\ClickHouseMetricsDataSource;
use App\Services\MetricsDataSourceFactory;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;

/**
 * ClickHouseMetricsTest
 * 
 * Unit tests for ClickHouse API metrics functionality.
 * 
 * Run with: php artisan test --filter ClickHouseMetricsTest
 */
class ClickHouseMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set ClickHouse configuration for testing
        Config::set('api-performance.clickhouse.enabled', true);
        Config::set('api-performance.clickhouse.database', 'Api_metrices');
        Config::set('api-performance.clickhouse.domain_name', 'test.sequifi.com');
    }

    /**
     * Test: ClickHouse connection is successful
     * NOTE: This is an integration test - requires ClickHouse credentials in .env
     */
    public function test_clickhouse_connection_successful(): void
    {
        if (!config('api-performance.clickhouse.enabled')) {
            $this->markTestSkipped('ClickHouse integration not enabled (set CLICKHOUSE_METRICS_ENABLED=true)');
        }
        
        $result = ClickHouseConnectionService::ping(3, 10, false);
        
        $this->assertTrue($result, 'ClickHouse connection should be successful');
    }

    /**
     * Test: ClickHouse collector can be instantiated
     */
    public function test_clickhouse_collector_instantiation(): void
    {
        Config::set('api-performance.clickhouse.enabled', true);
        
        $collector = new ClickHouseApiMetricsCollector();
        
        $this->assertInstanceOf(ClickHouseApiMetricsCollector::class, $collector);
    }

    /**
     * Test: ClickHouse data source can be created via factory
     */
    public function test_factory_creates_clickhouse_data_source(): void
    {
        $dataSource = MetricsDataSourceFactory::create();
        
        $this->assertInstanceOf(ClickHouseMetricsDataSource::class, $dataSource);
        $this->assertEquals('clickhouse', $dataSource->getType());
    }

    /**
     * Test: Metrics collector properly formats data
     * NOTE: This is an integration test - requires ClickHouse credentials in .env
     */
    public function test_metrics_data_formatting(): void
    {
        if (!config('api-performance.clickhouse.enabled')) {
            $this->markTestSkipped('ClickHouse integration not enabled (set CLICKHOUSE_METRICS_ENABLED=true)');
        }
        
        $collector = new ClickHouseApiMetricsCollector();
        
        $testMetrics = [
            'endpoint' => '/api/test',
            'method' => 'GET',
            'status_code' => 200,
            'response_time_ms' => 150.5,
            'memory_usage_mb' => 2.3,
            'timestamp' => time(),
        ];
        
        // This would throw exception if data format is wrong
        $collector->collectDirect($testMetrics);
        
        $this->assertTrue(true);
    }

    /**
     * Test: Data source returns empty stats when no data
     * NOTE: This is an integration test - requires ClickHouse credentials in .env
     */
    public function test_data_source_returns_empty_stats_gracefully(): void
    {
        if (!config('api-performance.clickhouse.enabled')) {
            $this->markTestSkipped('ClickHouse integration not enabled (set CLICKHOUSE_METRICS_ENABLED=true)');
        }
        
        $dataSource = new ClickHouseMetricsDataSource();
        
        $stats = $dataSource->getOverviewStats('1h');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('avg_response_time_ms', $stats);
        $this->assertArrayHasKey('error_count', $stats);
    }

    /**
     * Test: Configuration validation (does not require ClickHouse connection)
     */
    public function test_configuration_values_are_set(): void
    {
        $this->assertNotNull(config('api-performance.clickhouse'));
        $this->assertIsArray(config('api-performance.clickhouse'));
        $this->assertEquals('Api_metrices', config('api-performance.clickhouse.database'));
    }

    /**
     * Test: Collector respects enabled flag
     */
    public function test_collector_respects_enabled_flag(): void
    {
        Config::set('api-performance.clickhouse.enabled', false);
        
        $collector = new ClickHouseApiMetricsCollector();
        
        // When disabled, collectDirect should return early without throwing
        $testMetrics = ['endpoint' => '/test', 'method' => 'GET'];
        $collector->collectDirect($testMetrics);
        
        $this->assertTrue(true, 'Should not throw when disabled');
    }
}

