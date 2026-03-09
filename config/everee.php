<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Everee API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Everee payment processing API integration.
    | Supports both 1099 contractor and W2 employee payment processing.
    |
    */

    /**
     * 1099 Contractor Configuration
     */
    '1099' => [
        'tenant_id' => env('EVEREE_TENANT_ID', ''),
        'api_key' => env('EVEREE_API_KEY', ''),
    ],

    /**
     * W2 Employee Configuration
     */
    'w2' => [
        'tenant_id' => env('W2_EVEREE_TENANT_ID', ''),
        'api_key' => env('W2_EVEREE_API_KEY', ''),
    ],

    /**
     * Everee API Base URL
     */
    'api_url' => env('EVEREE_API_URL', 'https://api-prod.everee.com'),

    /**
     * API Version
     */
    'api_version' => env('EVEREE_API_VERSION', 'v2'),

    /**
     * Enable/Disable Everee Integration
     */
    'enabled' => env('EVEREE_ENABLED', true),

    /**
     * Timeout for API requests (in seconds)
     */
    'timeout' => env('EVEREE_TIMEOUT', 30),

    /**
     * Number of retries for failed API calls
     */
    'retries' => env('EVEREE_RETRIES', 3),

];
