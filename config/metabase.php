<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Metabase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for Metabase embedded dashboards and reports.
    |
    */

    'site_url' => env('METABASE_SITE_URL'),

    'secret_key' => env('METABASE_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    |
    | Default expiration time for embedded tokens in minutes.
    |
    */
    'token_expiration_minutes' => env('METABASE_TOKEN_EXPIRATION_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Default Dashboard Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for embedded dashboards.
    |
    */
    'default_settings' => [
        'bordered' => true,
        'titled' => true,
        // 'theme' => null, // Remove theme parameter entirely to use default light theme
    ],
];
