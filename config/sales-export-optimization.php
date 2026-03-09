<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sales Export Optimization Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the optimized sales export system using
    | SalesControllerForSalesExport. These settings control performance,
    | chunking, and memory management for large dataset exports.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Chunk Processing Settings
    |--------------------------------------------------------------------------
    |
    | Controls how data is processed in chunks to avoid memory exhaustion
    |
    */
    'chunk_size' => env('SALES_EXPORT_CHUNK_SIZE', 1000),
    'large_export_threshold' => env('SALES_EXPORT_LARGE_THRESHOLD', 20000),
    'export_format' => env('SALES_EXPORT_FORMAT', 'auto'), // auto|xlsx|csv
    'csv_zip_on_large' => env('SALES_EXPORT_CSV_ZIP_ON_LARGE', true),

    /*
    |--------------------------------------------------------------------------
    | Memory Management
    |--------------------------------------------------------------------------
    |
    | Memory limits and monitoring for export operations
    |
    */
    'memory_limit' => env('SALES_EXPORT_MEMORY_LIMIT', '512M'),
    'memory_warning_threshold' => env('SALES_EXPORT_MEMORY_WARNING', 80), // Percentage
    'memory_warning_mb' => env('SALES_EXPORT_MEMORY_WARNING_MB', 800), // MB absolute warning
    'memory_critical_mb' => env('SALES_EXPORT_MEMORY_CRITICAL_MB', 1500), // MB critical limit

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring export performance and logging
    |
    */
    'enable_performance_logging' => env('SALES_EXPORT_PERFORMANCE_LOG', true),
    'log_slow_exports' => env('SALES_EXPORT_LOG_SLOW', true),
    'slow_export_threshold' => env('SALES_EXPORT_SLOW_THRESHOLD', 30), // seconds

    /*
    |--------------------------------------------------------------------------
    | Optimization Features
    |--------------------------------------------------------------------------
    |
    | Toggle various optimization features
    |
    */
    'use_optimized_queries' => env('SALES_EXPORT_OPTIMIZED_QUERIES', true),
    'enable_query_caching' => env('SALES_EXPORT_QUERY_CACHE', true),
    'cache_lookup_data' => env('SALES_EXPORT_CACHE_LOOKUPS', true),

    /*
    |--------------------------------------------------------------------------
    | File Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Excel file generation and storage
    |
    */
    'file_storage_disk' => env('SALES_EXPORT_STORAGE_DISK', 'public'),
    'file_cleanup_hours' => env('SALES_EXPORT_CLEANUP_HOURS', 24), // Auto-cleanup old exports

    /*
    |--------------------------------------------------------------------------
    | Pusher Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for real-time export completion notifications
    |
    */
    'pusher_enabled' => env('SALES_EXPORT_PUSHER_ENABLED', true),
    'pusher_channel_prefix' => env('SALES_EXPORT_PUSHER_PREFIX', 'sales_export'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Settings
    |--------------------------------------------------------------------------
    |
    | Fallback options when optimizations fail
    |
    */
    'fallback_to_job_queue' => env('SALES_EXPORT_FALLBACK_QUEUE', false),
    'max_records_direct_processing' => env('SALES_EXPORT_MAX_DIRECT', 50000),

    /*
    |--------------------------------------------------------------------------
    | Database Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Settings related to database query optimization
    |
    */
    'use_database_views' => env('SALES_EXPORT_USE_VIEWS', true),
    'optimize_joins' => env('SALES_EXPORT_OPTIMIZE_JOINS', true),
    'bulk_load_associations' => env('SALES_EXPORT_BULK_LOAD', true),

];
