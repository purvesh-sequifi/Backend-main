<?php

return [
    // Master switch
    'enabled' => env('LEGACY_LOGS_SYNC_ENABLED', true),

    // Queue settings
    'queue' => env('LEGACY_LOGS_SYNC_QUEUE', 'default'),
    'job_attempts' => (int) env('LEGACY_LOGS_SYNC_ATTEMPTS', 3),
    'job_backoff' => (int) env('LEGACY_LOGS_SYNC_BACKOFF', 60), // seconds

    // Query behavior
    'max_sample_ids' => (int) env('LEGACY_LOGS_SYNC_MAX_SAMPLE_IDS', 50),

    // Default start date for backfill window (e.g., historical sync start)
    // If null, will default to March 1st of current year
    'default_start_date' => env('LEGACY_LOGS_SYNC_DEFAULT_START_DATE', null),

    // Chunk size for processing legacy rows in jobs
    'chunk' => (int) env('LEGACY_LOGS_SYNC_CHUNK', 500),

    // Behavior flags for SalesMaster updates/creation from legacy logs
    'update_sales_master_by_email' => (bool) env('LEGACY_LOGS_UPDATE_SM_BY_EMAIL', true),
    'create_missing_sales_master' => (bool) env('LEGACY_LOGS_CREATE_MISSING_SM', false),

    // SaleMaster job dispatch settings used after inserting legacy rows with import_to_sales = '0'
    'sale_master_queue' => env('LEGACY_LOGS_SALE_MASTER_QUEUE', 'sales-import'),
    'sale_master_chunk' => (int) env('LEGACY_LOGS_SALE_MASTER_CHUNK', 100),

    // Fields to watch for update triggers on User model
    'trigger_fields' => [
        'email',
        'work_email',
        'period_of_agreement_start_date', // treated as hire date
    ],
];
