<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
// Import the required models
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdatePidFromPocomos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pocomos:update-pid
                            {startDate? : Start date for API query in mm/dd/yy format}
                            {endDate? : End date for API query in mm/dd/yy format}
                            {--contract-id= : Specific contract ID to update. If not provided, all records will be processed}
                            {--dry-run : Run without updating data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update PID values in database tables based on Pocomos API data';

    /**
     * Log channel to use for Pocomos-specific logging
     *
     * @var string
     */
    protected $logChannel = 'pocomos';

    /**
     * Tables to update with their ID fields
     *
     * @var array
     */
    protected $tables = [
        'sale_masters' => 'pid',
        'sale_master_process' => 'pid',
        'sale_product_master' => 'pid',
        'user_commission' => 'pid',
        'user_overrides' => 'pid',
        'projection_user_commissions' => 'pid',
        'projection_user_overrides' => 'pid',
        'legacy_api_data_null' => 'pid',
        'legacy_api_raw_data_histories' => 'pid',
        'legacy_api_raw_data_histories_log' => 'pid',
    ];

    /**
     * Tables that exist in the database (will be filled at runtime)
     *
     * @var array
     */
    protected $existingTables = [];

    // Summary counters
    private $updatedRecords = [];

    private $skippedRecords = [];

    // Store detailed information about updated records
    private $updatedCustomers = [];

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
        $this->info('Starting PID update process from Pocomos API...');

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Running in dry-run mode - no data will be updated');
        }

        // Set dates for API query
        $startDate = $this->argument('startDate') ?? Carbon::now()->format('m/d/y');
        $endDate = $this->argument('endDate') ?? Carbon::now()->addDays(2)->format('m/d/y');

        $this->info("Date range: $startDate to $endDate");

        try {
            // Get the contract ID if provided
            $contractId = $this->option('contract-id');

            // Step 1: Query the account-status API
            $apiData = $this->queryAccountStatusApi($startDate, $endDate, $contractId);

            if (empty($apiData)) {
                $this->info('No records found from the API.');

                return Command::SUCCESS;
            }

            // Step 2: Update records in all tables
            $this->updateRecords($apiData, $dryRun);

            // Step 3: Display summary
            $this->displaySummary();

            $this->info('Pocomos PID update process completed successfully.');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Error during update process: '.$e->getMessage());
            Log::channel($this->logChannel)->error('Pocomos PID update error: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Get JWT token for authentication with Pocomos API
     *
     * @return string|null JWT Token on success, null on failure
     */
    private function getJwtToken(string $baseUrl, object $credentials): ?string
    {
        $jwtUrl = $baseUrl.'/public/technician/jwt_token';

        // For actual API call, use real credentials with URL encoding
        $jwtPayload = 'username='.urlencode($credentials->username).'&password='.urlencode($credentials->password);

        // For logging purposes, mask the password
        $jwtLogPayload = json_encode(['username' => $credentials->username, 'password' => '********']);

        // Create log entry before API call
        $apiLog = InterigationTransactionLog::create([
            'interigation_name' => 'Pocomos',
            'api_name' => 'JWT Token',
            'url' => $jwtUrl,
            'payload' => $jwtLogPayload,
            'response' => null,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $jwtUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jwtPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        // Update log entry with response
        $apiLog->update(['response' => $response]);

        // Parse the token response
        $decoded = json_decode($response);

        // Check if the response has the expected format and contains a token
        if (! isset($decoded->response) || (isset($decoded->meta->errors) && ! empty($decoded->meta->errors))) {
            $this->error('Invalid JWT token response');
            Log::channel($this->logChannel)->error('Invalid JWT token response', [
                'response' => $response,
            ]);

            return null;
        }

        // Extract the token from the response field
        return $decoded->response;
    }

    /**
     * Query the account-status API endpoint to get contract data
     *
     * @param  mixed|null  $contractId  Specific contract ID to filter for, or null for all records
     * @return array Array of records with pcc_id and contract_id from the API response
     */
    private function queryAccountStatusApi(string $startDate, string $endDate, $contractId = null): array
    {
        $this->info('Querying account-status API...');
        $apiData = [];

        // Get all active Pocomos integrations
        $integrations = Integration::where(['name' => 'Pocomos', 'status' => 1])->get();

        if ($integrations->isEmpty()) {
            $this->error('No active Pocomos integrations found');

            return $apiData;
        }

        $this->info('Found '.$integrations->count().' active Pocomos integrations');

        // Format dates for API call
        $formattedStartDate = $startDate;
        $formattedEndDate = $endDate;

        // Process each Pocomos integration
        foreach ($integrations as $integration) {
            $this->info('Processing Pocomos integration ID: '.$integration->id.' '.$integration->description);

            // Decrypt integration value
            $value = openssl_decrypt($integration->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
            $decode_value = json_decode($value);
            $base_url = isset($decode_value->base_url) ? $decode_value->base_url : null;

            if ($base_url == null) {
                $this->error('Base URL not found for Branch ID: '.$integration->description);

                continue;
            }

            // Get JWT token for authentication
            $token = $this->getJwtToken($base_url, $decode_value);

            if (! $token) {
                $this->error('Failed to get JWT token for integration ID: '.$integration->id);

                continue;
            }

            // Use the account-status endpoint
            $apiUrl = $base_url.'/jwt/'.$decode_value->branch_id.'/report/account-status';

            // Prepare payload for account-status endpoint
            $postFields = [
                'dateType' => 'contract',
                'startDate' => $formattedStartDate,
                'endDate' => $formattedEndDate,
                'anyStatus' => 1,
                'anySalesperson' => 1,
                'anyInitialJobStatus' => 1,
                'isSpecialtyPestChecked' => 1,
            ];

            $payload = json_encode($postFields);

            // Create log entry before API call
            $apiLog = InterigationTransactionLog::create([
                'interigation_name' => 'Pocomos',
                'api_name' => 'Account Status Fetch',
                'url' => $apiUrl,
                'payload' => $payload,
                'response' => null,
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'XAuthToken: '.$token,
                    'Authorization: Bearer '.$token,
                ],
            ]);

            $response = curl_exec($curl);
            curl_close($curl);

            // Update log entry with response
            $apiLog->update(['response' => $response]);

            // Parse the response
            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid JSON response from Pocomos API for integration: {$integration->id}");
                Log::channel($this->logChannel)->error('Invalid JSON response from Pocomos API', [
                    'integration_id' => $integration->id,
                    'branch_id' => $decode_value->branch_id,
                    'error' => json_last_error_msg(),
                    'response_sample' => substr($response, 0, 255).(strlen($response) > 255 ? '...' : ''),
                ]);

                continue;
            }

            // Check for the specific contract_id we're interested in
            if (! empty($decoded) && isset($decoded['response']) && is_array($decoded['response'])) {
                foreach ($decoded['response'] as $record) {
                    if (isset($record['contract_id']) && isset($record['pcc_id'])) {
                        // If a specific contract ID was provided, filter for it
                        // Otherwise include all records
                        if ($contractId === null || $record['contract_id'] == $contractId) {
                            $apiData[] = [
                                'pcc_id' => $record['pcc_id'],
                                'contract_id' => $record['contract_id'],
                            ];

                            $this->info("  - Found record: contract_id={$record['contract_id']}, pcc_id={$record['pcc_id']}");
                        }
                    }
                }

                $this->info('Found '.count($decoded['response']).' records from integration '.$integration->id);
            } else {
                $this->warn('No valid records found from integration '.$integration->id);
            }
        }

        $this->info('Total valid records found: '.count($apiData));

        return $apiData;
    }

    /**
     * Update records in all specified tables
     *
     * @param  array  $apiData  Array of records from the API
     * @param  bool  $dryRun  Whether to run in dry-run mode (no updates)
     */
    private function updateRecords(array $apiData, bool $dryRun): void
    {
        $this->info('Starting update process for '.count($apiData).' records...');

        // Check which tables exist in the database
        $this->checkExistingTables();

        if (empty($this->existingTables)) {
            $this->error('No target tables found in the database. Please check table names.');

            return;
        }

        $this->info(count($this->existingTables).' tables found and will be processed.');

        // Initialize counters for each table
        foreach ($this->existingTables as $tableName) {
            $this->updatedRecords[$tableName] = 0;
            $this->skippedRecords[$tableName] = 0;
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            foreach ($apiData as $record) {
                $pcc_id = $record['pcc_id'];
                $contract_id = $record['contract_id'];

                // Update each existing table
                foreach ($this->existingTables as $tableName) {
                    $pidField = $this->tables[$tableName];
                    $this->updateTable($tableName, $pidField, $contract_id, $pcc_id, $dryRun);
                }
            }

            if (! $dryRun) {
                DB::commit();
                $this->info('Database transaction committed successfully.');
            } else {
                DB::rollBack();
                $this->info('Dry run - database transaction rolled back.');
            }

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check which tables from our list exist in the database
     */
    private function checkExistingTables(): void
    {
        $this->info('Checking which tables exist in the database...');

        $tables = array_keys($this->tables);
        $this->existingTables = [];

        foreach ($tables as $tableName) {
            try {
                // Try to query the table - if it doesn't exist, it will throw an exception
                DB::table($tableName)->limit(1)->get();

                // If we get here, the table exists
                $this->existingTables[] = $tableName;
                $this->info("  ✓ Table {$tableName} exists");
            } catch (Exception $e) {
                $this->warn("  ✗ Table {$tableName} not found in database");
            }
        }
    }

    /**
     * Update records in a specific table
     *
     * @param  string  $tableName  Name of the table to update
     * @param  string  $pidField  Name of the PID field in the table
     * @param  mixed  $contractId  Contract ID to match
     * @param  mixed  $pccId  New PCC ID to set
     * @param  bool  $dryRun  Whether to run in dry-run mode (no updates)
     */
    private function updateTable(string $tableName, string $pidField, $contractId, $pccId, bool $dryRun): void
    {
        $this->info("Processing table {$tableName}: looking for pid={$contractId}");

        // Check if the pccId already exists in this table
        $existsCount = DB::table($tableName)
            ->where($pidField, $pccId)
            ->count();

        if ($existsCount > 0) {
            // Skip update because the new PID already exists
            $this->info("  - Skipping: new pid {$pccId} already exists in {$tableName}");
            $this->skippedRecords[$tableName]++;

            return;
        }

        // Find records matching the contract ID
        $recordsToUpdate = DB::table($tableName)
            ->where($pidField, $contractId)
            ->get();

        if ($recordsToUpdate->isNotEmpty()) {
            $this->info("  - Found {$recordsToUpdate->count()} records to update in {$tableName}");

            // For sale_masters table, capture customer details before updating
            if ($tableName === 'sale_masters') {
                foreach ($recordsToUpdate as $record) {
                    // Check if the record has customer_name and customer_phone fields
                    $customerName = isset($record->customer_name) ? $record->customer_name : 'N/A';
                    $customerPhone = isset($record->customer_phone) ? $record->customer_phone : 'N/A';

                    // Store customer details for reporting
                    $this->updatedCustomers[] = [
                        'contract_id' => $contractId,
                        'pcc_id' => $pccId,
                        'name' => $customerName,
                        'phone' => $customerPhone,
                    ];
                }
            }

            if (! $dryRun) {
                // Update the records
                DB::table($tableName)
                    ->where($pidField, $contractId)
                    ->update([$pidField => $pccId]);
            }

            $this->updatedRecords[$tableName] += $recordsToUpdate->count();
        } else {
            $this->info("  - No matching records found in {$tableName} for pid={$contractId}");
        }
    }

    /**
     * Display the summary of updated records
     */
    private function displaySummary()
    {
        $this->info("\n===== Update Summary =====\n");

        $this->info('✅ Updated Records:');
        $totalUpdated = 0;
        foreach ($this->updatedRecords as $table => $count) {
            $this->info("   {$table}: {$count}");
            $totalUpdated += $count;
        }
        $this->info("   Total: {$totalUpdated}\n");

        // Display customer details for updated sale_masters records
        if (! empty($this->updatedCustomers)) {
            $this->info('📋 Updated Customer Details:');
            $this->info('   Contract ID → PCC ID | Customer Name | Phone');
            $this->info('   ----------------------------------------');

            // Show up to 10 records in the console output to avoid excessive output
            $displayCount = min(count($this->updatedCustomers), 10);

            for ($i = 0; $i < $displayCount; $i++) {
                $customer = $this->updatedCustomers[$i];
                $this->info("   {$customer['contract_id']} → {$customer['pcc_id']} | {$customer['name']} | {$customer['phone']}");
            }

            // If there are more records, indicate that
            if (count($this->updatedCustomers) > 10) {
                $remaining = count($this->updatedCustomers) - 10;
                $this->info("   ... and {$remaining} more customers");
            }

            $this->info('');
        }

        $this->info('⛔ Skipped Records (due to existing pid):');
        $totalSkipped = 0;
        foreach ($this->skippedRecords as $table => $count) {
            $this->info("   {$table}: {$count}");
            $totalSkipped += $count;
        }
        $this->info("   Total: {$totalSkipped}\n");

        $this->info('===== End of Summary =====');
    }
}
