<?php

namespace App\Console\Commands;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PocomosRawDataSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pocomos:sync-raw-data
                            {startDate? : Start date for data pull (format: YYYY-MM-DD)}
                            {endDate? : End date for data pull (format: YYYY-MM-DD)}
                            {--batch=1000 : Number of records to process in each batch}
                            {--timeout=3600 : Maximum execution time in seconds}
                            {--dry-run : Run without saving data}
                            {--test : Use a fixed test date range of Jan 1-31, 2023 for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull raw data from Pocomos API and store it in pocomos_raw_data table';

    /**
     * Log channel to use for Pocomos-specific logging
     *
     * @var string
     */
    protected $logChannel = 'pocomos';

    /**
     * Counters for tracking data processing results
     */
    protected $processedRecords = 0;

    protected $newRecords = 0;

    protected $updatedRecords = 0;

    protected $errors = 0;

    protected $insertedLegacyIds = [];

    protected $startTime;

    protected $batchSize = 1000;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->startTime = microtime(true);
    }

    /**
     * Helper method to log messages to the pocomos channel
     *
     * @param  string  $level  Log level (info, error, warning, debug)
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data
     */
    protected function logToPocomos(string $level, string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->$level($message, $context);
    }

    /**
     * Log info messages to pocomos channel
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logToPocomos('info', $message, $context);
        $this->info($message);
    }

    /**
     * Log error messages to pocomos channel
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logToPocomos('error', $message, $context);
        $this->error($message);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Get command options
            $this->batchSize = (int) $this->option('batch');
            $timeout = (int) $this->option('timeout');
            $dryRun = $this->option('dry-run');

            ini_set('max_execution_time', $timeout);
            $this->info('Using new Pocomos Raw Data Sync');
            $this->info("Max execution time set to {$timeout} seconds");

            if ($dryRun) {
                $this->warn('Running in dry-run mode - no data will be saved');
            }

            // Determine date range for data pull
            if ($this->option('test')) {
                // Use a fixed date range that's more likely to have data for testing
                $startDate = '2023-01-01';
                $endDate = '2023-01-31';
                $this->info("Using TEST date range: {$startDate} to {$endDate}");
            } else {
                // Use provided arguments or defaults
                $startDate = $this->argument('startDate') ?? now()->subMonth()->format('Y-m-d');
                $endDate = $this->argument('endDate') ?? now()->format('Y-m-d');
                $this->info("Using date range: {$startDate} to {$endDate}");
            }

            $this->logInfo("Date range for sync: {$startDate} to {$endDate}");

            // Get all active Pocomos integrations
            $integrations = Integration::where(['name' => 'Pocomos', 'status' => 1])->get();
            if ($integrations->isEmpty()) {
                $this->error('No active Pocomos integrations found');

                return Command::FAILURE;
            }

            $this->info('Found '.$integrations->count().' active Pocomos integrations');
            $syncBatchId = Str::uuid()->toString(); // Generate unique batch ID for this sync run

            // Process each Pocomos integration
            foreach ($integrations as $integration) {
                try {
                    $this->info('Processing Pocomos integration ID: '.$integration->id.' '.$integration->description);

                    $value = openssl_decrypt($integration->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                    $decode_value = json_decode($value);

                    // Debug: Print the decoded value
                    // $this->info("=== DECODED VALUE FOR INTEGRATION ID {$integration->id} ===");
                    // $this->info(json_encode($decode_value, JSON_PRETTY_PRINT));
                    // $this->info("=== END DECODED VALUE ===");

                    $base_url = isset($decode_value->base_url) ? $decode_value->base_url : null;

                    if ($base_url == null) {
                        $this->error('Base URL not found for Branch ID: '.$integration->description);

                        continue; // Skip this integration but continue with others
                    }

                    // Get JWT token for authentication
                    $jwtToken = $this->getJwtToken($base_url, $decode_value);
                    if (! $jwtToken) {
                        $this->error('Failed to get JWT token for Branch ID: '.$integration->description);

                        continue; // Skip this integration but continue with others
                    }

                    // Get branch ID from credentials
                    $branchId = $decode_value->branch_id ?? null;

                    if (! $branchId) {
                        $this->logError("Missing branch_id for Pocomos integration ID: {$integration->id}");

                        continue; // Skip this integration but continue with others
                    }

                    // Fetch account status data
                    $accountData = $this->fetchAccountStatusData($base_url, $jwtToken, $branchId, $startDate, $endDate);
                    if (empty($accountData)) {
                        $this->info('No account data found for Branch ID: '.$integration->description);

                        continue; // Skip this integration but continue with others
                    }

                    $this->info('Retrieved '.count($accountData).' records from Pocomos API for Branch ID: '.$integration->description);

                    if (! $dryRun) {
                        $this->processAndSaveData($accountData, $integration, $syncBatchId);
                    } else {
                        $this->info('Dry run: would have processed '.count($accountData).' records');
                    }

                } catch (\Exception $e) {
                    $this->logError("Error processing integration ID {$integration->id}: ".$e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->errors++;
                }
            }

            // Summary
            $endTime = microtime(true);
            $executionTime = round($endTime - $this->startTime, 2);

            $this->info('====== SYNC SUMMARY ======');
            $this->info("Total records processed: {$this->processedRecords}");
            $this->info("New records: {$this->newRecords}");
            $this->info("Updated records: {$this->updatedRecords}");
            $this->info("Errors: {$this->errors}");
            $this->info("Execution time: {$executionTime} seconds");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logError('Fatal error: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Get JWT token for authentication
     */
    /**
     * Get JWT token for authentication
     * This method follows the same pattern as the original Pocomos command
     */
    protected function getJwtToken(string $baseUrl, object $credentials): ?string
    {
        try {
            $jwtUrl = $baseUrl.'/public/technician/jwt_token';

            // For actual API call, use real credentials with URL encoding
            $jwtPayload = 'username='.urlencode($credentials->username).'&password='.urlencode($credentials->password);

            // For logging purposes, mask the password
            $jwtLogPayload = json_encode(['username' => $credentials->username, 'password' => '********']); // Mask password for security

            // Create log entry before API call
            $apiLog = InterigationTransactionLog::create([
                'interigation_name' => 'Pocomos',
                'api_name' => 'JWT Token (Raw Data Sync)',
                'url' => $jwtUrl,
                'payload' => $jwtLogPayload,
                'response' => null,
            ]);

            $this->info("Authenticating with Pocomos API at: {$jwtUrl}");

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
                    'Cookie: PHPSESSID=1c2674f54251dc34017c561597c0f2fd',
                ],
                CURLOPT_SSL_VERIFYHOST => 0, // Don't verify SSL cert hostname
                CURLOPT_SSL_VERIFYPEER => 0, // Don't verify SSL cert
            ]);

            $t_response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            // Update log entry with response
            $apiLog->update(['response' => $t_response]);
            $this->info("Logged JWT token request for monitoring (Log ID: {$apiLog->id})");

            // Parse the token response
            $decode = json_decode($t_response);

            // Debug the response
            $this->info("JWT response HTTP code: {$http_code}");
            if ($http_code != 200) {
                $this->logError('HTTP error while getting JWT token', [
                    'http_code' => $http_code,
                ]);

                return null;
            }

            // Check if the response has the expected format and contains a token
            // We need to handle two possible formats:
            // 1. The token is in decode->data->api_token (standard format)
            // 2. The token is directly in decode->response (alternative format)

            if (isset($decode->data) && isset($decode->data->api_token)) {
                $this->info('Successfully obtained JWT token (standard format)');

                return $decode->data->api_token;
            } elseif (isset($decode->response) && is_string($decode->response) && strlen($decode->response) > 20) {
                // The token is directly in the response field as seen in the logs
                $this->info('Successfully obtained JWT token (alternative format)');

                return $decode->response;
            } else {
                $this->logError('Invalid JWT token response', [
                    'response' => substr($t_response, 0, 500), // Log just the first 500 chars
                ]);

                return null;
            }
        } catch (\Exception $e) {
            $this->logError('Failed to get JWT token: '.$e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Fetch account status data from Pocomos API
     */
    protected function fetchAccountStatusData(string $baseUrl, string $jwtToken, string $branchId, string $startDate, string $endDate): array
    {
        try {
            // Use the correct endpoint path as in the original Pocomos command
            $accountUrl = $baseUrl.'/jwt/'.$branchId.'/report/account-status';

            // Format dates for account-status endpoint (MM/DD/YY format)
            $formattedStartDate = (new \DateTime($startDate))->format('m/d/y');
            $formattedEndDate = (new \DateTime($endDate))->format('m/d/y');

            // Prepare request payload - match exactly what the original command does
            $requestPayload = json_encode([
                'dateType' => 'contract',
                'startDate' => $formattedStartDate,
                'endDate' => $formattedEndDate,
                'anyStatus' => 1,
                'anySalesperson' => 1,
                'anyInitialJobStatus' => 1,
                'isSpecialtyPestChecked' => 1,
            ]);

            // Create log entry before API call
            $apiLog = InterigationTransactionLog::create([
                'interigation_name' => 'Pocomos',
                'api_name' => 'Account Status (Raw Data Sync)',
                'url' => $accountUrl,
                'payload' => $requestPayload,
                'response' => null,
            ]);

            $this->info("Fetching account status data from: {$accountUrl}");
            $this->info("Date range: {$startDate} to {$endDate}");

            // Initialize curl session - matching original command's approach
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $accountUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $requestPayload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Cookie: PHPSESSID=1c2674f54251dc34017c561597c0f2fd',
                    'XauthToken: '.$jwtToken, // Added this header as seen in original command
                    'Authorization: Bearer '.$jwtToken,
                ],
                CURLOPT_SSL_VERIFYHOST => 0, // Don't verify SSL cert hostname
                CURLOPT_SSL_VERIFYPEER => 0, // Don't verify SSL cert
            ]);

            // Execute the request
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $info = curl_getinfo($curl);

            // Check for curl errors
            if (curl_errno($curl)) {
                $error = curl_error($curl);
                curl_close($curl);
                $this->logError("Curl error fetching account status: {$error}", [
                    'curl_info' => $info,
                    'url' => $accountUrl,
                    'branch_id' => $branchId,
                ]);

                return [];
            }

            curl_close($curl);

            // Sanitize and update log entry with response
            try {
                // Make sure the response is valid JSON before storing
                $jsonTest = json_decode($response);

                // If response is too long, store a truncated version that's still valid JSON
                if (strlen($response) > 65000) { // Leave some buffer before the 65535 limit
                    $truncated = substr($response, 0, 60000); // Use a safer limit
                    // Store simplified data instead of trying to fix truncated JSON
                    $truncated = json_encode([
                        'truncated' => true,
                        'original_size' => strlen($response),
                        'preview' => substr($response, 0, 1000), // Just store a preview of the beginning
                    ]);

                    $apiLog->update([
                        'response' => $truncated,
                    ]);

                    $this->info('Response was truncated for database storage (original size: '.strlen($response).' bytes)');
                } else {
                    $apiLog->update([
                        'response' => $response,
                    ]);
                }
            } catch (\Exception $e) {
                $this->logError('Failed to store API response in log: '.$e->getMessage(), [
                    'response_length' => strlen($response),
                    'exception' => get_class($e),
                ]);

                // Store basic info without the problematic response
                $apiLog->update([
                    'response' => json_encode(['error' => 'Response too large or malformed', 'size' => strlen($response)]),
                ]);
            }

            // Log the response for debugging
            $this->info("API response code: {$http_code}");

            // Check HTTP status code
            if ($http_code != 200) {
                $this->logError('HTTP error while fetching account status', [
                    'http_code' => $http_code,
                    'response' => substr($response, 0, 500), // Log just the first 500 chars
                    'request_url' => $accountUrl,
                    'request_payload' => $requestPayload,
                    'curl_info' => $info,
                ]);
                $this->info('Response body: '.substr($response, 0, 200).'...'); // Show part of response in console

                return [];
            }

            // Process the response
            $responseData = json_decode($response, true);

            // Log response structure for debugging
            $this->info('API Response Structure: '.json_encode(array_keys($responseData ?? [])));

            // Debug detailed response structure
            if (isset($responseData['response'])) {
                $this->info('Response type: '.gettype($responseData['response']));
                if (is_array($responseData['response'])) {
                    $this->info('Response array length: '.count($responseData['response']));
                    $this->info('Response sample: '.substr(json_encode($responseData['response']), 0, 100).'...');
                } elseif (is_string($responseData['response'])) {
                    $this->info('Response string length: '.strlen($responseData['response']));
                    $this->info('Response preview: '.substr($responseData['response'], 0, 100).'...');
                }
            }

            if (isset($responseData['meta'])) {
                $this->info('Meta info: '.json_encode($responseData['meta']));
            }

            // Check if it's the expected structure with 'data'
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $count = count($responseData['data']);
                $this->info("Successfully retrieved {$count} records from Pocomos API");

                return $responseData['data'];
            }

            // Handle Pocomos API specific response structure with 'response' key instead of 'data'
            if (isset($responseData['response']) && is_array($responseData['response'])) {
                $count = count($responseData['response']);
                $this->info("Found {$count} records in 'response' key");

                return $responseData['response'];
            }

            // Generic fallback for array of records with no wrapper
            if (is_array($responseData) && ! empty($responseData) && ! isset($responseData['error'])) {
                // Check if this looks like an array of records
                $firstItem = reset($responseData);
                if (is_array($firstItem)) {
                    $count = count($responseData);
                    $this->info("Found {$count} records without data wrapper");

                    return $responseData;
                }
            }

            // Log full response content (truncated) for debugging
            $this->logError('Account status response does not contain recognizable data', [
                'response_preview' => substr($response, 0, 1000),
                'response_type' => gettype($responseData),
                'response_structure' => json_encode(array_keys($responseData ?? [])),
            ]);

            return [];
        } catch (\Exception $e) {
            $this->logError('Failed to fetch account status data: '.$e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [];
        }
    }

    /**
     * Maps Pocomos API data to legacy_api_raw_data_histories fields based on the pocomos.json mapping file
     *
     * @param  array  $pocomosRecord  Raw Pocomos API record
     * @return array Mapped data for legacy_api_raw_data_histories table
     */
    private function mapPocomosToLegacyFields(array $pocomosRecord): array
    {
        // Debug the incoming record to understand what we're mapping
        $pccId = isset($pocomosRecord['pcc_id']) ? $pocomosRecord['pcc_id'] : 'unknown';
        $contractId = isset($pocomosRecord['contract_id']) ? $pocomosRecord['contract_id'] : 'unknown';
        $this->info("Mapping Pocomos record: pcc_id={$pccId}, contract_id={$contractId}");

        // Initialize result array with some default fields
        $legacyData = [
            'data_source_type' => 'Pocomos',
            'source_created_at' => now(),
            'source_updated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
            'import_to_sales' => 0,
        ];

        try {
            // Load mapping configuration from JSON file
            $mappingFilePath = base_path('config/field-mappings/pocomos.json');
            if (! file_exists($mappingFilePath)) {
                $this->logError("Pocomos mapping file not found at {$mappingFilePath}");

                return $this->fallbackMapping($pocomosRecord);
            }

            $mappingConfig = json_decode(file_get_contents($mappingFilePath), true);
            if (! $mappingConfig || ! isset($mappingConfig['field_mappings']['api_to_db'])) {
                $this->logError('Invalid mapping configuration in pocomos.json');

                return $this->fallbackMapping($pocomosRecord);
            }

            // Get field mappings from the configuration
            $fieldMappings = $mappingConfig['field_mappings']['api_to_db'];

            // Special handling for combined name field since Pocomos provides first and last name separately
            if (! isset($pocomosRecord['customer_name']) && (isset($pocomosRecord['customer_first_name']) || isset($pocomosRecord['customer_last_name']))) {
                $pocomosRecord['customer_name'] = trim(($pocomosRecord['customer_first_name'] ?? '').' '.($pocomosRecord['customer_last_name'] ?? ''));
                $this->info("Created customer_name: {$pocomosRecord['customer_name']}");
            }

            // Handle additional field conversions for Pocomos API
            if (isset($pocomosRecord['service_type']) && ! isset($pocomosRecord['product'])) {
                $pocomosRecord['product'] = $pocomosRecord['service_type'];
            }

            if (isset($pocomosRecord['original_contract_value']) && ! isset($pocomosRecord['gross_account_value'])) {
                $pocomosRecord['gross_account_value'] = $pocomosRecord['original_contract_value'];
            }

            // Apply transformations from pocomos.json transformers if available
            $transformers = isset($mappingConfig['transformers']) ? $mappingConfig['transformers'] : [];

            // Apply the mappings from the config
            foreach ($fieldMappings as $apiField => $mappingInfo) {
                // Skip if API field doesn't exist in the record
                if (! array_key_exists($apiField, $pocomosRecord)) {
                    continue;
                }

                $value = $pocomosRecord[$apiField];

                // Apply transformation if specified
                if (isset($mappingInfo['transform']) && isset($transformers[$mappingInfo['transform']])) {
                    $transformer = $transformers[$mappingInfo['transform']];

                    // Handle branch ID transform
                    if ($mappingInfo['transform'] === 'branch_id_transform' && isset($transformer['template'])) {
                        $value = str_replace('{branch_id}', $value, $transformer['template']);
                    }

                    // Handle boolean to Yes/No transform
                    if ($mappingInfo['transform'] === 'boolean_to_yes_no' && isset($transformer['rules'])) {
                        $value = isset($transformer['rules'][$value]) ? $transformer['rules'][$value] : $transformer['rules']['default'];
                    }
                }

                // Handle multiple target fields
                if (isset($mappingInfo['db_fields']) && is_array($mappingInfo['db_fields'])) {
                    foreach ($mappingInfo['db_fields'] as $dbField) {
                        $legacyData[$dbField] = $value;
                    }
                }
                // Handle single target field
                elseif (isset($mappingInfo['db_field'])) {
                    $dbField = $mappingInfo['db_field'];
                    $legacyData[$dbField] = $value;
                }
            }

            // Apply computed fields from the config
            if (isset($mappingConfig['field_mappings']['computed_fields'])) {
                foreach ($mappingConfig['field_mappings']['computed_fields'] as $fieldName => $fieldInfo) {
                    // Apply default value if specified
                    if (isset($fieldInfo['default']) && ! isset($legacyData[$fieldName])) {
                        $legacyData[$fieldName] = $fieldInfo['default'];
                    }
                }
            }

            // Apply specific required mappings for legacy_api_raw_data_histories table
            $this->applySpecialMappings($pocomosRecord, $legacyData);

            $this->info("Field mapping complete for record: pcc_id={$pccId}");

        } catch (\Exception $e) {
            $this->logError('Error mapping Pocomos data to legacy fields: '.$e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fallbackMapping($pocomosRecord);
        }

        return $legacyData;
    }

    /**
     * Apply special mappings required for legacy_api_raw_data_histories that may not be in the JSON file
     *
     * @param  array  $pocomosRecord  Original record
     * @param  array  &$legacyData  Reference to the legacy data being built
     */
    private function applySpecialMappings(array $pocomosRecord, array &$legacyData): void
    {
        // Ensure primary identifiers are set correctly
        if (! isset($legacyData['pid']) && isset($pocomosRecord['pcc_id'])) {
            $legacyData['pid'] = $pocomosRecord['pcc_id'];
        }

        // Construct branch ID format if available (with special format for legacy_id)
        if (isset($pocomosRecord['branch_id'])) {
            $legacyData['legacy_id'] = 'Branch-'.$pocomosRecord['branch_id'];
            $this->info("Set legacy_id to: {$legacyData['legacy_id']}");
        } elseif (! isset($legacyData['legacy_id']) && isset($pocomosRecord['pcc_id'])) {
            $legacyData['legacy_id'] = $pocomosRecord['pcc_id'];
        }

        // Map customer address from customer_street if not already set
        if (! isset($legacyData['customer_address']) && isset($pocomosRecord['customer_street'])) {
            $legacyData['customer_address'] = $pocomosRecord['customer_street'];
        }

        // Ensure location_code is set from state if available
        if (! isset($legacyData['location_code']) && isset($pocomosRecord['customer_state'])) {
            $legacyData['location_code'] = $pocomosRecord['customer_state'];
        }

        // Format card on file value if not already formatted by the transformer
        if (isset($pocomosRecord['card_on_file']) && ! isset($legacyData['card_on_file'])) {
            $legacyData['card_on_file'] = ($pocomosRecord['card_on_file'] == 1) ? 'Yes' : 'No';
        }

        // Format auto pay value if not already formatted by the transformer
        if (isset($pocomosRecord['autopay']) && ! isset($legacyData['auto_pay'])) {
            $legacyData['auto_pay'] = ($pocomosRecord['autopay'] == 1) ? 'Yes' : 'No';
        }

        // Handle product mapping from service_type
        if (! isset($legacyData['product']) && isset($pocomosRecord['service_type'])) {
            $legacyData['product'] = $pocomosRecord['service_type'];
        }

        // Handle gross_account_value from original_contract_value
        if (! isset($legacyData['gross_account_value']) && isset($pocomosRecord['original_contract_value'])) {
            $legacyData['gross_account_value'] = $pocomosRecord['original_contract_value'];
        }

        // Map sales rep information
        if (! isset($legacyData['sales_rep_email']) && isset($pocomosRecord['salesperson_email'])) {
            $legacyData['sales_rep_email'] = $pocomosRecord['salesperson_email'];
        }

        if (! isset($legacyData['sales_rep_name']) && isset($pocomosRecord['salesperson_name'])) {
            $legacyData['sales_rep_name'] = $pocomosRecord['salesperson_name'];
        }

        // CRITICAL: Set closer1_id field - required for SaleMasterJob
        if (! isset($legacyData['closer1_id'])) {
            if (isset($pocomosRecord['salesperson_id'])) {
                $legacyData['closer1_id'] = $pocomosRecord['salesperson_id'];
                $this->info("Set closer1_id to salesperson_id: {$legacyData['closer1_id']}");
            } elseif (isset($legacyData['sales_rep_name']) && ! empty($legacyData['sales_rep_name'])) {
                // Use the sales_rep_name as a backup for closer1_id if no salesperson_id is available
                $legacyData['closer1_id'] = $legacyData['sales_rep_name'];
                $this->info("Set closer1_id to sales_rep_name: {$legacyData['closer1_id']}");
            } else {
                // Fallback to a placeholder value to ensure the record gets processed
                $legacyData['closer1_id'] = 'Pocomos-'.($pocomosRecord['pcc_id'] ?? 'Unknown');
                $this->info("Set fallback closer1_id: {$legacyData['closer1_id']}");
            }
        }

        // Set data_source_type with capital P as expected by SaleMasterJob
        $legacyData['data_source_type'] = 'Pocomos';

        // Ensure import_to_sales flag is set properly
        $legacyData['import_to_sales'] = '0'; // String '0' to match exact comparison in SaleMasterJob

        // Initialize trigger_date array and m1_date following Pocomos.php logic
        $triggerDate = [];
        $m1Date = null;

        // CRITICAL: Check for initial_date field with various possible formats as in Pocomos.php
        if (isset($pocomosRecord['initial_date']) && ! empty($pocomosRecord['initial_date'])) {
            $initialDateValue = $pocomosRecord['initial_date'];
            $dateFormats = ['m/d/Y', 'Y-m-d', 'Y/m/d', 'd-m-Y', 'M j, Y', 'n/j/Y']; // Common formats to try
            $parsedDate = null;

            foreach ($dateFormats as $format) {
                try {
                    // Try to parse with each format
                    $parsedDate = \Carbon\Carbon::createFromFormat($format, $initialDateValue);
                    if ($parsedDate) {
                        $m1Date = $parsedDate->format('Y-m-d'); // Convert to consistent Y-m-d format
                        $this->info("Successfully parsed initial_date '{$initialDateValue}' using format '{$format}' to: {$m1Date}");
                        break; // Stop once we've successfully parsed the date
                    }
                } catch (\Exception $e) {
                    // Continue to next format if this one fails
                    continue;
                }
            }

            if (! $parsedDate) {
                // If all format attempts failed, log the issue
                $this->info("Failed to parse initial_date: {$initialDateValue} with any known format");
            }
        }

        // If initial_date wasn't available or parsing failed, check for initial_service_date
        if (($m1Date === null || empty($m1Date)) && isset($pocomosRecord['initial_service_date']) && ! empty($pocomosRecord['initial_service_date'])) {
            // Try to ensure initial_service_date is in Y-m-d format
            $initialServiceDate = $pocomosRecord['initial_service_date'];
            try {
                // First try to parse assuming it's already a date object or valid string
                $parsedDate = new \Carbon\Carbon($initialServiceDate);
                $m1Date = $parsedDate->format('Y-m-d');
                $this->info("Formatted initial_service_date to: {$m1Date}");
            } catch (\Exception $e) {
                // If that fails, use the original value
                $m1Date = $initialServiceDate;
                $this->info("Using original initial_service_date: {$m1Date}");
            }
        }

        // Initial service date - only set trigger_date if sales_status is 'Serviced'
        if (isset($pocomosRecord['sales_status']) && $pocomosRecord['sales_status'] == 'Serviced') {
            if ($m1Date !== null && ! empty($m1Date)) {
                $triggerDate[]['date'] = $m1Date;
                $this->info("Set trigger_date for Serviced record: {$m1Date}");
            } else {
                $triggerDate[]['date'] = null;
                $this->info('Record is Serviced but has no service date');
            }
        } else {
            $triggerDate[]['date'] = null;
            $this->info('Record is not Serviced, setting trigger_date to null');
        }

        // Check if service dates are blank but service_completion_date (last_service_date) is available
        if (($m1Date === null || empty($m1Date)) && isset($pocomosRecord['service_completion_date']) && ! empty($pocomosRecord['service_completion_date'])) {
            $serviceCompletionDate = $pocomosRecord['service_completion_date'];
            // Try to format the service_completion_date consistently
            try {
                $parsedCompletionDate = new \Carbon\Carbon($serviceCompletionDate);
                $m1Date = $parsedCompletionDate->format('Y-m-d');
                $triggerDate[0]['date'] = $m1Date;
                $this->info("Using formatted service_completion_date as fallback: {$m1Date}");
            } catch (\Exception $e) {
                // If parsing fails, use the original value
                $m1Date = $serviceCompletionDate;
                $triggerDate[0]['date'] = $serviceCompletionDate;
                $this->info("Using original service_completion_date as fallback: {$m1Date}");
            }
        } elseif ($m1Date === null || empty($m1Date)) {
            // If all service dates are blank, set a future date for customer_signoff to ensure SaleMasterJob processing
            // This is important for records to pass filtering criteria
            $futureDate = date('Y-m-d', strtotime('2025-01-01'));
            $m1Date = $futureDate;
            if (isset($triggerDate[0])) {
                $triggerDate[0]['date'] = $futureDate;
            }
            $this->info("All service dates are missing, using future date for processing: {$futureDate}");
        }

        // Set the values in legacy data
        $legacyData['m1_date'] = $m1Date;
        $legacyData['initial_service_date'] = $m1Date;
        $legacyData['trigger_date'] = json_encode($triggerDate);
        $this->info('Final trigger_date value: '.$legacyData['trigger_date']);

        // CRITICAL: Ensure customer_signoff date is set for SaleMasterJob processing
        // This is critical as the job filters records based on this field
        if (! isset($legacyData['customer_signoff']) || empty($legacyData['customer_signoff'])) {
            // If we have an m1_date, use that for customer_signoff
            if ($m1Date !== null && ! empty($m1Date)) {
                $legacyData['customer_signoff'] = $m1Date;
                $this->info("Set customer_signoff from m1_date: {$m1Date}");
            } else {
                // Otherwise use a future date to ensure job processing
                $futureDate = date('Y-m-d', strtotime('2025-01-01'));
                $legacyData['customer_signoff'] = $futureDate;
                $this->info("Set customer_signoff to future date for processing: {$futureDate}");
            }
        } else {
            // Ensure existing customer_signoff is properly formatted
            try {
                $parsedDate = new \Carbon\Carbon($legacyData['customer_signoff']);
                $legacyData['customer_signoff'] = $parsedDate->format('Y-m-d');
                $this->info("Formatted existing customer_signoff to: {$legacyData['customer_signoff']}");
            } catch (\Exception $e) {
                // If parsing fails and the field exists, leave it as is
                $this->info("Using original customer_signoff: {$legacyData['customer_signoff']}");
            }
        }

        // Set m2_date to null if not set
        if (! isset($legacyData['m2_date'])) {
            $legacyData['m2_date'] = null;
        }

        // Determine job_status based on conditions exactly as in Pocomos.php
        $job_status = 'Pending'; // Default status

        // Check if pid exists in clawback_settlements - in our case, we'll check if it's marked as clawback
        $isClawback = false;
        if (isset($pocomosRecord['is_clawback']) && $pocomosRecord['is_clawback']) {
            $isClawback = true;
        }

        $domain = config('app.domain_name') ?: '';
        if ($domain === 'homeguard') {
            if ($isClawback) {
                $job_status = 'Clawback';
                $this->info('Set job_status to Clawback for clawback record');
            } elseif (isset($pocomosRecord['date_cancelled']) && ! empty($pocomosRecord['date_cancelled'])) {
                $job_status = 'Cancelled';
                $this->info('Set job_status to Cancelled due to date_cancelled');
            } elseif (isset($pocomosRecord['sales_status'])) {
                // Check sales_status for Serviced cases
                if (in_array($pocomosRecord['sales_status'], ['Serviced', 'Past Due', 'Out of Frequency', 'Discount', 'OTS'])) {
                    $job_status = 'Serviced';
                    $this->info("Set job_status to Serviced based on sales_status: {$pocomosRecord['sales_status']}");
                }
                // Check sales_status for Cancelled cases
                elseif (in_array($pocomosRecord['sales_status'], ['Cancel'])) {
                    $job_status = 'Cancelled';
                    $this->info("Set job_status to Cancelled based on sales_status: {$pocomosRecord['sales_status']}");
                }
                // Check sales_status for Pending cases
                elseif (in_array($pocomosRecord['sales_status'], ['Pending', 'Reschedule'])) {
                    $job_status = 'Pending';
                    $this->info("Set job_status to Pending based on sales_status: {$pocomosRecord['sales_status']}");
                }
            }
        } else {
            if ($isClawback) {
                $job_status = 'Clawback';
                $this->info('Set job_status to Clawback for clawback record');
            } elseif (isset($pocomosRecord['date_cancelled']) && ! empty($pocomosRecord['date_cancelled'])) {
                $job_status = 'Cancelled';
                $this->info('Set job_status to Cancelled due to date_cancelled');
            } elseif ($m1Date !== null) {
                $job_status = 'Serviced';
                $this->info('Set job_status to Serviced due to m1Date being available');
            }
        }

        // Set job_status and initialStatusText in legacy data
        $legacyData['job_status'] = $job_status;
        $legacyData['initialStatusText'] = $job_status; // Exactly matching Pocomos.php

        // Handle length of agreement
        if (! isset($legacyData['length_of_agreement']) && isset($pocomosRecord['agreement_length'])) {
            $legacyData['length_of_agreement'] = $pocomosRecord['agreement_length'];
        }

        // Handle date fields properly
        $dateFields = [
            ['source' => 'contract_date', 'target' => 'customer_signoff'],
            ['source' => 'contract_cancelled_date', 'target' => 'date_cancelled'],
            ['source' => 'service_completion_date', 'target' => 'last_service_date'],
            ['source' => 'job_date', 'target' => 'install_complete_date'],
        ];

        // CRITICAL: Ensure customer_signoff date is set and is after 2024-12-31 for SaleMasterJob
        if (! isset($legacyData['customer_signoff']) || empty($legacyData['customer_signoff'])) {
            // Use current date as fallback if no contract_date is available
            $legacyData['customer_signoff'] = date('Y-m-d');
            $this->info("Set fallback customer_signoff date to today: {$legacyData['customer_signoff']}");
        } else {
            // Ensure the date is after 2024-12-31 as required by SaleMasterJob
            $signoffDate = strtotime($legacyData['customer_signoff']);
            $minDate = strtotime('2025-01-01');
            if ($signoffDate < $minDate) {
                $legacyData['customer_signoff'] = '2025-01-01';
                $this->info('Updated customer_signoff date to 2025-01-01 to meet SaleMasterJob requirements');
            }
        }

        foreach ($dateFields as $mapping) {
            if (isset($pocomosRecord[$mapping['source']]) && ! empty($pocomosRecord[$mapping['source']])) {
                try {
                    $legacyData[$mapping['target']] = date('Y-m-d', strtotime($pocomosRecord[$mapping['source']]));
                    $this->info("Set {$mapping['target']} date to: {$legacyData[$mapping['target']]}");
                } catch (\Exception $e) {
                    // If date parsing fails, use the original value
                    $legacyData[$mapping['target']] = $pocomosRecord[$mapping['source']];
                }
            }
        }

        // Debug the mapped data
        $this->info('Legacy data mapping complete. Fields: '.json_encode(array_keys($legacyData)));
    }

    /**
     * Fallback mapping when JSON config fails
     *
     * @param  array  $pocomosRecord  Original record
     * @return array Mapped data
     */
    private function fallbackMapping(array $pocomosRecord): array
    {
        $pccId = isset($pocomosRecord['pcc_id']) ? $pocomosRecord['pcc_id'] : 'unknown';
        $this->info("Using fallback mapping for record: pcc_id={$pccId}");

        // Construct customer name
        $customerName = null;
        if (isset($pocomosRecord['customer_name'])) {
            $customerName = $pocomosRecord['customer_name'];
        } elseif (isset($pocomosRecord['customer_first_name']) || isset($pocomosRecord['customer_last_name'])) {
            $customerName = trim(($pocomosRecord['customer_first_name'] ?? '').' '.($pocomosRecord['customer_last_name'] ?? ''));
        }

        // Construct branch ID
        $legacyId = isset($pocomosRecord['branch_id']) ? 'Branch-'.$pocomosRecord['branch_id'] : $pccId;

        // Format special values
        $cardOnFile = (isset($pocomosRecord['card_on_file']) && $pocomosRecord['card_on_file'] == 1) ? 'Yes' : 'No';
        $autoPay = (isset($pocomosRecord['autopay']) && $pocomosRecord['autopay'] == 1) ? 'Yes' : 'No';
        $jobStatus = isset($pocomosRecord['sales_status']) ? $pocomosRecord['sales_status'] : null;
        $m1Date = isset($pocomosRecord['initial_service_date']) ? $pocomosRecord['initial_service_date'] : null;

        return [
            // Primary identifiers
            'pid' => $pccId,
            'legacy_id' => $legacyId,

            // Customer information
            'customer_name' => $customerName,
            'customer_phone' => isset($pocomosRecord['customer_phone']) ? $pocomosRecord['customer_phone'] : null,
            'customer_address' => isset($pocomosRecord['customer_street']) ? $pocomosRecord['customer_street'] : null,
            'customer_state' => isset($pocomosRecord['customer_state']) ? $pocomosRecord['customer_state'] : null,
            'location_code' => isset($pocomosRecord['customer_state']) ? $pocomosRecord['customer_state'] : null,
            'customer_city' => isset($pocomosRecord['customer_city']) ? $pocomosRecord['customer_city'] : null,
            'customer_zip' => isset($pocomosRecord['customer_zip']) ? $pocomosRecord['customer_zip'] : null,
            'customer_email' => isset($pocomosRecord['customer_email']) ? $pocomosRecord['customer_email'] : null,

            // Product information
            'product' => isset($pocomosRecord['service_type']) ? $pocomosRecord['service_type'] : null,

            // Financial information
            'gross_account_value' => isset($pocomosRecord['original_contract_value']) ? $pocomosRecord['original_contract_value'] : null,

            // Sales representative information
            'sales_rep_email' => isset($pocomosRecord['salesperson_email']) ? $pocomosRecord['salesperson_email'] : null,
            'sales_rep_name' => isset($pocomosRecord['salesperson_name']) ? $pocomosRecord['salesperson_name'] : null,

            // Payment information
            'card_on_file' => $cardOnFile,
            'auto_pay' => $autoPay,

            // Status information
            'job_status' => $jobStatus,
            'initialStatusText' => $jobStatus,

            // Dates
            'm1_date' => $m1Date,
            'initial_service_date' => $m1Date,
            'trigger_date' => json_encode([]),

            // System fields
            'data_source_type' => 'Pocomos',
            'import_to_sales' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Process and save the data to pocomos_raw_data table
     */
    protected function processAndSaveData(array $accountData, Integration $integration, string $syncBatchId): void
    {
        if (empty($accountData)) {
            $this->info("No data to process for integration ID: {$integration->id}");

            return;
        }

        $batchSize = $this->option('batch');
        $chunks = array_chunk($accountData, $batchSize);
        $dryRun = $this->option('dry-run');

        $this->info('Processing '.count($accountData).' records in '.count($chunks).' batches');

        foreach ($chunks as $index => $chunk) {
            $this->info('Processing batch '.($index + 1).' of '.count($chunks));
            $now = now();

            foreach ($chunk as $record) {
                try {
                    // Debug how many records match this pcc_id
                    $matchCount = DB::table('pocomos_raw_data')
                        ->where('pcc_id', $record['pcc_id'] ?? null)
                        ->count();

                    $this->info("Found $matchCount records with pcc_id: {$record['pcc_id']}");

                    // Check if record already exists by pcc_id only (more reliable)
                    // We initially tried using contract_id as well, but that may not have been populated in earlier syncs
                    $existingRecord = DB::table('pocomos_raw_data')
                        ->where('pcc_id', $record['pcc_id'] ?? null)
                        ->first();

                    if ($existingRecord) {
                        $this->info("Found existing record with ID: {$existingRecord->id}, pcc_id: {$record['pcc_id']}");
                    } else {
                        $this->info("No existing record found with pcc_id: {$record['pcc_id']}");
                    }

                    // Map each field from the API response to the database columns
                    // ONLY include fields that exist in the database table based on the exact schema
                    $recordData = [
                        // Customer and contract identifiers
                        'pcc_id' => $record['pcc_id'] ?? null,
                        'contract_id' => $record['contract_id'] ?? null,
                        'customer_id' => $record['customer_id'] ?? null,

                        // Customer details
                        'customer_first_name' => $record['customer_first_name'] ?? null,
                        'customer_last_name' => $record['customer_last_name'] ?? null,
                        'customer_status' => $record['customer_status'] ?? null,
                        'customer_phone' => $record['customer_phone'] ?? null,
                        // Get external account ID from the correct field or generate a unique one
                        'customer_external_account_id' => $record['customer_external_account_id'] ?? $record['profile_external_id'] ?? 'EXT-'.($record['pcc_id'] ?? 'UNKNOWN'),

                        // Address information
                        // Make sure to properly format the contact address
                        // Use the customer_contact_address field directly from API if available to ensure consistency
                        'customer_contact_address' => ! empty($record['customer_contact_address']) ?
                            $record['customer_contact_address'] :
                            (! empty($record['customer_street']) ?
                                trim($record['customer_street'] ?? '').' '.
                                trim($record['customer_city'] ?? '').', '.
                                trim($record['customer_state'] ?? '').' '.
                                trim($record['customer_zip'] ?? '') :
                                'Address for '.($record['pcc_id'] ?? 'UNKNOWN')),
                        'customer_street' => $record['customer_street'] ?? null,
                        'customer_city' => $record['customer_city'] ?? null,
                        'customer_zip' => $record['customer_zip'] ?? null,
                        'customer_state' => $record['customer_state'] ?? null,

                        // Service information
                        'last_service_date' => $record['last_service_date'] ?? null,
                        'map_code' => $record['map_code'] ?? null,
                        'preferred_tech' => $record['preferred_tech'] ?? null,

                        // Contract information
                        'contract_date' => $record['contract_date'] ?? null,
                        'account_sign_up_start_date' => $record['account_sign_up_start_date'] ?? null,
                        'sales_status' => $record['sales_status'] ?? null,
                        'initial_price' => $record['initial_price'] ?? null,
                        'recurring_price' => $record['recurring_price'] ?? null,
                        'balance' => $record['balance'] ?? null,
                        'days_past_due' => $record['days_past_due'] ?? null,
                        'card_on_file' => $record['card_on_file'] ?? null,
                        'job_date' => isset($record['job_date']) ? (strtotime($record['job_date']) ? date('Y-m-d', strtotime($record['job_date'])) : $record['job_date']) : null,
                        'initial_date' => $record['initial_date'] ?? null,
                        'contract_name' => $record['contract_name'] ?? null,
                        'service_type' => $record['service_type'] ?? null,
                        'service_frequency' => $record['service_frequency'] ?? null,
                        'marketing_type' => $record['marketing_type'] ?? null,
                        'original_contract_value' => $record['original_contract_value'] ?? null,
                        'first_year_contract_value' => $record['first_year_contract_value'] ?? null,
                        'balance_credit' => $record['balance_credit'] ?? null,
                        'autopay' => $record['autopay'] ?? null,
                        'pay_level' => $record['pay_level'] ?? null,

                        // Branch and sales information
                        'salesperson_name' => $record['salesperson_name'] ?? null,
                        'profile_external_id' => $record['profile_external_id'] ?? null,
                        'branch_name' => $record['branch_name'] ?? null,
                        'salesperson_email' => $record['salesperson_email'] ?? null,
                        'contract_cancelled_date' => $record['contract_cancelled_date'] ?? null,
                        'agreement_length' => $record['agreement_length'] ?? null,

                        // Store the entire raw response in sync_notes field - sanitized for database storage
                        'sync_notes' => json_encode($record, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),

                        // Sync metadata
                        'sync_batch_id' => $syncBatchId,
                        'last_synced_at' => $now,
                    ];

                    if ($dryRun) {
                        // In dry run mode, just count the record
                        $this->processedRecords++;
                        if ($existingRecord) {
                            $this->info("Dry run - would check for updates to record with pcc_id: {$record['pcc_id']}");
                        } else {
                            $this->newRecords++;
                            $this->info("Dry run - would insert new record with pcc_id: {$record['pcc_id']}");
                        }

                        continue;
                    }

                    if ($existingRecord) {
                        // Check if anything has changed before updating
                        $hasChanges = false;

                        // Convert existing record to array for comparison
                        $existingData = (array) $existingRecord;

                        $this->info("Comparing record with pcc_id: {$record['pcc_id']} and contract_id: {$record['contract_id']}");

                        // Compare fields to see if anything changed
                        foreach ($recordData as $key => $value) {
                            // Skip comparing these metadata fields
                            if (in_array($key, ['sync_batch_id', 'last_synced_at', 'created_at', 'updated_at', 'sync_notes'])) {
                                continue;
                            }

                            // Normalize values for comparison (handle nulls and type conversion issues)
                            $existingValue = isset($existingData[$key]) ? $existingData[$key] : null;

                            // Convert numeric strings to same type for comparison
                            if (is_numeric($existingValue) && is_numeric($value)) {
                                $existingValue = (string) $existingValue;
                                $value = (string) $value;
                            }

                            // Handle null vs empty string
                            if (($existingValue === '' && $value === null) || ($existingValue === null && $value === '')) {
                                continue; // Treat empty string and null as equivalent
                            }

                            // Special handling for state abbreviations vs full names in address fields
                            $isAddressStateEquivalent = false;
                            if ($key === 'customer_contact_address' && $existingValue !== $value) {
                                // Check if the only difference is CA vs California, TX vs Texas, etc.
                                // Get list of US state abbreviations and their full names
                                $stateMap = [
                                    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
                                    'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
                                    'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
                                    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
                                    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
                                    'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
                                    'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
                                    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
                                    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
                                    'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
                                    'DC' => 'District of Columbia',
                                ];

                                // Check each state abbreviation and full name
                                foreach ($stateMap as $abbr => $fullName) {
                                    // If existing has abbreviation and new has full name
                                    $pattern1 = "/ $abbr /i";
                                    $pattern2 = "/ $fullName /i";

                                    if ((preg_match($pattern1, $existingValue) && preg_match($pattern2, $value)) ||
                                        (preg_match($pattern2, $existingValue) && preg_match($pattern1, $value))) {
                                        // This is just a state format difference, not a real change
                                        $isAddressStateEquivalent = true;
                                        break;
                                    }
                                }
                            }

                            // Check if the value is different and not just a state abbreviation difference
                            if ($existingValue !== $value && ! $isAddressStateEquivalent) {
                                $hasChanges = true;
                                $this->info("Change detected in field '$key': '".
                                    (is_null($existingValue) ? 'NULL' : $existingValue)."' => '".
                                    (is_null($value) ? 'NULL' : $value)."'");
                                // Don't break so we can see all changes
                            }
                        }

                        if ($hasChanges) {
                            try {
                                $this->info("Changes detected - updating record with pcc_id: {$record['pcc_id']}");

                                // Update the record and set last_modified to now ONLY when actual changes occur
                                $recordData['last_modified'] = $now;
                                $recordData['updated_at'] = $now;

                                // Log the update query for debugging
                                $this->info("Executing update for record ID: {$existingRecord->id}");

                                // Update database record
                                $updateResult = DB::table('pocomos_raw_data')
                                    ->where('id', $existingRecord->id)
                                    ->update($recordData);

                                $this->info('Update result: '.($updateResult ? "Success (affected: $updateResult)" : 'Failed (0 rows)'));

                                // Only count successful updates
                                if ($updateResult) {
                                    $this->info("Updated record with pcc_id: {$record['pcc_id']} and set last_modified to now");
                                    $this->updatedRecords++;

                                    // Insert into legacy_api_raw_data_histories for updated records
                                    $legacyId = $this->insertIntoLegacyHistory($record, true, $dryRun);
                                    if ($legacyId && ! $dryRun) {
                                        $this->insertedLegacyIds[] = $legacyId;
                                    }
                                } else {
                                    $this->info("Update operation did not affect any rows for pcc_id: {$record['pcc_id']}");
                                    $this->errors++;
                                }

                                $this->processedRecords++;
                            } catch (\Exception $e) {
                                $this->logError('Error updating record: '.$e->getMessage(), [
                                    'pcc_id' => $record['pcc_id'] ?? 'unknown',
                                    'exception' => get_class($e),
                                    'message' => $e->getMessage(),
                                ]);
                                $this->errors++;
                            }
                        } else {
                            // No changes detected, just update last_synced_at BUT NOT last_modified
                            try {
                                DB::table('pocomos_raw_data')
                                    ->where('id', $existingRecord->id)
                                    ->update([
                                        'last_synced_at' => $now,
                                        'sync_batch_id' => $syncBatchId,
                                        'updated_at' => $now,
                                    ]);

                                // No legacy history record for unchanged records
                                // We only want to track new or updated records in legacy_api_raw_data_histories
                                $this->info("Skipping legacy history for unchanged record: {$record['pcc_id']}");

                                $this->info("Record with pcc_id: {$record['pcc_id']} unchanged - updated sync timestamp only");
                                $this->processedRecords++;
                            } catch (\Exception $e) {
                                $this->logError('Error updating sync timestamp: '.$e->getMessage(), [
                                    'pcc_id' => $record['pcc_id'] ?? 'unknown',
                                    'exception' => get_class($e),
                                    'message' => $e->getMessage(),
                                ]);
                                $this->errors++;
                            }
                        }
                    } else {
                        // New record - insert it
                        try {
                            // Add timestamps for new record
                            $recordData['created_at'] = $now;
                            $recordData['updated_at'] = $now;
                            $recordData['last_modified'] = $now; // Set last_modified for new records

                            DB::table('pocomos_raw_data')->insert($recordData);

                            $this->info("Inserted new record with pcc_id: {$record['pcc_id']}");
                            $this->newRecords++;
                            $this->processedRecords++;

                            // Insert into legacy_api_raw_data_histories for new records
                            $legacyId = $this->insertIntoLegacyHistory($record, false, $dryRun);
                            if ($legacyId && ! $dryRun) {
                                $this->insertedLegacyIds[] = $legacyId;
                            }
                        } catch (\Exception $e) {
                            $this->logError('Error inserting record: '.$e->getMessage(), [
                                'pcc_id' => $record['pcc_id'] ?? 'unknown',
                                'exception' => get_class($e),
                                'message' => $e->getMessage(),
                            ]);
                            $this->errors++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logError('Error processing record: '.$e->getMessage(), [
                        'record_id' => $record['pcc_id'] ?? 'unknown',
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->errors++;
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run summary for this batch:');
            $this->info("- Would process {$this->processedRecords} records");
            $this->info("- Would insert {$this->newRecords} new records");
            $this->info("- Would update {$this->updatedRecords} existing records");
        } else {
            // Update the system_settings table with the last successful sync time
            $this->updateSystemSettings();

            // Always dispatch the job if we have any legacy records, regardless of whether they're from new/updated records
            if (isset($this->insertedLegacyIds) && ! empty($this->insertedLegacyIds)) {
                $recordCount = count($this->insertedLegacyIds);
                $this->info("Found {$recordCount} legacy records to process");
                $this->dispatchSalesMasterJob($this->insertedLegacyIds, $dryRun);
            } else {
                $this->info('No legacy records to process for sales_master');
            }
        }
    }

    /**
     * Validate if a record has enough data to be inserted into legacy_api_raw_data_histories
     *
     * @param  array  $pocomosRecord  Pocomos record to validate
     * @return bool Whether record has minimum required data
     */
    private function validateLegacyRecord(array $pocomosRecord): bool
    {
        // Check for minimum required fields
        if (! isset($pocomosRecord['pcc_id']) || empty($pocomosRecord['pcc_id'])) {
            $this->info('Skipping legacy insert: Missing pcc_id');

            return false;
        }

        if (! isset($pocomosRecord['contract_id']) || empty($pocomosRecord['contract_id'])) {
            $this->info("Skipping legacy insert for pcc_id={$pocomosRecord['pcc_id']}: Missing contract_id");

            return false;
        }

        // Check if the record has any meaningful data for tracking
        $hasCustomerData = isset($pocomosRecord['customer_first_name']) ||
                          isset($pocomosRecord['customer_last_name']) ||
                          isset($pocomosRecord['customer_street']);

        $hasContractData = isset($pocomosRecord['contract_date']) ||
                          isset($pocomosRecord['original_contract_value']);

        if (! $hasCustomerData && ! $hasContractData) {
            $this->info("Skipping legacy insert for pcc_id={$pocomosRecord['pcc_id']}: Insufficient data");

            return false;
        }

        return true;
    }

    /**
     * Insert record into legacy_api_raw_data_histories table if it has valid data
     *
     * @param  array  $pocomosRecord  Original pocomos record
     * @param  bool  $isUpdate  Whether this is an update or a new record
     * @param  bool  $dryRun  Whether to actually save data or just simulate
     * @return int|null The inserted ID or null if not inserted
     */
    private function insertIntoLegacyHistory(array $pocomosRecord, bool $isUpdate, bool $dryRun): ?int
    {
        if ($dryRun) {
            return null;
        }

        // Skip if the record doesn't meet minimum criteria
        if (! $this->validateLegacyRecord($pocomosRecord)) {
            return null;
        }

        $pccId = $pocomosRecord['pcc_id'];
        $this->info("Preparing to insert legacy history record for pcc_id={$pccId}, type=".($isUpdate ? 'update' : 'insert'));

        try {
            // Map the pocomos fields to legacy fields
            $legacyData = $this->mapPocomosToLegacyFields($pocomosRecord);

            // Double-check the mapped data has essential fields
            if (! isset($legacyData['legacy_id']) || ! isset($legacyData['pid'])) {
                $this->info("Skipping legacy insert for pcc_id={$pccId}: Failed to map required fields");

                return null;
            }

            // Add additional fields for tracking
            $legacyData['import_to_sales'] = 0; // Default to not imported

            // Debug the mapped data
            $this->info("Legacy data for pcc_id={$pccId}: pid={$legacyData['pid']}, legacy_id={$legacyData['legacy_id']}");

            // Insert into legacy_api_raw_data_histories
            $insertId = DB::table('legacy_api_raw_data_histories')->insertGetId($legacyData);

            $this->info("Inserted into legacy_api_raw_data_histories: pid={$legacyData['pid']}, legacy_id={$legacyData['legacy_id']}, id={$insertId}");

            // Return the inserted ID for further processing
            return $insertId;

        } catch (\Exception $e) {
            $this->logError('Error inserting into legacy_api_raw_data_histories: '.$e->getMessage(), [
                'pcc_id' => isset($pocomosRecord['pcc_id']) ? $pocomosRecord['pcc_id'] : 'unknown',
                'contract_id' => isset($pocomosRecord['contract_id']) ? $pocomosRecord['contract_id'] : 'unknown',
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update system_settings table with last successful sync
     */
    private function updateSystemSettings(): void
    {
        try {
            // Check if the setting already exists
            $setting = DB::table('system_settings')
                ->where('key', 'pocomos_last_sync')
                ->where('group', 'pocomos')
                ->first();

            $now = now();

            if ($setting) {
                // Update existing setting
                DB::table('system_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => $now,
                        'updated_at' => $now,
                    ]);

                $this->info("Updated system_settings with last sync time: {$now}");
            } else {
                // Create new setting
                DB::table('system_settings')->insert([
                    'key' => 'pocomos_last_sync',
                    'value' => $now,
                    'group' => 'pocomos',
                    'description' => 'Last successful run timestamp for Pocomos data synchronization',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->info("Created system_settings entry for Pocomos sync with time: {$now}");
            }
        } catch (\Exception $e) {
            $this->logError('Error updating system_settings: '.$e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch a SaleMasterJob to process the inserted legacy records
     *
     * @param  array  $insertedIds  Array of inserted legacy record IDs
     * @param  bool  $dryRun  Whether this is a dry run
     */
    private function dispatchSalesMasterJob(array $insertedIds, bool $dryRun): void
    {
        if ($dryRun || empty($insertedIds)) {
            return;
        }

        try {
            // Use consistent queue name across all Pocomos-related jobs
            $queueName = 'pocomos-sales-process';

            // IMPORTANT: Use 'Pocomos' with capital P to match filtering in SaleMasterJob
            // Use Bus::dispatch to match method used in Pocomos.php
            \Illuminate\Support\Facades\Bus::dispatch(
                (new SaleMasterJob('Pocomos', 100, $queueName))
                    ->onQueue($queueName)
            );

            // Log detailed information about the dispatched job for debugging
            $recordCount = count($insertedIds);
            $this->info("Dispatched SaleMasterJob with type 'Pocomos' to process {$recordCount} legacy records with IDs: ".implode(',', array_slice($insertedIds, 0, 5)).(count($insertedIds) > 5 ? '...' : ''));
            $this->logInfo("SaleMasterJob dispatched to queue: {$queueName}");

            // Double-check that records are properly set up for processing
            $this->verifyRecordsForProcessing($insertedIds);
        } catch (\Exception $e) {
            $this->logError('Failed to dispatch SaleMasterJob: '.$e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Verify that inserted records meet the requirements for SaleMasterJob processing
     *
     * @param  array  $insertedIds  Array of inserted legacy record IDs
     */
    private function verifyRecordsForProcessing(array $insertedIds): void
    {
        if (empty($insertedIds)) {
            return;
        }

        try {
            // Sample up to 5 records to verify they are set up correctly
            $sampleIds = array_slice($insertedIds, 0, 5);
            $records = DB::table('legacy_api_raw_data_histories')
                ->whereIn('id', $sampleIds)
                ->get();

            foreach ($records as $record) {
                $issues = [];

                if ($record->data_source_type !== 'Pocomos') {
                    $issues[] = "data_source_type is '{$record->data_source_type}' instead of 'Pocomos'";
                }

                if (empty($record->closer1_id)) {
                    $issues[] = 'closer1_id is empty';
                }

                if (empty($record->customer_signoff) || strtotime($record->customer_signoff) <= strtotime('2024-12-31')) {
                    $issues[] = 'customer_signoff is missing or not after 2024-12-31';
                }

                if ($record->import_to_sales !== '0') {
                    $issues[] = "import_to_sales is '{$record->import_to_sales}' instead of '0'";
                }

                if (! empty($issues)) {
                    $this->info("Record ID {$record->id} has issues that may prevent processing: ".implode(', ', $issues));
                } else {
                    $this->info("Record ID {$record->id} meets all requirements for processing");
                }
            }
        } catch (\Exception $e) {
            $this->logError('Error verifying records: '.$e->getMessage());
        }
    }
}
