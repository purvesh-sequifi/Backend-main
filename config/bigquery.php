<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google BigQuery Integration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the Google BigQuery integration.
    | It includes settings for enabling/disabling the integration,
    | project configuration, and operational parameters.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | BigQuery Integration Status
    |--------------------------------------------------------------------------
    |
    | This option controls whether the BigQuery integration is enabled.
    | When disabled, no data will be sent to BigQuery.
    |
    */
    'enabled' => env('BIGQUERY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Project Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Cloud Project and BigQuery datasets.
    |
    */
    'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', ''),
    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS', storage_path('app/hawxd2d-7bc3b83f2cba.json')),
    'default_dataset' => env('BIGQUERY_DEFAULT_DATASET', ''),

    /*
    |--------------------------------------------------------------------------
    | Operation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings that control how the BigQuery integration operates.
    |
    */
    'batch_size' => env('BIGQUERY_BATCH_SIZE', 100),
    'timeout' => env('BIGQUERY_TIMEOUT', 60),
    'max_retries' => env('BIGQUERY_MAX_RETRIES', 3),
    'retry_delay' => env('BIGQUERY_RETRY_DELAY', 2), // seconds

    /*
    |--------------------------------------------------------------------------
    | Logging and Debugging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging and debugging the BigQuery integration.
    |
    */
    'debug' => env('BIGQUERY_DEBUG', false),
    'log_channel' => env('BIGQUERY_LOG_CHANNEL', 'stack'),
];
