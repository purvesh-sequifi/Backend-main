<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'pocomos' => [
            'driver' => 'daily',
            'path' => storage_path('logs/pocomos.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'flexible_id_audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/flexible_id_audit.log'),
            'level' => 'info',
            'days' => 90, // Keep audit logs for 90 days
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'clark_excel' => [
            'driver' => 'daily',
            'path' => storage_path('logs/clark_excel.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'clark_sftp' => [
            'driver' => 'daily',
            'path' => storage_path('logs/clark-sftp.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        'user_activity_log' => [
            'driver' => 'single',
            'path' => storage_path('logs/user_activity.log'),
            'level' => 'debug',
        ],

        'encryptDataLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/encrypt-data.log'),
            'level' => 'debug',
        ],

        'sclearance_log' => [
            'driver' => 'daily',
            'path' => storage_path('logs/sclearance_log.log'),
            'level' => 'debug',
        ],

        'reconLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/recon-log.log'),
            'level' => 'debug',
        ],

        'reconLogCalculation' => [
            'driver' => 'daily',
            'path' => storage_path('logs/recon-calculation/recon-calculation-log.log'),
            'level' => 'debug',
        ],

        'position_cache' => [
            'driver' => 'daily',
            'path' => storage_path('logs/position-cache.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'excel_import_counters' => [
            'driver' => 'daily',
            'path' => storage_path('logs/excel_import_counters.log'),
            'level' => 'info',
            'days' => 30, // Keep logs for 30 days for troubleshooting
        ],
    ],

];
