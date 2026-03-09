<?php

use Sentry\Laravel\Features\Storage\Integration;

/*
|--------------------------------------------------------------------------
| S3 Credential Helper
|--------------------------------------------------------------------------
|
| When credentials are empty/null, return null to let AWS SDK use the
| default credential provider chain (env vars -> credentials file -> IAM role)
|
*/
$getS3Key = fn () => env('AWS_ACCESS_KEY_ID') ?: null;
$getS3Secret = fn () => env('AWS_SECRET_ACCESS_KEY') ?: null;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    | Note: When 'key' and 'secret' are null, AWS SDK automatically uses
    | the default credential provider chain which includes IAM roles.
    |
    */

    'disks' => Integration::configureDisks([

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],
        'clark-momentum' => [
            'driver' => 'local',
            'root' => storage_path('app/clark-momentum'),
            'visibility' => 'private',
        ],

        // 's3' => [
        //     'driver' => 's3',
        //     'key' => env('AWS_ACCESS_KEY_ID'),
        //     'secret' => env('AWS_SECRET_ACCESS_KEY'),
        //     'region' => env('AWS_DEFAULT_REGION'),
        //     'bucket' => env('AWS_BUCKET'),
        //     'url' => env('AWS_URL'),
        //     'endpoint' => env('AWS_ENDPOINT'),
        // ],

        /*
        |--------------------------------------------------------------------------
        | S3 Disks with IAM Role Fallback
        |--------------------------------------------------------------------------
        |
        | These S3 disk configurations support IAM role authentication.
        | When AWS_ACCESS_KEY_ID or AWS_SECRET_ACCESS_KEY are empty/null,
        | the AWS SDK will automatically use the EC2 instance IAM role.
        |
        */
        'aws_s3' => [
            'driver' => 's3',
            'key' => $getS3Key(),
            'secret' => $getS3Secret(),
            'region' => env('AWS_DEFAULT_REGION', 'us-west-1'),
            'bucket' => env('AWS_BUCKET', 'sequifi'),
            'url' => env('AWS_S3BUCKET_URL'), // Optional: CloudFront URL
            'throw' => true, // Throw exceptions on errors
        ],

        's3_private' => [
            'driver' => 's3',
            'key' => $getS3Key(),
            'secret' => $getS3Secret(),
            'region' => env('AWS_DEFAULT_REGION', 'us-west-1'),
            'bucket' => env('AWS_BUCKET_PRIVATE', 'sequifi-private-files'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true, // Throw exceptions on errors
        ],

        's3_public' => [
            'driver' => 's3',
            'key' => $getS3Key(),
            'secret' => $getS3Secret(),
            'region' => env('AWS_DEFAULT_REGION', 'us-west-1'),
            'bucket' => env('AWS_BUCKET_PUBLIC', 'sequifi'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true, // Throw exceptions on errors
        ],

    ], true, true),

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
