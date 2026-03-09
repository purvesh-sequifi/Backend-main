<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FieldRoutes Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for the FieldRoutes
    | data synchronization system.
    |
    */

    // Database tables
    'tables' => [
        'raw_data' => 'FieldRoutes_Raw_Data',
        'customer_data' => 'FieldRoutes_Customer_Data',
        'appointment_data' => 'FieldRoutes_Appointment_Data',
        'legacy_history' => 'legacy_api_raw_data_histories',
    ],

    // Sync settings
    'sync' => [
        'batch_size' => 250, // Number of records to process in each batch
        'timeout' => 1800,   // Maximum execution time in seconds (30 minutes)
        'retry_attempts' => 3, // Number of retry attempts for failed records
        'retry_delay' => 300,  // Delay between retries in seconds (5 minutes)
    ],

    // Domain-specific settings
    'domains' => [
        'whiteknight' => [
            'cutoff_date' => '2025-03-01',
            'import_rules' => [
                'before_cutoff' => 2,
                'after_cutoff' => 0,
            ],
        ],
        'moxie' => [
            'cutoff_date' => '2024-11-01',
            'import_rules' => [
                'before_cutoff' => 2,
                'after_cutoff' => 0,
            ],
        ],
        'default' => [
            'cutoff_date' => '2024-12-31',
            'import_rules' => [
                'before_cutoff' => 2,
                'after_cutoff' => 0,
            ],
        ],
    ],

    // Logging settings
    'logging' => [
        'enabled' => true,
        'channel' => 'fieldroutes',
        'level' => env('FIELDROUTES_LOG_LEVEL', 'info'),
        'file' => storage_path('logs/fieldroutes-sync.log'),
    ],

    // Product settings
    'products' => [
        'default_id' => env('FIELDROUTES_DEFAULT_PRODUCT_ID', null),
        'code_cleanup_pattern' => '/[^a-zA-Z0-9]/',
    ],
];
