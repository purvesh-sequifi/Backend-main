<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AWS S3 Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains AWS S3 configuration that needs to work with cached
    | config. All values are loaded from environment variables and will be
    | cached when running php artisan config:cache.
    |
    */

    // Private Bucket Configuration
    'region_private' => env('AWS_DEFAULT_REGION_PRIVATE', 'us-west-1'),
    'bucket_private' => env('AWS_BUCKET_PRIVATE', 'sequifi-private-files'),
    'key_encrypted_private' => env('ENCRYPTED_AWS_ACCESS_KEY_ID_PRIVATE'),
    'secret_encrypted_private' => env('ENCRYPTED_AWS_SECRET_ACCESS_KEY_PRIVATE'),

    // Public Bucket Configuration
    'region_public' => env('AWS_DEFAULT_REGION_PUBLIC', 'us-west-1'),
    'bucket_public' => env('AWS_BUCKET_PUBLIC', 'sequifi'),
    'key_encrypted_public' => env('ENCRYPTED_AWS_ACCESS_KEY_ID_PUBLIC'),
    'secret_encrypted_public' => env('ENCRYPTED_AWS_SECRET_ACCESS_KEY_PUBLIC'),

];
