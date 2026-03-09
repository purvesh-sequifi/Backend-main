<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * MetricsDataSource Interface
 * 
 * ClickHouse-based metrics storage interface.
 * Abstraction allows for future backend implementations if needed.
 */
interface MetricsDataSource
{
    /**
     * Get overview statistics for a given time range.
     * 
     * @param string $timeRange Time range (1h, 6h, 24h, 7d)
     * @return array<string, mixed>
     */
    public function getOverviewStats(string $timeRange): array;

    /**
     * Get top endpoints by request count.
     * 
     * @param string $timeRange Time range
     * @param int $limit Number of endpoints to return
     * @param string $sortBy Sort by ('requests' or 'response_time')
     * @return array<int, array<string, mixed>>
     */
    public function getTopEndpoints(string $timeRange, int $limit = 10, string $sortBy = 'requests'): array;

    /**
     * Get slow endpoints (response time > threshold).
     * 
     * @param string $timeRange Time range
     * @param float $thresholdMs Threshold in milliseconds
     * @param int $limit Number of endpoints to return
     * @return array<int, array<string, mixed>>
     */
    public function getSlowEndpoints(string $timeRange, float $thresholdMs = 1000.0, int $limit = 10): array;

    /**
     * Get performance history for time-series charts.
     * 
     * @param string $timeRange Time range
     * @return array<string, mixed>
     */
    public function getPerformanceHistory(string $timeRange): array;

    /**
     * Get error statistics.
     * 
     * @param string $timeRange Time range
     * @return array<string, mixed>
     */
    public function getErrorStats(string $timeRange): array;

    /**
     * Get endpoint-specific analytics.
     * 
     * @param string $endpoint Endpoint path
     * @param string|null $method HTTP method (optional)
     * @param string $timeRange Time range
     * @return array<string, mixed>
     */
    public function getEndpointAnalytics(string $endpoint, ?string $method, string $timeRange): array;

    /**
     * Get system health metrics.
     * 
     * @return array<string, mixed>
     */
    public function getSystemHealth(): array;

    /**
     * Check if data source is available and healthy.
     * 
     * @return bool
     */
    public function isHealthy(): bool;

    /**
     * Get data source type identifier.
     * 
     * @return string
     */
    public function getType(): string;

    /**
     * Get endpoints with error counts (4xx + 5xx status codes).
     * 
     * @param string $timeRange Time range
     * @param int $limit Number of endpoints to return
     * @return array<int, array<string, mixed>>
     */
    public function getEndpointsWithErrors(string $timeRange, int $limit = 10): array;
}

