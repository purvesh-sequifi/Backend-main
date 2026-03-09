<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class DatabaseReconnector
{
    /**
     * Maximum number of reconnection attempts
     */
    protected $maxRetries = 5;

    /**
     * Delay between retry attempts in seconds
     */
    protected $retryDelay = 2;

    /**
     * Configure the database connection for better stability during long-running processes
     */
    public static function configure()
    {
        try {
            // Disable persistent connections to avoid stale connections
            config(['database.connections.mysql.options' => [
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_TIMEOUT => 60, // Increase timeout
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]]);

            // Set the wait_timeout and interactive_timeout for this session
            DB::statement('SET SESSION wait_timeout=600');
            DB::statement('SET SESSION interactive_timeout=600');

            return true;
        } catch (\Exception $e) {
            Log::error('Database configuration failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Attempt to reconnect to the database after a connection failure
     *
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @param  int  $retryDelay  Delay between attempts in seconds
     * @return bool True if reconnection was successful, false otherwise
     */
    public static function reconnect(int $maxRetries = 5, int $retryDelay = 2): bool
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            $attempts++;

            try {
                // Disconnect from all connections
                foreach (config('database.connections') as $name => $config) {
                    DB::disconnect($name);
                }

                // Force a new connection
                DB::reconnect();

                // Test the connection
                DB::connection()->getPdo();
                DB::select('SELECT 1 as test_connection');

                // Log success
                Log::info("Database reconnection successful (attempt {$attempts})");

                // Reconfigure the connection
                self::configure();

                return true;
            } catch (\Exception $e) {
                Log::warning("Database reconnection failed (attempt {$attempts}/{$maxRetries}): ".$e->getMessage());

                // Wait before retrying
                sleep($retryDelay);
            }
        }

        Log::error("Database reconnection failed after {$maxRetries} attempts");

        return false;
    }
}
