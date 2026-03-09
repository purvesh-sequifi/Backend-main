<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SFTP Connection Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains SFTP connection details used by the application
    |
    */

    'connections' => [
        'default' => [
            'host' => env('SFTP_HOST'),
            'port' => 22,
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),
            'remote_path' => env('SFTP_PATH'),
        ],

        'clark' => [
            'host' => env('SFTP_HOST_CLARK'),
            'port' => 22,
            'username' => env('SFTP_USERNAME_CLARK'),
            'password' => env('SFTP_PASSWORD_CLARK'),
            'remote_path' => env('SFTP_PATH_CLARK'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SFTP Timeout Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control timeouts for various SFTP operations to prevent
    | the application from hanging indefinitely when network issues occur
    |
    */

    'timeout' => [
        // Connection timeout in seconds
        'connection' => env('SFTP_CONNECTION_TIMEOUT', 30),

        // Command execution timeout in seconds
        'execution' => env('SFTP_EXECUTION_TIMEOUT', 60),

        // Directory listing timeout in seconds
        'dirlist' => env('SFTP_DIRLIST_TIMEOUT', 60),

        // File retrieval timeout in seconds
        'fileget' => env('SFTP_FILEGET_TIMEOUT', 120),

        // Maximum execution time for the entire SFTP import process in seconds
        'max_execution' => env('SFTP_MAX_EXECUTION_TIME', 3600),
    ],
];
