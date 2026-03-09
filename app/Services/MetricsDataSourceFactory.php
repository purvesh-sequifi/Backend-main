<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MetricsDataSource;
use App\Services\DataSources\ClickHouseMetricsDataSource;

/**
 * MetricsDataSourceFactory
 * 
 * Factory for ClickHouse metrics data source.
 * Simplified to ClickHouse-only architecture (SQLite removed).
 */
class MetricsDataSourceFactory
{
    /**
     * Create ClickHouse metrics data source.
     * 
     * @return MetricsDataSource
     */
    public static function create(): MetricsDataSource
    {
        return new ClickHouseMetricsDataSource();
    }

    /**
     * Check if ClickHouse is available and healthy.
     * 
     * @return bool
     */
    public static function isHealthy(): bool
    {
        try {
            $dataSource = new ClickHouseMetricsDataSource();
            return $dataSource->isHealthy();
        } catch (\Exception $e) {
            return false;
        }
    }
}

