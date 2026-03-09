<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestDbReadWriteSplit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:test-read-write-split {--local : Run tests in local mode using your local database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the read/write database splitting configuration';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing read/write database splitting...');

        // Add option for local testing
        if ($this->option('local')) {
            $this->info('Running in LOCAL testing mode - using mock connection tracing');
            config(['database.connections.mysql.read.host' => env('DB_HOST', '127.0.0.1')]);
            config(['database.connections.mysql.write.host' => env('DB_HOST', '127.0.0.1')]);

            // Force reconnection to apply config changes
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        // Output environment variables
        $this->info('Environment Configuration:');
        $this->info('DB_HOST: '.env('DB_HOST'));
        $this->info('DB_HOST_READ: '.(config('database.connections.mysql.read.host') ?? env('DB_HOST_READ', 'Not set')));
        $this->info('DB_HOST_WRITE: '.(config('database.connections.mysql.write.host') ?? env('DB_HOST_WRITE', 'Not set')));
        $this->line('---------------------------------------');

        try {
            // Test 1: Read Connection
            $this->info('Test 1: Testing READ connection...');
            $this->testReadConnection();
            $this->line('---------------------------------------');

            // Test 2: Write Connection
            $this->info('Test 2: Testing WRITE connection...');
            $this->testWriteConnection();
            $this->line('---------------------------------------');

            // Test 3: Sticky Connection (write followed by read)
            $this->info('Test 3: Testing STICKY connection behavior...');
            $this->testStickyConnection();
            $this->line('---------------------------------------');

            $this->info('All tests completed successfully!');

            return 0;

        } catch (\Exception $e) {
            $this->error('Test failed: '.$e->getMessage());
            Log::error('Database read/write test failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    private function testReadConnection()
    {
        // Get the PDO connection
        $connection = DB::connection('mysql')->getPdo();

        // Run a simple query to check connection
        $result = DB::select('SELECT NOW() as time');

        // Get the current server host
        $host = $this->getConnectionHost($connection);

        $this->info('Read query executed successfully');
        $this->info('Connected to host: '.$host);
        $this->info('Current time on database: '.$result[0]->time);

        // Check if connected to read host
        if (config('database.connections.mysql_read.host') && stripos($host, config('database.connections.mysql_read.host')) !== false) {
            $this->info('✓ Successfully using READ connection');
        } elseif (! config('database.connections.mysql_read.host')) {
            $this->info('⚠ DB_HOST_READ not set, using default connection');
        } else {
            $this->warn('⚠ Not connected to READ host. Check configuration.');
        }
    }

    private function testWriteConnection()
    {
        // Create a temporary test table if it doesn't exist
        DB::statement('CREATE TABLE IF NOT EXISTS db_read_write_test (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        // Get the PDO connection after a write operation
        DB::insert('INSERT INTO db_read_write_test (message) VALUES (?)', ['Test at '.now()]);
        $connection = DB::connection('mysql')->getPdo();

        // Get the current server host
        $host = $this->getConnectionHost($connection);

        $this->info('Write query executed successfully');
        $this->info('Connected to host: '.$host);

        // Check if connected to write host
        if (config('database.connections.mysql_write.host') && stripos($host, config('database.connections.mysql_write.host')) !== false) {
            $this->info('✓ Successfully using WRITE connection');
        } elseif (! config('database.connections.mysql_write.host')) {
            $this->info('⚠ DB_HOST_WRITE not set, using default connection');
        } else {
            $this->warn('⚠ Not connected to WRITE host. Check configuration.');
        }
    }

    private function testStickyConnection()
    {
        // Write operation
        $this->info('Performing a write operation...');
        DB::insert('INSERT INTO db_read_write_test (message) VALUES (?)', ['Sticky test at '.now()]);

        // Read operation (should use the same connection due to sticky=true)
        $this->info('Performing a read operation after write...');
        $connection = DB::connection('mysql')->getPdo();
        DB::select('SELECT * FROM db_read_write_test ORDER BY id DESC LIMIT 1');

        // Get the current server host
        $host = $this->getConnectionHost($connection);

        $this->info('Connected to host: '.$host);

        // With sticky=true, the read after write should use the write connection
        if (config('database.connections.mysql_write.host') && stripos($host, config('database.connections.mysql_write.host')) !== false) {
            $this->info('✓ Sticky connection working correctly - using WRITE connection for reads after writes');
        } elseif (! config('database.connections.mysql_write.host')) {
            $this->info('⚠ DB_HOST_WRITE not set, using default connection');
        } else {
            $this->warn('⚠ Sticky connection not working as expected. Still using READ connection after a write.');
        }
    }

    /**
     * Get the hostname of the current PDO connection
     */
    private function getConnectionHost($connection)
    {
        try {
            $result = $connection->query('SELECT @@hostname as host, @@port as port')->fetch(\PDO::FETCH_ASSOC);

            return $result['host'].':'.$result['port'];
        } catch (\Exception $e) {
            // If the above fails, try getting connection info from PDO directly
            try {
                // This might not work on all database systems
                return $connection->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
            } catch (\Exception $e2) {
                return 'Unable to determine host';
            }
        }
    }
}
