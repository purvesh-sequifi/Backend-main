<?php

namespace App\Services;

use ClickHouseDB\Client as ClickHouseClient;
use Illuminate\Support\Facades\Log;

class ClickHouseConnectionService
{
    /**
     * @var ClickHouseClient|null
     */
    protected static $client = null;

    /**
     * @var float
     */
    protected static $lastAccessTime = null;

    /**
     * Maximum idle time before connection is considered stale (in seconds)
     *
     * @var int
     */
    protected static $maxIdleTime = 300; // 5 minutes

    /**
     * Get a ClickHouse client instance
     *
     * @param  int  $timeout  Connection timeout in seconds
     * @param  bool  $force  Force a new connection even if one exists
     */
    public static function getClient(int $timeout = 60, bool $force = false): ?ClickHouseClient
    {
        if ($force || self::needsNewConnection()) {
            return self::initialize($timeout);
        }

        self::$lastAccessTime = microtime(true);

        return self::$client;
    }

    /**
     * Check if the connection needs to be refreshed
     */
    public static function needsNewConnection(): bool
    {
        // No connection yet
        if (self::$client === null) {
            return true;
        }

        // No record of last access time
        if (self::$lastAccessTime === null) {
            return true;
        }

        // Connection has been idle too long
        $idleTime = microtime(true) - self::$lastAccessTime;
        if ($idleTime > self::$maxIdleTime) {
            return true;
        }

        return false;
    }

    /**
     * Initialize a connection to ClickHouse
     *
     * @param  int  $timeout  Connection timeout in seconds
     */
    protected static function initialize(int $timeout = 60): ?ClickHouseClient
    {
        try {
            // Use config() instead of env() to centralize configuration
            $host = config('clickhouse.connections.default.host');
            $port = config('clickhouse.connections.default.port');
            $database = config('clickhouse.connections.default.database');
            $username = config('clickhouse.connections.default.username');
            $password = config('clickhouse.connections.default.password');
            $protocol = config('clickhouse.connections.default.options.protocol');
            $timeout = config('clickhouse.connections.default.timeout');
            $connectTimeout = config('clickhouse.connections.default.connectTimeout');

            // Comprehensive validation of required fields
            if (empty($host) || empty($port) || empty($database) || empty($username) || empty($password)) {
                Log::error('[ClickHouse] Missing required ClickHouse configuration.');

                return null;
            }

            // Validate data types and ranges
            if (! is_numeric($port) || $port < 1 || $port > 65535) {
                Log::error('[ClickHouse] Invalid port configuration');

                return null;
            }

            if (! in_array($protocol, ['http', 'https'])) {
                Log::error('[ClickHouse] Invalid protocol configuration');

                return null;
            }

            if (! is_numeric($timeout) || $timeout < 1 || $timeout > 300) {
                Log::error('[ClickHouse] Invalid timeout configuration');

                return null;
            }

            $config = [
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'https' => $protocol === 'https',
                'database' => $database,
                'timeout' => (float) $timeout,
                'connect_timeout' => (float) $connectTimeout,
            ];

            // Secure logging without exposing credentials
            Log::info('[ClickHouse] Initializing connection', [
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'protocol' => $protocol,
                'timeout' => $timeout,
                // Password intentionally omitted for security
            ]);

            self::$client = new ClickHouseClient($config);
            self::$lastAccessTime = microtime(true);

            // Test connection
            self::$client->ping();
            Log::info('[ClickHouse] Connection established successfully');

            return self::$client;

        } catch (\Exception $e) {
            // SECURITY: Never expose credentials in error messages
            Log::error('[ClickHouse] Connection failed', [
                'error' => $e->getMessage(),
                'timeout' => $timeout,
                // No config details that might contain credentials
            ]);
            self::$client = null;

            return null;
        }
    }

    /**
     * Send a ping query with retry mechanism and exponential backoff
     *
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @param  int  $initialTimeout  Initial timeout in seconds
     * @param  bool  $verbose  Whether to output debug info
     * @return bool Success status
     */
    public static function ping(int $maxRetries = 3, int $initialTimeout = 60, bool $verbose = false): bool
    {
        $client = self::getClient($initialTimeout, true);
        if (! $client) {
            if ($verbose) {
                Log::warning('[ClickHouse] Failed to obtain client for ping');
            }

            return false;
        }

        $attempt = 0;
        $success = false;
        $timeout = $initialTimeout;
        $maxTimeout = 180; // 3 minutes max timeout for waking up

        do {
            $attempt++;

            try {
                // Use a query with minimal resource usage
                $result = $client->select('SELECT 1');
                if (isset($result->rows()[0]['1']) && $result->rows()[0]['1'] == 1) {
                    $success = true;
                    if ($verbose) {
                        Log::info("[ClickHouse] Ping successful on attempt {$attempt}");
                    }
                    break;
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();

                // Increase timeout for next attempt with exponential backoff
                $timeout = min($maxTimeout, $timeout * 2);

                if ($verbose) {
                    Log::warning("[ClickHouse] Ping attempt {$attempt} failed: {$errorMessage}. Next timeout: {$timeout}s");
                }

                // Re-initialize the client with increased timeout for next attempt
                if ($attempt < $maxRetries) {
                    sleep(min(5, $attempt)); // Progressive waiting between attempts
                    $client = self::getClient($timeout, true);
                }
            }
        } while ($attempt < $maxRetries);

        return $success;
    }

    /**
     * Pre-warm the connection to ClickHouse
     * This is useful to call before running operations that require ClickHouse to be responsive
     *
     * @param  int  $maxRetries  Maximum retry attempts
     * @param  int  $timeout  Connection timeout in seconds
     * @return bool Success status
     */
    public static function preWarmConnection(int $maxRetries = 5, int $timeout = 120): bool
    {
        Log::info('[ClickHouse] Starting connection pre-warming');

        // First ping attempt with extended timeout
        $success = self::ping($maxRetries, $timeout, true);

        if (! $success) {
            Log::error('[ClickHouse] Failed to pre-warm connection after '.$maxRetries.' attempts');

            return false;
        }

        Log::info('[ClickHouse] Connection successfully pre-warmed');

        return true;
    }

    /**
     * Execute a query with retry mechanism for handling sleeping ClickHouse instances
     *
     * @param  string  $query  The SQL query to execute
     * @param  array  $params  Query parameters
     * @param  int  $maxRetries  Maximum retry attempts
     * @param  int  $timeout  Connection timeout in seconds
     * @return mixed Query result or false on failure
     */
    public static function executeWithRetry(string $query, array $params = [], int $maxRetries = 3, int $timeout = 60)
    {
        $attempt = 0;
        $lastException = null;
        $progressiveTimeout = $timeout;

        do {
            $attempt++;

            try {
                $client = self::getClient($progressiveTimeout, $attempt > 1);

                if (! $client) {
                    throw new \Exception("Failed to get ClickHouse client on attempt {$attempt}");
                }

                // For SELECT queries
                if (stripos(trim($query), 'select') === 0) {
                    return $client->select($query, $params);
                }

                // For INSERT, UPDATE, CREATE, etc.
                return $client->write($query, $params);

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning("[ClickHouse] Query attempt {$attempt} failed: ".$e->getMessage());

                // Progressive backoff
                $sleepTime = min(pow(2, $attempt - 1), 10);
                $progressiveTimeout = min(180, $timeout * ($attempt + 1));

                if ($attempt < $maxRetries) {
                    Log::info("[ClickHouse] Retrying in {$sleepTime}s with timeout {$progressiveTimeout}s");
                    sleep($sleepTime);
                }
            }
        } while ($attempt < $maxRetries);

        // All attempts failed
        Log::error('[ClickHouse] All query attempts failed', [
            'last_error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'query' => $query,
        ]);

        // Re-throw the last exception
        if ($lastException) {
            throw $lastException;
        }

        return false;
    }

    /**
     * Pre-warm the connection to ClickHouse with extreme timeout and retry settings
     * This is designed specifically to handle deep-sleep wakeup scenarios
     *
     * @param  int  $maxRetries  Maximum retry attempts (default 7 for extensive retrying)
     * @param  int  $initialTimeout  Initial connection timeout in seconds
     * @return bool Success status
     */
    public static function wakeUpDeepSleepingInstance(int $maxRetries = 7, int $initialTimeout = 120): bool
    {
        Log::info('[ClickHouse] Starting deep sleep wake-up procedure');

        $success = self::ping($maxRetries, $initialTimeout, true);

        if (! $success) {
            Log::error('[ClickHouse] Failed to wake up ClickHouse after extensive retries');

            return false;
        }

        Log::info('[ClickHouse] ClickHouse instance successfully awakened');

        // Run a second ping with shorter timeout to ensure stability
        $success = self::ping(1, 30, true);

        return $success;
    }

    /**
     * Get a ClickHouse client instance without specifying a database
     * This is useful for creating databases or connecting to the server before a specific database exists
     *
     * @param  int  $timeout  Connection timeout in seconds
     */
    public static function getClientWithoutDatabase(int $timeout = 60): ?ClickHouseClient
    {
        try {
            // Use config() instead of env() to centralize configuration
            $host = config('clickhouse.connections.default.host');
            $port = config('clickhouse.connections.default.port');
            $username = config('clickhouse.connections.default.username');
            $password = config('clickhouse.connections.default.password');
            $protocol = config('clickhouse.connections.default.options.protocol');
            $timeout = config('clickhouse.connections.default.timeout');
            $connectTimeout = config('clickhouse.connections.default.connectTimeout');

            // Comprehensive validation of required fields (excluding database)
            if (empty($host) || empty($port) || empty($username) || empty($password)) {
                Log::error('[ClickHouse] Missing required ClickHouse configuration for database-less connection.');

                return null;
            }

            // Validate data types and ranges
            if (! is_numeric($port) || $port < 1 || $port > 65535) {
                Log::error('[ClickHouse] Invalid port configuration');

                return null;
            }

            if (! in_array($protocol, ['http', 'https'])) {
                Log::error('[ClickHouse] Invalid protocol configuration');

                return null;
            }

            if (! is_numeric($timeout) || $timeout < 1 || $timeout > 300) {
                Log::error('[ClickHouse] Invalid timeout configuration');

                return null;
            }

            $config = [
                'host' => $host,
                'port' => (int) $port,
                'username' => $username,
                'password' => $password,
                'https' => $protocol === 'https',
                // No database specified - true database-less connection
                'timeout' => (float) $timeout,
                'connect_timeout' => (float) $connectTimeout,
            ];

            // Secure logging without exposing credentials
            Log::info('[ClickHouse] Initializing database-less connection', [
                'host' => $host,
                'port' => $port,
                'protocol' => $protocol,
                'timeout' => $timeout,
                // Password intentionally omitted for security
            ]);

            $client = new ClickHouseClient($config);

            // Test connection
            $client->ping();
            Log::info('[ClickHouse] Database-less connection established successfully');

            return $client;

        } catch (\Exception $e) {
            Log::error('[ClickHouse] Failed to establish database-less connection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
