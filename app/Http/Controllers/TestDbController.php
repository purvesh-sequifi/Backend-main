<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestDbController extends Controller
{
    /**
     * Test database read operation
     */
    public function testRead(): JsonResponse
    {
        // Log connection info before query
        $this->logConnectionInfo('Before READ operation');

        // Perform read operation
        $data = DB::table('test_data')->get();

        // Log connection info after query
        $this->logConnectionInfo('After READ operation');

        return response()->json([
            'operation' => 'READ',
            'success' => true,
            'connection_info' => $this->getConnectionInfo(),
            'data' => $data,
            'count' => count($data),
        ]);
    }

    /**
     * Test database write operation
     */
    public function testWrite(Request $request): JsonResponse
    {
        // Log connection info before query
        $this->logConnectionInfo('Before WRITE operation');

        // Perform write operation
        $id = DB::table('test_data')->insertGetId([
            'message' => 'Test message at '.now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log connection info after query
        $this->logConnectionInfo('After WRITE operation');

        // Read after write (should use write connection due to sticky=true)
        $this->logConnectionInfo('Before READ after WRITE operation');
        $inserted = DB::table('test_data')->where('id', $id)->first();
        $this->logConnectionInfo('After READ after WRITE operation');

        return response()->json([
            'operation' => 'WRITE',
            'success' => true,
            'connection_info' => $this->getConnectionInfo(),
            'inserted_id' => $id,
            'inserted_data' => $inserted,
        ]);
    }

    /**
     * Test database read after write operation
     */
    public function testReadAfterWrite(): JsonResponse
    {
        // First perform a write operation
        $id = DB::table('test_data')->insertGetId([
            'message' => 'Sticky test at '.now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // This read should use the write connection due to sticky=true
        $inserted = DB::table('test_data')->where('id', $id)->first();

        return response()->json([
            'operation' => 'READ_AFTER_WRITE',
            'success' => true,
            'connection_info' => $this->getConnectionInfo(),
            'inserted_id' => $id,
            'inserted_data' => $inserted,
        ]);
    }

    /**
     * Test create table if it doesn't exist
     */
    public function createTestTable(): JsonResponse
    {
        if (! DB::schema()->hasTable('test_data')) {
            DB::schema()->create('test_data', function ($table) {
                $table->increments('id');
                $table->string('message');
                $table->timestamps();
            });

            return response()->json([
                'operation' => 'CREATE_TABLE',
                'success' => true,
                'message' => 'Test table created successfully',
            ]);
        }

        return response()->json([
            'operation' => 'CREATE_TABLE',
            'success' => true,
            'message' => 'Test table already exists',
        ]);
    }

    /**
     * Get connection information
     */
    private function getConnectionInfo(): array
    {
        try {
            $pdo = DB::connection()->getPdo();
            $dbHost = config('database.connections.mysql.host');
            $readHost = config('database.connections.mysql_read.host');
            $writeHost = config('database.connections.mysql_write.host');

            // Get connection attributes
            $connectionStatus = $pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS);

            // Get current status
            $result = DB::select('SELECT @@hostname as host, @@port as port, DATABASE() as db');

            return [
                'connection_id' => spl_object_hash($pdo),
                'connection_status' => $connectionStatus,
                'current_database' => $result[0]->db ?? 'unknown',
                'database_server' => $result[0]->host.':'.($result[0]->port ?? 'unknown'),
                'config' => [
                    'default_host' => $dbHost,
                    'read_host' => $readHost,
                    'write_host' => $writeHost,
                    'sticky' => config('database.connections.mysql.sticky', false),
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Log connection information
     */
    private function logConnectionInfo(string $context): void
    {
        $connectionInfo = $this->getConnectionInfo();
        Log::info("DB Connection Info: {$context}", $connectionInfo);
    }
}
