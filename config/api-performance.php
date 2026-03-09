<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration controls the API performance monitoring system.
    | Adjust these settings based on your infrastructure capacity and
    | monitoring requirements.
    |
    */

    'enabled' => env('API_PERFORMANCE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | ClickHouse-based metrics storage configuration.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | ClickHouse Configuration
    |--------------------------------------------------------------------------
    |
    | ClickHouse configuration for centralized metrics storage across all 54 tenants.
    | All metrics are stored in a single ClickHouse database with tenant isolation.
    |
    */
    'clickhouse' => [
        'enabled' => env('CLICKHOUSE_METRICS_ENABLED', false),
        'host' => env('CLICKHOUSE_METRICS_HOST', 'p5f0vtns9z.westus3.azure.clickhouse.cloud'),
        'port' => env('CLICKHOUSE_METRICS_PORT', 8443),
        'database' => env('CLICKHOUSE_METRICS_DATABASE', 'Api_metrices'),
        'username' => env('CLICKHOUSE_METRICS_USERNAME', 'default'),
        'password' => env('CLICKHOUSE_METRICS_PASSWORD', ''),
        'protocol' => env('CLICKHOUSE_METRICS_PROTOCOL', 'https'),
        'domain_name' => env('DOMAIN_NAME', 'unknown'),  // CRITICAL: Tenant identifier
        'timeout' => env('CLICKHOUSE_METRICS_TIMEOUT', 5),
        'connect_timeout' => env('CLICKHOUSE_METRICS_CONNECT_TIMEOUT', 10),
        'batch_size' => env('CLICKHOUSE_METRICS_BATCH_SIZE', 1000),
        'retention_days' => env('CLICKHOUSE_METRICS_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Collection Settings
    |--------------------------------------------------------------------------
    |
    | Control what data is collected and how frequently.
    |
    */
    'collection' => [
        // Sampling rate (0.0 to 1.0) - Use 0.1 for 10% sampling in high traffic
        'sampling_rate' => env('API_METRICS_SAMPLING_RATE', 1.0),

        // Buffer size before triggering batch processing
        'buffer_size' => env('API_METRICS_BUFFER_SIZE', 100),

        // Maximum memory usage for buffering (MB)
        'max_buffer_memory_mb' => env('API_METRICS_MAX_BUFFER_MB', 50),

        // Collect detailed timing breakdowns
        'detailed_timing' => env('API_METRICS_DETAILED_TIMING', false),

        // Collect SQL query metrics
        'collect_db_metrics' => env('API_METRICS_COLLECT_DB', false),

        // Collect cache hit/miss metrics
        'collect_cache_metrics' => env('API_METRICS_COLLECT_CACHE', false),

        // Use direct database writes (no buffering/queues)
        'direct_writes' => env('API_METRICS_DIRECT_WRITES', true),

        // High traffic adaptive sampling
        'adaptive_sampling' => [
            'enabled' => env('API_METRICS_ADAPTIVE_SAMPLING', true),
            'request_threshold_per_minute' => 1000,  // Threshold to trigger reduced sampling
            'reduced_sampling_rate' => 0.25,         // Sample 25% during high traffic
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Define what constitutes slow/problematic API performance.
    | These thresholds trigger alerts and special tracking.
    |
    */
    'thresholds' => [
        'response_time' => [
            'warning_ms' => env('API_PERFORMANCE_WARNING_MS', 1000),   // 1 second
            'critical_ms' => env('API_PERFORMANCE_CRITICAL_MS', 5000), // 5 seconds
            'timeout_ms' => env('API_PERFORMANCE_TIMEOUT_MS', 30000),  // 30 seconds
        ],
        'memory_usage' => [
            'warning_mb' => env('API_PERFORMANCE_MEMORY_WARNING_MB', 128),
            'critical_mb' => env('API_PERFORMANCE_MEMORY_CRITICAL_MB', 256),
        ],
        'cpu_usage' => [
            'warning_percent' => env('API_PERFORMANCE_CPU_WARNING', 70),
            'critical_percent' => env('API_PERFORMANCE_CPU_CRITICAL', 90),
        ],
        'error_rate' => [
            'warning_percent' => env('API_PERFORMANCE_ERROR_WARNING', 5),
            'critical_percent' => env('API_PERFORMANCE_ERROR_CRITICAL', 10),
        ],
        'throughput' => [
            'min_requests_per_minute' => env('API_PERFORMANCE_MIN_THROUGHPUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusion Rules
    |--------------------------------------------------------------------------
    |
    | Paths, patterns, and conditions to exclude from monitoring.
    |
    */
    'exclusions' => [
        'paths' => [
            'api-performance',
            'health',
            'metrics',
            '_debugbar',
            'telescope',
            'horizon',
            'nova',
            'static',
            'assets',
            'css',
            'js',
            'images',
            'favicon.ico',
        ],

        'route_patterns' => [
            '*/ping',
            '*/status',
            '*/health-check',
        ],

        'methods' => [
            // 'OPTIONS', // Uncomment to exclude OPTIONS requests
        ],

        'status_codes' => [
            // 304, // Uncomment to exclude 304 Not Modified
        ],

        // Skip monitoring for requests faster than this (microseconds)
        'min_response_time_us' => env('API_METRICS_MIN_RESPONSE_TIME', 1000), // 1ms

        // Skip monitoring during these hours (24-hour format)
        'maintenance_hours' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep different types of metrics data.
    |
    */
    'retention' => [
        'raw_data_days' => env('API_METRICS_RAW_RETENTION_DAYS', 7),
        'hourly_stats_days' => env('API_METRICS_HOURLY_RETENTION_DAYS', 90),
        'daily_stats_days' => env('API_METRICS_DAILY_RETENTION_DAYS', 365),
        'slow_endpoints_days' => env('API_METRICS_SLOW_RETENTION_DAYS', 30),

        // Auto-cleanup settings
        'auto_cleanup' => true,
        'cleanup_frequency' => 'daily', // daily, weekly, monthly
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Settings
    |--------------------------------------------------------------------------
    |
    | How and when to aggregate raw metrics into summary statistics.
    |
    */
    'aggregation' => [
        // Automatically aggregate hourly stats
        'auto_hourly_aggregation' => true,
        'hourly_aggregation_schedule' => '5 * * * *', // Every hour at 5 minutes past

        // Automatically aggregate daily stats
        'auto_daily_aggregation' => true,
        'daily_aggregation_schedule' => '10 1 * * *', // Daily at 1:10 AM

        // Pre-calculate percentiles
        'calculate_percentiles' => [50, 75, 90, 95, 99],

        // Batch size for aggregation processing
        'aggregation_batch_size' => 10000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the monitoring dashboard interface.
    |
    */
    'dashboard' => [
        'enabled' => env('API_PERFORMANCE_DASHBOARD_ENABLED', true),
        'route_prefix' => env('API_PERFORMANCE_ROUTE_PREFIX', 'api-performance'),
        'middleware' => ['web', 'auth'], // Add appropriate middleware

        // Real-time updates
        'auto_refresh_seconds' => env('API_PERFORMANCE_AUTO_REFRESH', 30),
        'real_time_enabled' => env('API_PERFORMANCE_REAL_TIME', true),

        // Dashboard features
        'show_user_metrics' => env('API_PERFORMANCE_SHOW_USER_METRICS', false),
        'show_ip_metrics' => env('API_PERFORMANCE_SHOW_IP_METRICS', false),
        'enable_endpoint_search' => true,
        'enable_time_filtering' => true,
        'enable_export' => true,

        // Chart settings
        'chart_max_points' => 100,
        'chart_colors' => [
            'primary' => '#007bff',
            'success' => '#28a745',
            'warning' => '#ffc107',
            'danger' => '#dc3545',
            'info' => '#17a2b8',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Set up alerts for performance issues.
    |
    */
    'alerts' => [
        'enabled' => env('API_PERFORMANCE_ALERTS_ENABLED', false),

        'channels' => [
            'slack' => env('API_PERFORMANCE_SLACK_WEBHOOK'),
            'email' => env('API_PERFORMANCE_ALERT_EMAIL'),
            'log' => true,
        ],

        'alert_frequency' => [
            'slow_endpoint' => '5 minutes', // Don't spam alerts
            'high_error_rate' => '10 minutes',
            'memory_threshold' => '15 minutes',
        ],

        'alert_thresholds' => [
            'endpoints_over_threshold' => 5, // Alert if 5+ endpoints are slow
            'system_error_rate' => 15, // Alert if overall error rate > 15%
            'system_avg_response_time' => 2000, // Alert if system avg > 2s
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Override settings based on application environment.
    |
    */
    'environment_overrides' => [
        'local' => [
            'collection' => [
                'sampling_rate' => 1.0, // Monitor everything in local
                'buffer_size' => 10, // Smaller buffer for immediate feedback
            ],
            'dashboard' => [
                'auto_refresh_seconds' => 5, // Faster refresh for development
            ],
        ],

        'staging' => [
            'collection' => [
                'sampling_rate' => 0.5, // 50% sampling in staging
            ],
            'thresholds' => [
                'response_time' => [
                    'warning_ms' => 2000, // More lenient in staging
                    'critical_ms' => 10000,
                ],
            ],
        ],

        'production' => [
            'collection' => [
                'sampling_rate' => 0.1, // 10% sampling in production for high traffic
                'buffer_size' => 500, // Larger buffer for efficiency
            ],
            'alerts' => [
                'enabled' => true, // Enable alerts in production only
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Advanced Features
    |--------------------------------------------------------------------------
    |
    | Advanced monitoring features for enterprise usage.
    |
    */
    'advanced' => [
        // Track API versioning performance
        'track_api_versions' => env('API_PERFORMANCE_TRACK_VERSIONS', false),

        // Distributed tracing correlation
        'correlation_id_header' => 'X-Correlation-ID',

        // Custom context collection
        'custom_context_callback' => null, // Callback function for custom data

        // Export metrics to external systems
        'export_to_external' => [
            'prometheus' => env('API_PERFORMANCE_EXPORT_PROMETHEUS', false),
            'datadog' => env('API_PERFORMANCE_EXPORT_DATADOG', false),
            'newrelic' => env('API_PERFORMANCE_EXPORT_NEWRELIC', false),
        ],
    ],
];
