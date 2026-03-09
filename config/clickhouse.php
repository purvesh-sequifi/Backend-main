<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all work. Of course, you may use many
    | connections at once using the manager class.
    |
    */

    'default' => env('CLICKHOUSE_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | ClickHouse Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application.
    |
    */

    'connections' => [
        'default' => [
            'host' => env('CLICKHOUSE_HOST', 'localhost'),
            'port' => env('CLICKHOUSE_PORT', 8123),
            'database' => env('CLICKHOUSE_DATABASE', 'default'),
            'username' => env('CLICKHOUSE_USERNAME', 'default'),
            'password' => env('CLICKHOUSE_PASSWORD', ''),
            'timeout' => env('CLICKHOUSE_TIMEOUT', 1.5),
            'connectTimeout' => env('CLICKHOUSE_CONNECT_TIMEOUT', 3),
            'options' => [
                'enable_http_compression' => 1,
                'protocol' => env('CLICKHOUSE_PROTOCOL', 'https'),
            ],
        ],
    ],
];
