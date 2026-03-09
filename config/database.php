<?php

use Illuminate\Support\Str;

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        // Other connections (mongodb, sqlite, pgsql, sqlsrv) remain unchanged

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'read' => [
                'host' => env('DB_HOST_READ', env('DB_HOST', '127.0.0.1')),
            ],
            'write' => [
                'host' => env('DB_HOST_WRITE', env('DB_HOST', '127.0.0.1')),
            ],
            'sticky' => env('DB_STICKY', true),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', false),
            'engine' => env('DB_ENGINE', 'InnoDB ROW_FORMAT=DYNAMIC'),
            'options' => extension_loaded('pdo_mysql') ? array_filter([

                // SSL Configuration
                defined('Pdo\Mysql::ATTR_SSL_CA') ? Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),

                // Connection Management
                PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', false),
                PDO::ATTR_TIMEOUT => env('DB_TIMEOUT', 30),
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Performance Optimizations
                PDO::ATTR_EMULATE_PREPARES => true,
                defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY') ? Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                defined('Pdo\Mysql::ATTR_LOCAL_INFILE') ? Pdo\Mysql::ATTR_LOCAL_INFILE : PDO::MYSQL_ATTR_LOCAL_INFILE => false,
                defined('Pdo\Mysql::ATTR_COMPRESS') ? Pdo\Mysql::ATTR_COMPRESS : PDO::MYSQL_ATTR_COMPRESS => false,
                defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,

                // Init Command (session variables)
                (defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? Pdo\Mysql::ATTR_INIT_COMMAND : PDO::MYSQL_ATTR_INIT_COMMAND) => 
                    "SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';".
                    "SET SESSION time_zone='+00:00';".
                    'SET SESSION wait_timeout=28800;'.
                    'SET SESSION interactive_timeout=28800;'.
                    'SET SESSION innodb_lock_wait_timeout='.env('DB_LOCK_TIMEOUT', 120).';'.
                    'SET SESSION autocommit=1;'.
                    "SET SESSION transaction_isolation='READ-COMMITTED';".
                    'SET SESSION bulk_insert_buffer_size=67108864;',

            ]) : [],
        ],

        // Similarly, update mysql_2, master_db, sclearance, queue_monitoring:
        // Replace all PDO::MYSQL_* constants with Pdo\Mysql::* with the same pattern as above
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'read_timeout' => 60,
            'context' => [],
        ],
        // cache & queue remain unchanged
    ],

    'write_optimization' => [
        'monitor_writes' => env('DB_MONITOR_WRITES', false),
        'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 1000),
        'track_write_patterns' => env('DB_TRACK_WRITE_PATTERNS', false),
        'flush_log_at_commit' => env('DB_FLUSH_LOG_AT_COMMIT', 2),
        'sync_binlog' => env('DB_SYNC_BINLOG', 0),
        'persistent_connections' => env('DB_PERSISTENT', false),
    ],

];