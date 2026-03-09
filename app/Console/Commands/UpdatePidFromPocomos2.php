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

class UpdatePidFromPocomos2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pocomos:update-pid-2
                            {startDate? : Start date for API query in mm/dd/yy format}
                            {endDate? : End date for API query in mm/dd/yy format}
                            {--contract-id= : Specific contract ID to update. If not provided, all records will be processed}
                            {--dry-run : Run without updating data}
                            {--dedupe-only : Only run deduplication on sale_masters table, skip API sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update PID values in database tables based on Pocomos API data - Version 2';

    /**
     * Log channel to use for Pocomos-specific logging
     *
     * @var string
     */
    protected $logChannel = 'pocomos';

    /**
     * Tables to update with their ID fields
     *
     * @var arraydedupeTables
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

    // Deduplication counters
    private $dedupeStats = [
        'duplicateGroups' => 0,
        'totalDuplicates' => 0,
        'recordsKept' => 0,
        'recordsDeleted' => 0,
        'pidUpdates' => 0,
    ];

    // Per-table deduplication results
    private $dedupeByTable = [];

    // Track PID transformations from sale_masters to apply to other tables
    private $pidTransformations = [];

    /**
     * Tables to deduplicate
     *
     * @var array
     */
    protected $dedupeTables = [
        'sale_masters',
        'user_overrides',
        'user_commission',
        'sale_master_process',
        'projection_user_commissions',
        'projection_user_overrides',
    ];

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
        $this->info('Starting PID update process from Pocomos API - Version 2...');

        $dryRun = $this->option('dry-run');
        $dedupeOnly = $this->option('dedupe-only');

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no data will be updated');
        }

        if ($dedupeOnly) {
            $this->info('Running in dedupe-only mode - will only process sale_masters duplicates');
        }

        try {
            if ($dedupeOnly) {
                // Only run deduplication
                $this->deduplicateTables($dryRun);
            } else {
                // Set dates for API query
                $startDate = $this->argument('startDate') ?? Carbon::now()->format('m/d/y');
                $endDate = $this->argument('endDate') ?? Carbon::now()->addDays(2)->format('m/d/y');

                $this->info("Date range: $startDate to $endDate");

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
            }

            // Step 3: Display summary
            $this->displaySummary();

            $this->info('Pocomos PID update process completed successfully - Version 2.');

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Error during update process: '.$e->getMessage());
            Log::channel($this->logChannel)->error('Pocomos PID update error (V2): '.$e->getMessage(), [
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
            'interigation_name' => 'Pocomos-V2',
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
            Log::channel($this->logChannel)->error('Invalid JWT token response (V2)', [
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
                'interigation_name' => 'Pocomos-V2',
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
                Log::channel($this->logChannel)->error('Invalid JSON response from Pocomos API (V2)', [
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
     * Deduplicate multiple tables by finding identical records except for pid, created_at, updated_at
     * Keep the older record but update its pid with the newer record's pid, then delete the newer record
     *
     * @param  bool  $dryRun  Whether to run in dry-run mode (no updates)
     */
    private function deduplicateTables(bool $dryRun): void
    {
        $this->info('Starting deduplication process for multiple tables...');

        // Step 1: Process sale_masters first with field comparison to establish PID transformations
        if (in_array('sale_masters', $this->dedupeTables)) {
            $this->info("\n--- Step 1: Processing sale_masters table (master reference) ---");
            $this->deduplicateTable('sale_masters', $dryRun);
        }

        // Step 2: Apply the same PID transformations to other tables
        $otherTables = array_filter($this->dedupeTables, function ($table) {
            return $table !== 'sale_masters';
        });

        if (! empty($otherTables) && ! empty($this->pidTransformations)) {
            $this->info("\n--- Step 2: Applying same PID transformations to other tables ---");
            $this->info('Found '.count($this->pidTransformations).' PID transformations to apply');

            foreach ($otherTables as $tableName) {
                $this->info("\n--- Processing table: {$tableName} ---");
                $this->applyPidTransformations($tableName, $dryRun);
            }
        } elseif (! empty($otherTables)) {
            $this->info("\n--- No PID transformations found from sale_masters, skipping other tables ---");
        }
    }

    /**
     * Deduplicate a specific table by finding identical records except for pid, created_at, updated_at
     * Keep the older record but update its pid with the newer record's pid, then delete the newer record
     *
     * @param  string  $tableName  Name of the table to deduplicate
     * @param  bool  $dryRun  Whether to run in dry-run mode (no updates)
     */
    private function deduplicateTable(string $tableName, bool $dryRun): void
    {
        $this->info("Starting deduplication process for {$tableName} table...");

        // Initialize per-table stats
        $this->dedupeByTable[$tableName] = [
            'duplicateGroups' => 0,
            'totalDuplicates' => 0,
            'recordsKept' => 0,
            'recordsDeleted' => 0,
            'pidUpdates' => 0,
        ];

        // Check if table exists
        try {
            DB::table($tableName)->limit(1)->get();
        } catch (Exception $e) {
            $this->warn("{$tableName} table not found in database - skipping");

            return;
        }

        // Get all column names from table except the ones we want to exclude
        $columns = collect(DB::getSchemaBuilder()->getColumnListing($tableName))
            ->reject(function ($column) {
                return in_array($column, ['id', 'pid', 'created_at', 'updated_at']);
            })
            ->values()
            ->toArray();

        $this->info('Analyzing columns: '.implode(', ', $columns));

        // Start database transaction for this table
        DB::beginTransaction();

        try {
            // Find duplicate groups using a hash-based approach to avoid memory issues
            $this->info('Scanning for duplicate records using hash-based approach...');

            // Create a hash of the comparison columns for each record
            $columnsForHash = implode(",'|',", array_map(function ($col) {
                return "COALESCE(`$col`, 'NULL')";
            }, $columns));

            // Increase GROUP_CONCAT limit to handle large groups
            DB::statement('SET SESSION group_concat_max_len = 1000000');

            // Use MD5 hash to group records efficiently with a reasonable limit
            $duplicateGroups = DB::select("
                SELECT COUNT(*) as duplicate_count,
                       MD5(CONCAT($columnsForHash)) as record_hash,
                       GROUP_CONCAT(id ORDER BY created_at ASC) as record_ids,
                       GROUP_CONCAT(pid ORDER BY created_at ASC) as pids,
                       GROUP_CONCAT(created_at ORDER BY created_at ASC) as created_ats
                FROM {$tableName} 
                GROUP BY MD5(CONCAT($columnsForHash))
                HAVING COUNT(*) > 1 AND COUNT(*) <= 50
                LIMIT 50
            ");

            $this->info('Found '.count($duplicateGroups)." groups with duplicates in {$tableName}");

            if (empty($duplicateGroups)) {
                $this->info("No duplicate records found in {$tableName}.");

                return;
            }

            // Check for very large groups that we're skipping (> 50 records)
            $largeGroups = DB::select("
                SELECT COUNT(*) as group_count
                FROM (
                    SELECT COUNT(*) as duplicate_count
                    FROM {$tableName} 
                    GROUP BY MD5(CONCAT($columnsForHash))
                    HAVING COUNT(*) > 50
                ) large_groups
            ");

            if (! empty($largeGroups) && $largeGroups[0]->group_count > 0) {
                $this->warn("Found {$largeGroups[0]->group_count} groups with more than 50 duplicates in {$tableName}. These will be skipped for safety. Consider handling them manually.");
            }

            // Process each duplicate group
            foreach ($duplicateGroups as $group) {
                $recordIds = explode(',', $group->record_ids);
                $pids = explode(',', $group->pids);
                $createdAts = explode(',', $group->created_ats);

                $this->info('Processing duplicate group with '.count($recordIds).' records');

                // The first record (oldest) is the one we'll keep
                $keepRecordId = $recordIds[0];
                $keepPid = $pids[0];

                // The last record (newest) has the PID we want to use
                $newestPid = end($pids);

                $this->info("  - Keeping record ID: {$keepRecordId} (created: {$createdAts[0]})");
                $this->info("  - Updating PID from {$keepPid} to {$newestPid}");

                // First, delete all other records in this group to avoid unique constraint violations
                $recordsToDelete = array_slice($recordIds, 1);
                foreach ($recordsToDelete as $index => $recordId) {
                    $recordIndex = $index + 1; // +1 because we skipped the first record

                    // Add bounds checking to prevent array access errors
                    $createdAt = isset($createdAts[$recordIndex]) ? $createdAts[$recordIndex] : 'Unknown';
                    $pid = isset($pids[$recordIndex]) ? $pids[$recordIndex] : 'Unknown';

                    $this->info("  - Deleting record ID: {$recordId} (created: {$createdAt}, PID: {$pid})");

                    if (! $dryRun) {
                        DB::table($tableName)
                            ->where('id', $recordId)
                            ->delete();
                    }

                    $this->dedupeStats['recordsDeleted']++;
                    $this->dedupeByTable[$tableName]['recordsDeleted']++;
                }

                // Then update the oldest record with the newest PID (now that duplicates are deleted)
                if (! $dryRun && $keepPid !== $newestPid) {
                    DB::table($tableName)
                        ->where('id', $keepRecordId)
                        ->update(['pid' => $newestPid]);

                    $this->dedupeStats['pidUpdates']++;
                    $this->dedupeByTable[$tableName]['pidUpdates']++;
                }

                // Track PID transformations for sale_masters to apply to other tables (even in dry-run)
                if ($tableName === 'sale_masters' && $keepPid !== $newestPid) {
                    $this->pidTransformations[] = [
                        'from_pid' => $keepPid,
                        'to_pid' => $newestPid,
                        'keep_record_id' => $keepRecordId,
                        'deleted_record_ids' => $recordsToDelete,
                    ];
                    $this->info("  - Tracked transformation: PID {$keepPid} → {$newestPid}");
                }

                $this->dedupeStats['duplicateGroups']++;
                $this->dedupeStats['totalDuplicates'] += count($recordIds);
                $this->dedupeStats['recordsKept']++;

                $this->dedupeByTable[$tableName]['duplicateGroups']++;
                $this->dedupeByTable[$tableName]['totalDuplicates'] += count($recordIds);
                $this->dedupeByTable[$tableName]['recordsKept']++;
            }

            if (! $dryRun) {
                DB::commit();
                $this->info("Database transaction committed successfully for {$tableName}.");
            } else {
                DB::rollBack();
                $this->info("Dry run - database transaction rolled back for {$tableName}.");
            }

        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Error processing {$tableName}: ".$e->getMessage());
            throw $e;
        }

        $this->info("Deduplication process completed for {$tableName}.");
    }

    /**
     * Apply PID transformations from sale_masters to other tables
     *
     * @param  string  $tableName  Name of the table to apply transformations to
     * @param  bool  $dryRun  Whether to run in dry-run mode (no updates)
     */
    private function applyPidTransformations(string $tableName, bool $dryRun): void
    {
        $this->info("Applying PID transformations to {$tableName} table...");

        // Initialize per-table stats
        $this->dedupeByTable[$tableName] = [
            'duplicateGroups' => 0,
            'totalDuplicates' => 0,
            'recordsKept' => 0,
            'recordsDeleted' => 0,
            'pidUpdates' => 0,
        ];

        // Check if table exists
        try {
            DB::table($tableName)->limit(1)->get();
        } catch (Exception $e) {
            $this->warn("{$tableName} table not found in database - skipping");

            return;
        }

        if (empty($this->pidTransformations)) {
            $this->info("No PID transformations to apply to {$tableName}.");

            return;
        }

        // Start database transaction for this table
        DB::beginTransaction();

        try {
            foreach ($this->pidTransformations as $transformation) {
                $fromPid = $transformation['from_pid'];
                $toPid = $transformation['to_pid'];

                $this->info("Applying transformation: PID {$fromPid} → {$toPid}");

                // Find records with both PIDs
                $fromPidRecord = DB::table($tableName)->where('pid', $fromPid)->first();
                $toPidRecord = DB::table($tableName)->where('pid', $toPid)->first();

                if ($fromPidRecord && $toPidRecord) {
                    $this->info("  - Found both records: keeping ID {$fromPidRecord->id} (PID {$fromPid}), deleting ID {$toPidRecord->id} (PID {$toPid})");

                    // Delete the toPid record first
                    if (! $dryRun) {
                        DB::table($tableName)->where('id', $toPidRecord->id)->delete();
                    }
                    $this->dedupeStats['recordsDeleted']++;
                    $this->dedupeByTable[$tableName]['recordsDeleted']++;

                    // Update fromPid record to toPid
                    if (! $dryRun) {
                        DB::table($tableName)->where('id', $fromPidRecord->id)->update(['pid' => $toPid]);
                    }
                    $this->dedupeStats['pidUpdates']++;
                    $this->dedupeByTable[$tableName]['pidUpdates']++;

                    $this->dedupeStats['duplicateGroups']++;
                    $this->dedupeStats['totalDuplicates'] += 2;
                    $this->dedupeStats['recordsKept']++;

                    $this->dedupeByTable[$tableName]['duplicateGroups']++;
                    $this->dedupeByTable[$tableName]['totalDuplicates'] += 2;
                    $this->dedupeByTable[$tableName]['recordsKept']++;

                } elseif ($fromPidRecord) {
                    $this->info("  - Found only fromPid record (ID {$fromPidRecord->id}), updating PID {$fromPid} → {$toPid}");

                    // Just update the PID
                    if (! $dryRun) {
                        DB::table($tableName)->where('id', $fromPidRecord->id)->update(['pid' => $toPid]);
                    }
                    $this->dedupeStats['pidUpdates']++;
                    $this->dedupeByTable[$tableName]['pidUpdates']++;

                } elseif ($toPidRecord) {
                    $this->info("  - Found only toPid record (ID {$toPidRecord->id}), no action needed");

                } else {
                    $this->info("  - No records found with either PID {$fromPid} or {$toPid}");
                }
            }

            if (! $dryRun) {
                DB::commit();
                $this->info("Database transaction committed successfully for {$tableName}.");
            } else {
                DB::rollBack();
                $this->info("Dry run - database transaction rolled back for {$tableName}.");
            }

        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Error processing {$tableName}: ".$e->getMessage());
            throw $e;
        }

        $this->info("PID transformation process completed for {$tableName}.");
    }

    /**
     * Display the summary of updated records
     */
    private function displaySummary()
    {
        $this->info("\n===== Update Summary (Version 2) =====\n");

        // Display API sync results if any
        if (! empty($this->updatedRecords) || ! empty($this->skippedRecords)) {
            $this->info('✅ API Sync - Updated Records:');
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

            $this->info('⛔ API Sync - Skipped Records (due to existing pid):');
            $totalSkipped = 0;
            foreach ($this->skippedRecords as $table => $count) {
                $this->info("   {$table}: {$count}");
                $totalSkipped += $count;
            }
            $this->info("   Total: {$totalSkipped}\n");
        }

        // Display deduplication results if any
        if ($this->dedupeStats['duplicateGroups'] > 0 || $this->option('dedupe-only')) {
            $this->info('🔄 Deduplication Results:');

            // Show PID transformations summary
            if (! empty($this->pidTransformations)) {
                $this->info("\n   🔀 PID Transformations from sale_masters:");
                $this->info('      - Total transformations tracked: '.count($this->pidTransformations));
                foreach ($this->pidTransformations as $index => $transformation) {
                    $this->info('      - Transformation '.($index + 1).": PID {$transformation['from_pid']} → {$transformation['to_pid']}");
                }
                $this->info('');
            }

            // Show per-table results
            foreach ($this->dedupeByTable as $tableName => $stats) {
                if ($stats['duplicateGroups'] > 0) {
                    $tableType = $tableName === 'sale_masters' ? 'Field comparison' : 'PID transformation';
                    $this->info("   📋 {$tableName} ({$tableType}):");
                    $this->info("      - Duplicate groups found: {$stats['duplicateGroups']}");
                    $this->info("      - Total duplicate records: {$stats['totalDuplicates']}");
                    $this->info("      - Records kept (oldest): {$stats['recordsKept']}");
                    $this->info("      - Records deleted (newer): {$stats['recordsDeleted']}");
                    $this->info("      - PID updates applied: {$stats['pidUpdates']}");
                } else {
                    $this->info("   📋 {$tableName}: No duplicates found");
                }
            }

            // Show overall totals
            $this->info("\n   🎯 Overall Totals:");
            $this->info("      - Total duplicate groups: {$this->dedupeStats['duplicateGroups']}");
            $this->info("      - Total duplicate records: {$this->dedupeStats['totalDuplicates']}");
            $this->info("      - Total records kept: {$this->dedupeStats['recordsKept']}");
            $this->info("      - Total records deleted: {$this->dedupeStats['recordsDeleted']}");
            $this->info("      - Total PID updates: {$this->dedupeStats['pidUpdates']}\n");
        }

        $this->info('===== End of Summary =====');
    }
}
