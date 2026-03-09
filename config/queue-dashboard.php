<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Dashboard Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all the rate limiting settings for the queue dashboard.
    | Different operations have different rate limits based on their security
    | impact and potential for abuse.
    |
    | NOTE: Auto-refresh has been disabled to prevent server overload.
    | Users must manually refresh the dashboard using the refresh button.
    |
    | QUICK CUSTOMIZATION EXAMPLES:
    |
    | 1. Make bulk operations more restrictive:
    |    'bulk' => ['max_attempts' => 3, 'decay_minutes' => 10]
    |
    | 2. Allow more read operations for heavy monitoring:
    |    'read' => ['max_attempts' => 300, 'decay_minutes' => 1]
    |
    | 3. Restrict critical operations further:
    |    'critical' => ['max_attempts' => 1, 'decay_minutes' => 15]
    |
    | 4. Disable rate limiting for local development:
    |    Set all 'max_attempts' to 9999 in 'local' environment overrides
    |
    */

    'queues' => [
        'default',
        'sales-process-arcsite',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Detection Mode
    |--------------------------------------------------------------------------
    |
    | This setting controls how queues are detected and displayed in the dashboard.
    |
    | Available modes:
    | - 'worker_only': Show only queues that have running workers (RECOMMENDED)
    | - 'all_discovered': Show all discovered queues from database, config, supervisor, etc.
    | - 'active_jobs_only': Show only queues that currently have jobs or recent failures
    |
    */
    'queue_detection_mode' => env('QUEUE_DASHBOARD_DETECTION_MODE', 'worker_only'),

    'rate_limits' => [

        /*
        |--------------------------------------------------------------------------
        | Dashboard UI Rate Limits
        |--------------------------------------------------------------------------
        |
        | Rate limits for accessing the main dashboard page and basic UI operations.
        | Default: 30 requests per minute (reduced to prevent server overload)
        |
        */
        'dashboard' => [
            'max_attempts' => 30,
            'decay_minutes' => 1,
            'description' => 'Dashboard page loads and UI interactions',
        ],

        /*
        |--------------------------------------------------------------------------
        | Read Operations Rate Limits
        |--------------------------------------------------------------------------
        |
        | Rate limits for read-only operations like statistics, job lists, etc.
        | These are generally safe operations that don't modify system state.
        | Default: 60 requests per minute (reduced from 300 to prevent server overload)
        |
        */
        'read' => [
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'description' => 'Statistics, job lists, performance data retrieval',
        ],

        /*
        |--------------------------------------------------------------------------
        | Write Operations Rate Limits
        |--------------------------------------------------------------------------
        |
        | Rate limits for individual write operations like retrying single jobs,
        | deleting individual jobs, etc.
        | Default: 20 requests per minute (reduced to prevent server overload)
        |
        */
        'write' => [
            'max_attempts' => 20,
            'decay_minutes' => 1,
            'description' => 'Individual job retry, delete, reset operations',
        ],

        /*
        |--------------------------------------------------------------------------
        | Bulk Operations Rate Limits
        |--------------------------------------------------------------------------
        |
        | Rate limits for bulk operations that can affect many jobs at once.
        | These operations are more dangerous and have stricter limits.
        | Default: 5 requests per 5 minutes
        |
        */
        'bulk' => [
            'max_attempts' => 5,
            'decay_minutes' => 5,
            'description' => 'Bulk retry, clear all failed, clear queue operations',
        ],

        /*
        |--------------------------------------------------------------------------
        | Critical Operations Rate Limits
        |--------------------------------------------------------------------------
        |
        | Rate limits for critical system operations like restarting workers.
        | These have the strictest limits due to their system-wide impact.
        | Default: 2 requests per 10 minutes
        |
        */
        'critical' => [
            'max_attempts' => 2,
            'decay_minutes' => 10,
            'description' => 'Worker restart and other critical system operations',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [

        /*
        | Whether to log rate limit violations for security monitoring
        | Default: true (recommended for security auditing)
        */
        'log_violations' => true,

        /*
        | Whether to include detailed headers in rate limit responses
        | Default: true (helps clients understand rate limiting)
        */
        'include_headers' => true,

        /*
        | Whether to use authenticated user ID for rate limiting (vs IP only)
        | Default: true (more accurate rate limiting per user)
        */
        'use_user_id' => true,

        /*
        | Custom rate limit violation message
        | Default: Generic security message
        */
        'violation_message' => 'Rate limit exceeded for queue dashboard operations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | Override rate limits for different environments. These settings will
    | merge with the base rate_limits configuration above.
    |
    | Example: In 'local' environment, bulk operations get 20 attempts instead of 5
    |
    */
    'environment_overrides' => [

        /*
        | Local Development - More permissive limits for easier testing
        */
        'local' => [
            'dashboard' => ['max_attempts' => 100],   // 100 req/min vs 30
            'read' => ['max_attempts' => 200],        // 200 req/min vs 60
            'write' => ['max_attempts' => 50],        // 50 req/min vs 20
            'bulk' => ['max_attempts' => 20],         // 20 req/5min vs 5
            'critical' => ['max_attempts' => 10],     // 10 req/10min vs 2
        ],

        /*
        | Testing Environment - Very high limits to avoid test interference
        */
        'testing' => [
            'dashboard' => ['max_attempts' => 1000],
            'read' => ['max_attempts' => 1000],
            'write' => ['max_attempts' => 1000],
            'bulk' => ['max_attempts' => 1000],
            'critical' => ['max_attempts' => 1000],
        ],

        /*
        | Staging Environment - Production-like with slightly higher limits for testing
        */
        'staging' => [
            'dashboard' => ['max_attempts' => 50],    // Slightly higher than production
            'read' => ['max_attempts' => 80],
            'write' => ['max_attempts' => 30],
            'bulk' => ['max_attempts' => 8],
            'critical' => ['max_attempts' => 3],
        ],

        /*
        | Production Environment - More conservative limits for production stability
        */
        'production' => [
            'dashboard' => ['max_attempts' => 20, 'decay_minutes' => 1],    // 20 req/min
            'read' => ['max_attempts' => 40, 'decay_minutes' => 1],         // 40 req/min
            'write' => ['max_attempts' => 10, 'decay_minutes' => 1],        // 10 req/min
            'bulk' => ['max_attempts' => 3, 'decay_minutes' => 5],          // 3 req/5min
            'critical' => ['max_attempts' => 1, 'decay_minutes' => 15],     // 1 req/15min
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Alerting
    |--------------------------------------------------------------------------
    */
    'monitoring' => [

        /*
        | Threshold for sending alerts about excessive rate limiting
        | Default: 50 violations per hour
        */
        'alert_threshold' => 50,

        /*
        | Time window for counting violations (minutes)
        | Default: 60 minutes
        */
        'alert_window' => 60,

        /*
        | Notification channels for rate limit alerts
        | Configure these in your .env file if you want alerting:
        | QUEUE_DASHBOARD_SLACK_WEBHOOK=your-webhook-url
        | QUEUE_DASHBOARD_ALERT_EMAIL=admin@yoursite.com
        */
        'notification_channels' => [
            'slack' => env('QUEUE_DASHBOARD_SLACK_WEBHOOK'),
            'email' => env('QUEUE_DASHBOARD_ALERT_EMAIL'),
        ],
    ],
];
