<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPocomosDataBatchJob;
use App\Jobs\Sales\SaleMasterJob;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\Integration;
use App\Models\InterigationTransactionLog;
use App\Models\ProductCode;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionContext;

class Pocomos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pocomos:insert {startDate?} {endDate?} 
                          {--batch=1000 : Number of records to process in each batch}
                          {--parallel=4 : Number of parallel processes for data processing}
                          {--memory-limit=1024 : Memory limit in MB for the process}
                          {--timeout=3600 : Maximum execution time in seconds}
                          {--dry-run : Run without saving data}';

    /**
     * Timestamp when command started, for performance tracking
     *
     * @var float|null
     */
    protected $commandStartTime = null;

    /**
     * Log channel to use for Pocomos-specific logging
     *
     * @var string
     */
    protected $logChannel = 'pocomos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import sales from Pocomos API with batch processing and queue support';

    /**
     * Counters for tracking data processing results
     */
    protected $processedRecords = 0;      // Total number of records processed

    protected $newRecords = 0;            // Number of new records added

    protected $errors = 0;                // Number of errors encountered during processing

    protected $startTime;                 // Start time for performance measurement

    /**
     * Batch processing configuration
     */
    protected $batchSize = 100;           // Default batch size

    // protected $queueName = 'parlley';     // Default queue name
    protected $queueName = 'pocomos-sales-process';

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
     * Safely parse date from m/d/Y format to Y-m-d format
     *
     * @param  string|null  $dateString  Date string in m/d/Y format
     * @return string|null Formatted date in Y-m-d format or null if invalid
     */
    protected function parseDate(?string $dateString): ?string
    {
        if (empty($dateString) || ! is_string($dateString)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('m/d/Y', trim($dateString))->format('Y-m-d');
        } catch (\Exception $e) {
            $this->logWarning('Invalid date format encountered', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Log debug messages to pocomos channel
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->logToPocomos('debug', $message, $context);
    }

    /**
     * Log info messages to pocomos channel
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logToPocomos('info', $message, $context);
    }

    /**
     * Log warning messages to pocomos channel
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->logToPocomos('warning', $message, $context);
    }

    /**
     * Log error messages to pocomos channel
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logToPocomos('error', $message, $context);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
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
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Get command options
            $this->batchSize = (int) $this->option('batch');
            $memoryLimit = (int) $this->option('memory-limit');
            $timeout = (int) $this->option('timeout');
            $this->queueName = 'pocomos-sales-process'; // Use environment variable with default fallback
            $dryRun = $this->option('dry-run');

            $this->info('Using new account-status API endpoint');
            ini_set('memory_limit', $memoryLimit.'M');
            ini_set('max_execution_time', $timeout);
            $this->info("Memory limit set to {$memoryLimit}M");
            $this->info("Max execution time set to {$timeout} seconds");
            $this->info("Using queue: {$this->queueName}");

            if ($dryRun) {
                $this->warn('Running in dry-run mode - no data will be saved');
            }

            // Get all active Pocomos integrations instead of just the first one
            $integrations = Integration::where(['name' => 'Pocomos', 'status' => 1])->get();
            if ($integrations->isEmpty()) {
                $this->error('No active Pocomos integrations found');

                return Command::FAILURE;
            }

            // Check for and clear any stale locks that might prevent execution
            if (file_exists(storage_path('framework/schedule-'.sha1('pocomos:insert')))) {
                @unlink(storage_path('framework/schedule-'.sha1('pocomos:insert')));
                $this->info('Cleared stale scheduler lock');
            }

            // Register a shutdown function to handle fatal errors gracefully and complete Sentry check-in
            register_shutdown_function(function () {
                $error = error_get_last();
                if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                    // Log fatal error - log both to main log and to our dedicated channel
                    $errorMessage = 'Fatal error in Pocomos import command';
                    $errorContext = [
                        'error' => $error,
                        'message' => 'Command terminated abnormally',
                    ];

                    // Log to both channels to ensure it's captured
                    \Illuminate\Support\Facades\Log::error($errorMessage, $errorContext);
                    \Illuminate\Support\Facades\Log::channel('pocomos')->error($errorMessage, $errorContext);

                    // Ensure Sentry check-in is marked as errored for fatal errors
                    try {
                        if (class_exists('\Sentry\SentrySdk')) {
                            // Try to get the transaction from a global variable
                            global $POCOMOS_TRANSACTION;
                            global $POCOMOS_CHECKIN_ID;
                            if (isset($POCOMOS_TRANSACTION)) {
                                // Mark the transaction as errored
                                $POCOMOS_TRANSACTION->setStatus(SpanStatus::internalError());
                                $POCOMOS_TRANSACTION->finish();
                            } else {
                                // Try to find the current transaction
                                $transaction = \Sentry\SentrySdk::getCurrentHub()->getTransaction();
                                if ($transaction) {
                                    $transaction->setStatus(SpanStatus::internalError());
                                    $transaction->finish();
                                }
                            }

                            // Configure scope with error details
                            \Sentry\SentrySdk::getCurrentHub()->configureScope(function (\Sentry\State\Scope $scope) use ($error): void {
                                $scope->setContext('fatal_error', $error);
                            });

                            // Capture the fatal error message
                            \Sentry\captureException(new \ErrorException(
                                $error['message'],
                                0,
                                $error['type'],
                                $error['file'],
                                $error['line']
                            ));

                            // Force flush
                            if (method_exists(\Sentry\SentrySdk::getCurrentHub()->getClient(), 'flush')) {
                                \Sentry\SentrySdk::getCurrentHub()->getClient()->flush();
                            }
                        }
                    } catch (\Exception $e) {
                        // Nothing we can do at this point if Sentry fails
                    }
                }
            });

            // Set timeout for the command if specified
            $timeout = intval($this->option('timeout') ?? 1800); // Default to 30 minutes
            ini_set('max_execution_time', $timeout);
            $this->info("Max execution time set to {$timeout} seconds");

            // Record the start time for performance tracking
            $startTime = microtime(true);

            // Track statistics for reporting
            $total_records = 0;
            $new_pids = [];
            $updated_pids = [];
            $errors = 0;

            // Initialize Sentry check-in for monitoring
            $checkInId = null;
            try {
                if (class_exists('\Sentry\SentrySdk')) {
                    // Create a unique monitor slug
                    // Use the same monitor slug format that Laravel's scheduler generates
                    $monitorSlug = 'scheduled_artisan-pocomos-insert-batch1000-memory';

                    // Create the check-in with 'in_progress' status
                    $checkIn = new \Sentry\CheckIn(
                        $monitorSlug,
                        \Sentry\CheckInStatus::inProgress(),
                        null, // duration parameter should be null
                        null  // id parameter should be null for new check-ins
                    );

                    // Set monitor properties
                    $monitorConfig = [
                        'schedule' => [
                            'type' => 'crontab',
                            'value' => '0 * * * *', // Hourly schedule
                        ],
                        'checkin_margin' => 5 * 60, // 5 minute margin
                        'max_runtime' => $this->option('timeout'), // Maximum runtime in seconds
                        'timezone' => 'UTC',
                    ];

                    // Create a transaction context
                    $transactionContext = new TransactionContext(
                        'pocomos-import-monitor',
                        'monitor.check_in'
                    );
                    $transactionContext->setDescription('Pocomos import job');
                    $transactionContext->setData([
                        'monitor_config' => $monitorConfig,
                        'monitor_slug' => $monitorSlug,
                    ]);

                    // Generate a unique check-in ID
                    $checkInId = uniqid('pocomos_', true);
                    $transactionContext->setData([
                        'monitor_config' => $monitorConfig,
                        'monitor_slug' => $monitorSlug,
                        'check_in_id' => $checkInId,
                    ]);

                    // Start a transaction for monitoring
                    $transaction = \Sentry\startTransaction($transactionContext);

                    // Store the transaction in the current hub
                    \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);

                    // Store in global variable for shutdown function
                    global $POCOMOS_TRANSACTION;
                    $POCOMOS_TRANSACTION = $transaction;
                    global $POCOMOS_CHECKIN_ID;
                    $POCOMOS_CHECKIN_ID = $checkInId;

                    // Log the check-in ID for debugging
                    $this->logInfo("Sentry check-in initiated with ID: {$checkInId}");

                    // Add command details as tags
                    \Sentry\SentrySdk::getCurrentHub()->configureScope(function (\Sentry\State\Scope $scope): void {
                        $scope->setTag('command', 'pocomos:insert');
                        $scope->setContext('command_info', [
                            'timeout' => $this->option('timeout'),
                            'batch_size' => $this->option('batch'),
                            'memory_limit' => $this->option('memory-limit'),
                        ]);
                    });

                    // Add a breadcrumb as well
                    \Sentry\SentrySdk::getCurrentHub()->addBreadcrumb(
                        new \Sentry\Breadcrumb(
                            \Sentry\Breadcrumb::LEVEL_INFO,
                            \Sentry\Breadcrumb::TYPE_DEFAULT,
                            'pocomos.import',
                            'Starting Pocomos import process'
                        )
                    );
                }
            } catch (\Exception $e) {
                // Continue silently if Sentry isn't available
                $this->logWarning("Sentry check-in failed: {$e->getMessage()}");
            }

            if (Carbon::now()->isSaturday()) {
                $startDate = $this->argument('startDate') ?? Carbon::now()->subDays(30)->toDateString();
                $endDate = $this->argument('endDate') ?? Carbon::now()->toDateString();
            } elseif (Carbon::now()->hour == 0) {
                $startDate = $this->argument('startDate') ?? Carbon::now()->subDays(10)->toDateString();
                $endDate = $this->argument('endDate') ?? Carbon::now()->toDateString();
            } else {
                $startDate = $this->argument('startDate') ?? Carbon::now()->toDateString();
                $endDate = $this->argument('endDate') ?? Carbon::parse($startDate)->addDay()->toDateString();
            }
            // Log::info($startDate .",". $endDate);
            // dd($startDate , $endDate);

            $company_profile = CompanyProfile::first();
            if ($company_profile->company_type == 'Pest') {
                $total_records = 0;
                $new_pids = [];
                $updated_pids = [];
                $api_data = [];  // Initialize api_data array to prevent undefined variable error

                // Get all active Pocomos integrations instead of just the first one
                $integrations = Integration::where(['name' => 'Pocomos', 'status' => 1])->get();
                if ($integrations->isEmpty()) {
                    $this->error('No active Pocomos integrations found');

                    return Command::FAILURE;
                }

                $this->info('Found '.$integrations->count().' active Pocomos integrations');

                // Process each Pocomos integration
                foreach ($integrations as $integration) {
                    try {
                        // Initialize batch array for this integration
                        $dataBatch = [];

                        $this->info('Processing Pocomos integration ID: '.$integration->id.' '.$integration->description);

                        $value = openssl_decrypt($integration->value, config('app.encryption_cipher_algo'), config('app.encryption_key'), 0, config('app.encryption_iv'));
                        $decode_value = json_decode($value);
                        $base_url = isset($decode_value->base_url) ? $decode_value->base_url : null;
                        //  dd($decode_value);

                        if ($base_url == null) {
                            $this->error('Base URL not found for Branch ID: '.$integration->description);

                            continue; // Skip this integration but continue with others
                        }

                        // get token of pokomos
                        // Prepare API call and log details
                        $jwtUrl = $base_url.'/public/technician/jwt_token';

                        // For actual API call, use real credentials with URL encoding
                        $jwtPayload = 'username='.urlencode($decode_value->username).'&password='.urlencode($decode_value->password);

                        // For logging purposes, mask the password
                        $jwtLogPayload = json_encode(['username' => $decode_value->username, 'password' => '********']); // Mask password for security
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
                                'Cookie: PHPSESSID=1c2674f54251dc34017c561597c0f2fd',
                            ],
                        ]);

                        $t_response = curl_exec($curl);
                        curl_close($curl);

                        // Update log entry with response
                        $apiLog->update(['response' => $t_response]);
                        $this->info("Logged JWT token request for monitoring (Log ID: {$apiLog->id})");
                        // Parse the token response
                        $decode = json_decode($t_response);

                        // Check if the response has the expected format and contains a token
                        if (! isset($decode->response) || (isset($decode->meta->errors) && ! empty($decode->meta->errors))) {
                            $this->logError('Invalid JWT token response', [
                                'integration_id' => $integration->id,
                                'branch_id' => $decode_value->branch_id,
                                'response' => $t_response,
                            ]);
                            throw new \Exception('Invalid JWT token response: '.
                                (isset($decode->meta->errors[0]->message) ? $decode->meta->errors[0]->message : 'Token not found in response'));
                        }

                        // Extract the token from the response field
                        $token = $decode->response;

                        // Format dates for account-status endpoint (MM/DD/YY format)
                        $formattedStartDate = Carbon::parse($startDate)->format('m/d/y');
                        $formattedEndDate = Carbon::parse($endDate)->format('m/d/y');

                        // Use the new account-status endpoint
                        // $billingUrl = $base_url.'/jwt/report/custom/'.$decode_value->branch_id.'/billing-for-seq';
                        $billingUrl = $base_url.'/jwt/'.$decode_value->branch_id.'/report/account-status';

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

                        $this->logInfo('Using account-status endpoint', [
                            'url' => $billingUrl,
                            'date_range' => "$formattedStartDate to $formattedEndDate",
                            'branch_id' => $decode_value->branch_id,
                        ]);

                        $billingPayload = json_encode($postFields);

                        // Create log entry before API call
                        $billingApiLog = InterigationTransactionLog::create([
                            'interigation_name' => 'Pocomos',
                            'api_name' => 'Sales Data Fetch',
                            'url' => $billingUrl,
                            'payload' => $billingPayload,
                            'response' => null,
                        ]);

                        $curl_data = curl_init();

                        curl_setopt_array($curl_data, [
                            CURLOPT_URL => $billingUrl,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => $billingPayload,
                            CURLOPT_HTTPHEADER => [
                                'Content-Type: application/json',
                                'Cookie: PHPSESSID=1c2674f54251dc34017c561597c0f2fd',
                                'XauthToken: '.$token,
                                'Authorization: Bearer '.$token,
                            ],
                        ]);

                        $response = curl_exec($curl_data);
                        curl_close($curl_data);

                        // Update log with response and add statistics
                        // Safely decode JSON with validation
                        $responseObj = json_decode($response, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $this->logError('Invalid JSON response from Pocomos API', [
                                'error' => json_last_error_msg(),
                                'integration_id' => $integration->id,
                                'branch_id' => $decode_value->branch_id,
                                'response_sample' => substr($response, 0, 255).(strlen($response) > 255 ? '...' : ''),
                            ]);
                            $responseObj = [];
                        }
                        $recordCount = isset($responseObj['response']) && is_array($responseObj['response']) ? count($responseObj['response']) : 0;

                        $billingApiLog->update([
                            'response' => $response,
                        ]);

                        $this->info("Logged sales data API request for monitoring (Log ID: {$billingApiLog->id}, Records: {$recordCount})");

                        // Only decode the response once to improve efficiency
                        $decodedResponse = json_decode($response, true);
                        $api_data[] = $decodedResponse;
                        $res = (object) $decodedResponse;

                        $this->info("Processing data from Pocomos integration ID: {$integration->id} (Branch: {$decode_value->branch_id})");

                        if (isset($res->response) && is_array($res->response)) {
                            $newData = $res->response;
                            $dataCount = count($newData);

                            $this->logInfo("Processing {$dataCount} records from Pocomos using account-status endpoint", [
                                'integration_id' => $integration->id,
                                'branch_id' => $decode_value->branch_id,
                                'record_count' => $dataCount,
                            ]);

                            $progressBarTickets = $this->output->createProgressBar($dataCount);
                            $progressBarTickets->start();

                            // Using account-status endpoint for data processing

                            foreach ($newData as $val) {
                                // Map fields from account-status endpoint format
                                $mappedVal = [
                                    'pcc_id' => $val['pcc_id'] ?? null,
                                    'customer_name' => trim(($val['customer_first_name'] ?? '').' '.($val['customer_last_name'] ?? '')),
                                    'customer_email' => $val['customer_email'] ?? null,
                                    'customer_phone' => $val['customer_phone'] ?? null,
                                    'product' => $val['service_type'] ?? null,
                                    'initial_service_date' => $this->parseDate($val['initial_date'] ?? null),
                                    'service_completion_date' => $this->parseDate($val['last_service_date'] ?? null),
                                    'sales_rep_email' => $val['salesperson_email'] ?? null,
                                    'customer_signoff' => $this->parseDate($val['contract_date'] ?? null),
                                    'service_price' => $val['recurring_price'] ?? null,
                                    'customer_address' => $val['customer_contact_address'] ?? null,
                                    'service_type' => $val['service_type'] ?? null,
                                    'contract_name' => $val['contract_name'] ?? null,
                                    'customer_status' => $val['customer_status'] ?? null,
                                    'branch_name' => $val['branch_name'] ?? null,
                                    'marketing_type' => $val['marketing_type'] ?? null,
                                    'gross_account_value' => $val['original_contract_value'] ?? null,
                                    // Additional fields from the new endpoint
                                    'customer_external_account_id' => $val['customer_external_account_id'] ?? null,
                                    'recurring_price' => $val['recurring_price'] ?? null,
                                    'initial_price' => $val['initial_price'] ?? null,
                                    'length_of_agreement' => $val['agreement_length'] ?? null,
                                    'date_cancelled' => $this->parseDate($val['contract_cancelled_date'] ?? null),
                                    'customer_zip' => $val['customer_zip'] ?? null,
                                    'customer_city' => $val['customer_city'] ?? null,
                                    'customer_state' => $val['customer_state'] ?? null,
                                    'autopay' => $val['autopay'] ?? null,
                                    'sales_rep_name' => $val['salesperson_name'] ?? null,
                                    'card_on_file' => $val['card_on_file'] ?? null,
                                    'balance' => $val['balance'] ?? null,
                                    'sales_status' => $val['sales_status'] ?? null,
                                    'service_schedule' => $val['service_frequency'] ?? null,
                                    'initial_service_cost' => $val['initial_price'] ?? null,
                                    'subscription_payment' => $val['recurring_price'] ?? null,
                                ];

                                // Use mapped values for processing
                                $val = $mappedVal;

                                // Skip records with "Inspection Only" sales status
                                if (isset($val['sales_status']) && $val['sales_status'] === 'Inspection Only') {
                                    $this->logInfo("Skipping record with 'Inspection Only' sales status", [
                                        'pcc_id' => $val['pcc_id'] ?? 'unknown',
                                        'customer_name' => $val['customer_name'] ?? 'unknown',
                                        'sales_status' => $val['sales_status'],
                                    ]);
                                    $progressBarTickets->advance();

                                    continue;
                                }

                                // Validate record has all required fields
                                $requiredFields = ['pcc_id', 'customer_name'];
                                $missingFields = [];

                                foreach ($requiredFields as $field) {
                                    if (! isset($val[$field]) || empty($val[$field])) {
                                        $missingFields[] = $field;
                                    }
                                }

                                if (! empty($missingFields)) {
                                    $this->logWarning(
                                        'Skipping record with missing required fields: '.implode(', ', $missingFields),
                                        [
                                            'integration_id' => $integration->id,
                                            'partial_data' => array_intersect_key($val, array_flip(['pcc_id', 'customer_name', 'customer_email', 'product'])),
                                        ]
                                    );
                                    $progressBarTickets->advance();

                                    continue;
                                }

                                $productCode = isset($val['product']) && $val['product'] != '' ? strtolower(str_replace(' ', '', $val['product'])) : null;
                                $product = ProductCode::where('product_code', $productCode)->first();
                                if (! $product) {
                                    $product = ProductCode::where('product_code', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                                }

                                $product_id = $product->product_id;
                                $product_code = $product->product_code;
                                $triggerDate = [];
                                $m1Date = null;

                                // Get initial date - use the mapped initial_service_date field
                                $initialDate = null;
                                if (isset($val['initial_service_date']) && ! empty($val['initial_service_date'])) {
                                    $initialDate = $val['initial_service_date'];
                                }

                                // Get last service date - use the mapped service_completion_date field
                                $lastServiceDate = null;
                                if (isset($val['service_completion_date']) && ! empty($val['service_completion_date'])) {
                                    $lastServiceDate = $val['service_completion_date'];
                                }

                                // Initial service date
                                if (isset($val['sales_status']) && $val['sales_status'] == 'Serviced') {
                                    $triggerDate[0]['date'] = $initialDate;
                                    $m1Date = $initialDate;
                                } else {
                                    $triggerDate[0]['date'] = null;
                                    $m1Date = null;
                                }

                                // Check if initial date is blank but last service date is available
                                if (($m1Date === null || empty($m1Date)) && $lastServiceDate !== null && ! empty($lastServiceDate)) {
                                    $m1Date = $lastServiceDate;
                                    $triggerDate[0]['date'] = $lastServiceDate;
                                    // Log the fallback for debugging purposes
                                    $this->logInfo('Using service_completion_date as fallback for initial_service_date', [
                                        'pcc_id' => $val['pcc_id'] ?? 'unknown',
                                        'service_completion_date' => $lastServiceDate,
                                    ]);
                                } elseif ($m1Date === null || empty($m1Date)) {
                                    // If both initial date and last service date are blank, ensure nulls are set explicitly
                                    $m1Date = null;
                                    $triggerDate[0]['date'] = null;
                                    $this->logWarning('Both initial_service_date and service_completion_date are missing', [
                                        'pcc_id' => $val['pcc_id'] ?? 'unknown',
                                    ]);
                                }

                                // $triggerDate[]['date'] = isset($val['service_completion_date'])?$val['service_completion_date']:null;
                                $sales_rep_email = isset($val['sales_rep_email']) ? $val['sales_rep_email'] : null;

                                $user = User::where('email', $sales_rep_email)->first();
                                if (! $user) {
                                    $user = null;
                                    $matchingUser = UsersAdditionalEmail::with('user')->where('email', $sales_rep_email)->first();
                                    if ($matchingUser) {
                                        $user = $matchingUser->user;
                                    }
                                }

                                if (! $user) {
                                    $import_to_sales = 2;
                                } else {
                                    $import_to_sales = 0;
                                }

                                // Sales before March 15th 2025 - remove them. Put a logic to not re-import those sales again
                                if (! empty($user) && ! empty($val['customer_signoff']) && $val['customer_signoff'] > '2025-03-15') {
                                    $import_to_sales = 0;
                                } else {
                                    $import_to_sales = 2;
                                }

                                if ($val['balance'] == 0.00) {
                                    $bill_status = 'Paid';
                                } else {
                                    $bill_status = 'Pending';
                                }

                                // Determine job_status based on conditions
                                $job_status = 'Pending'; // Default status

                                // Check if pid exists in clawback_settlements
                                $clawback = ClawbackSettlement::where('pid', $val['pcc_id'])->first();

                                $domain = config('app.domain_name') ?: '';
                                if ($domain === 'homeguard') {
                                    if ($clawback) {
                                        $job_status = 'Clawback';
                                    } elseif (! empty($val['date_cancelled'])) {
                                        $job_status = 'Cancelled';
                                    } elseif (isset($val['sales_status'])) {
                                        // Check sales_status for Serviced cases
                                        if (in_array($val['sales_status'], ['Serviced', 'Past Due', 'Out of Frequency', 'Discount', 'OTS'])) {
                                            $job_status = 'Serviced';
                                        }
                                        // Check sales_status for Cancelled cases
                                        elseif (in_array($val['sales_status'], ['Cancel'])) {
                                            $job_status = 'Cancelled';
                                        }
                                        // Check sales_status for Pending cases
                                        elseif (in_array($val['sales_status'], ['Pending', 'Reschedule'])) {
                                            $job_status = 'Pending';
                                        }
                                    }

                                } else {
                                    if ($clawback) {
                                        $job_status = 'Clawback';
                                    } elseif (! empty($val['date_cancelled'])) {
                                        $job_status = 'Cancelled';
                                    } elseif ($m1Date !== null) {
                                        $job_status = 'Serviced';
                                    }
                                }

                                $dataCreate = [
                                    'pid' => isset($val['pcc_id']) ? $val['pcc_id'] : null,
                                    'legacy_id' => 'Branch-'.$decode_value->branch_id,
                                    'customer_name' => isset($val['customer_name']) ? $val['customer_name'] : null,
                                    'customer_phone' => isset($val['customer_phone']) ? $val['customer_phone'] : null,
                                    'customer_address' => isset($val['customer_address']) ? $val['customer_address'] : null,
                                    // 'customer_address_2' => isset($val['customer_address_2'])?$val['customer_address_2']:null,
                                    'customer_state' => isset($val['customer_state']) ? $val['customer_state'] : null,
                                    'location_code' => isset($val['customer_state']) ? $val['customer_state'] : null,
                                    'customer_city' => isset($val['customer_city']) ? $val['customer_city'] : null,
                                    'customer_zip' => isset($val['customer_zip']) ? $val['customer_zip'] : null,
                                    'product' => isset($val['product']) ? $val['product'] : null,
                                    'product_id' => isset($product_id) ? $product_id : null,
                                    'product_code' => isset($product_code) ? $product_code : null,
                                    'gross_account_value' => isset($val['gross_account_value']) ? $val['gross_account_value'] : null,
                                    'customer_signoff' => isset($val['customer_signoff']) ? $val['customer_signoff'] : null,
                                    'data_source_type' => 'Pocomos',
                                    'customer_email' => isset($val['customer_email']) ? $val['customer_email'] : null,
                                    'sales_rep_email' => isset($val['sales_rep_email']) ? $val['sales_rep_email'] : null,
                                    'sales_rep_name' => isset($val['sales_rep_name']) ? $val['sales_rep_name'] : null,
                                    'date_cancelled' => isset($val['date_cancelled']) && ! empty($val['date_cancelled']) ? $val['date_cancelled'] : null,
                                    'length_of_agreement' => isset($val['length_of_agreement']) ? $val['length_of_agreement'] : null,
                                    'service_schedule' => isset($val['service_schedule']) ? $val['service_schedule'] : null, // missing
                                    'initial_service_cost' => isset($val['initial_service_cost']) ? $val['initial_service_cost'] : null, // missing
                                    'subscription_payment' => isset($val['subscription_payment']) ? $val['subscription_payment'] : null, // missing
                                    'card_on_file' => (isset($val['card_on_file']) && $val['card_on_file'] == 1) ? 'Yes' : 'No',
                                    'auto_pay' => (isset($val['autopay']) && $val['autopay'] == 1) ? 'Yes' : 'No',
                                    'last_service_date' => isset($val['service_completion_date']) ? $val['service_completion_date'] : null,
                                    'bill_status' => $bill_status, // missing
                                    'job_status' => $job_status,
                                    'service_completed' => isset($val['service_completed']) ? $val['service_completed'] : null, // missing
                                    'install_complete_date' => null, // isset($val['service_completion_date'])?$val['service_completion_date']:null, // missing
                                    // 'm1_date' => isset($val['initial_service_date'])?$val['initial_service_date']:null,
                                    'm1_date' => $m1Date,
                                    'initial_service_date' => $m1Date,
                                    'trigger_date' => json_encode($triggerDate),
                                    // 'm2_date' => isset($val['service_completion_date'])?$val['service_completion_date']:null,
                                    'm2_date' => null,
                                    'import_to_sales' => $import_to_sales,
                                    'closer1_id' => isset($user->id) ? $user->id : null,
                                    'initialStatusText' => $job_status,
                                ];

                                // Add to batch array instead of creating individually
                                // This will be processed in batches later
                                $dataBatch[] = $dataCreate;
                                $new_pids[] = $val['pcc_id'] ?? null;
                                $total_records++;

                                // Advance the sale progress bar
                                $progressBarTickets->advance();
                            }
                            $progressBarTickets->finish();

                            // Process the collected data in batches
                            if (! empty($dataBatch)) {
                                // Split the data into batches based on batch size
                                $batches = array_chunk($dataBatch, $this->batchSize);
                                $this->info('Processing '.count($dataBatch).' records in '.count($batches).' batches');

                                // Process each batch
                                $batchProgressBar = $this->output->createProgressBar(count($batches));
                                $batchProgressBar->start();

                                foreach ($batches as $batchIndex => $batch) {
                                    try {
                                        if ($this->option('dry-run')) {
                                            $this->info("[DRY RUN] Would process batch {$batchIndex} with ".count($batch).' records');
                                        } else {
                                            // Process the batch using a queue job for better performance
                                            $job = new ProcessPocomosDataBatchJob(
                                                $batch,
                                                'Pocomos',
                                                $integration->id,
                                                $decode_value->branch_id ?? null
                                            );

                                            // Dispatch the job to the specified queue
                                            \Illuminate\Support\Facades\Bus::dispatch($job->onQueue($this->queueName));
                                            $this->info("Job dispatched to queue '{$this->queueName}' for batch {$batchIndex}");

                                            $this->logInfo(
                                                "Dispatched batch {$batchIndex} for processing",
                                                [
                                                    'integration_id' => $integration->id,
                                                    'batch_size' => count($batch),
                                                    'queue' => $this->queueName,
                                                ]
                                            );
                                        }
                                    } catch (\Exception $batchException) {
                                        $errors++;
                                        $this->logError(
                                            "Error dispatching batch {$batchIndex}: {$batchException->getMessage()}",
                                            [
                                                'integration_id' => $integration->id,
                                                'branch_id' => $decode_value->branch_id ?? null,
                                                'batch_size' => count($batch),
                                                'error' => $batchException->getMessage(),
                                            ]
                                        );
                                    }

                                    $batchProgressBar->advance();
                                }

                                $batchProgressBar->finish();
                                $this->newLine();
                            }

                            // SaleMasterJob dispatch moved to the end of the command for better performance and Sentry monitoring

                            $this->newLine();
                            $this->info("Processed Pocomos integration ID: {$integration->id} (Branch: {$decode_value->branch_id}) successfully.");
                        } else {
                            $this->newLine();
                            $this->info("No sales data found for Pocomos integration ID: {$integration->id} (Branch: {$decode_value->branch_id})");
                        }
                    } catch (\Exception $integrationException) {
                        // Log the exception for this integration but continue with others
                        $errors++;
                        $this->error("Error processing Pocomos integration ID: {$integration->id} - {$integrationException->getMessage()}");

                        // Log detailed error information to the pocomos channel
                        $this->logError(
                            "Integration processing failed: {$integrationException->getMessage()}",
                            [
                                'integration_id' => $integration->id,
                                'trace' => $integrationException->getTraceAsString(),
                            ]
                        );

                        // Add Sentry breadcrumb for this integration error
                        try {
                            if (class_exists('\Sentry\SentrySdk')) {
                                \Sentry\SentrySdk::getCurrentHub()->addBreadcrumb(
                                    new \Sentry\Breadcrumb(
                                        \Sentry\Breadcrumb::LEVEL_ERROR,
                                        \Sentry\Breadcrumb::TYPE_ERROR,
                                        'pocomos.import',
                                        "Error processing integration {$integration->id}",
                                        ['error' => $integrationException->getMessage()]
                                    )
                                );
                            }
                        } catch (\Exception $e) {
                            // Silently continue if Sentry reporting fails
                            $this->logWarning("Sentry error reporting failed: {$e->getMessage()}");
                        }
                    }
                } // End of foreach loop for integrations

                // Save combined data from all integrations
                $response = json_encode($api_data, JSON_FORCE_OBJECT);
                $file = 'Pocomos_data_all_'.date('Y-m-d_H_i_s').'.json';

                // Filter and count statistics across all integrations
                $new_pids = array_filter($new_pids, 'strlen');
                $updated_pids = array_filter($updated_pids, 'strlen');
                $count_new_pids = count($new_pids);
                $count_updated_pids = count($updated_pids);

                // Generate alerts for all processed sales
                // $this->call('generate:alert');
                $this->newLine();

                // Now dispatch SaleMasterJob after all batches have been processed
                try {
                    // Get an admin user to associate with the job, with fallback logic
                    $user = User::where('is_super_admin', 1)->first();
                    if (! $user) {
                        $user = User::first(); // Fallback to any user if no admin found
                    }

                    if (! $user) {
                        throw new \Exception('No valid user found to dispatch SaleMasterJob');
                    }

                    $this->logInfo("Dispatching SaleMasterJob with user ID: {$user->id}");
                    $dataForPusher = [];
                    // Queue the SaleMasterJob to the parlley queue
                    \Illuminate\Support\Facades\Bus::dispatch((new SaleMasterJob('Pocomos', 100, $this->queueName))->onQueue($this->queueName));
                    $this->info("SaleMasterJob dispatched to queue: {$this->queueName}");
                } catch (\Exception $e) {
                    $this->logWarning(
                        "Unable to dispatch SaleMasterJob: {$e->getMessage()}",
                        ['stage' => 'final_processing']
                    );
                    // Continue execution despite the queue error
                }

                // Calculate execution time
                $executionTime = round(microtime(true) - $startTime, 2);
                $this->info("Pocomos Sales Data Execute Successfully (Time: {$executionTime}s)");
                $this->info("Date Range: {$startDate} to {$endDate}");
                $this->info("Processed integrations: {$integrations->count()}");
                $this->info("Total records processed: {$total_records}");
                $this->info("New records: {$count_new_pids}, Updated records: {$count_updated_pids}, Errors: {$errors}");

                // Complete the Sentry check-in to indicate successful completion
                try {
                    if (class_exists('\Sentry\SentrySdk') && $checkInId) {
                        // Create a unique monitor slug (must match the one used to start the check-in)
                        // Use the same monitor slug format that Laravel's scheduler generates
                        $monitorSlug = 'scheduled_artisan-pocomos-insert-batch1000-memory';

                        // Create the check-in with 'ok' status
                        $checkIn = new \Sentry\CheckIn(
                            $monitorSlug,
                            \Sentry\CheckInStatus::ok(),
                            null, // duration parameter
                            $checkInId // Important: must use the same ID
                        );

                        // Complete the check-in transaction
                        $transaction = \Sentry\SentrySdk::getCurrentHub()->getTransaction();
                        if ($transaction) {
                            $transaction->setStatus(SpanStatus::ok());
                            $transaction->finish();
                            $this->logInfo('Sentry check-in completed successfully');
                        } else {
                            $this->logWarning('Could not find Sentry transaction for check-in completion');
                        }

                        // Log detailed metrics about the import process
                        \Sentry\SentrySdk::getCurrentHub()->addBreadcrumb(
                            new \Sentry\Breadcrumb(
                                \Sentry\Breadcrumb::LEVEL_INFO,
                                \Sentry\Breadcrumb::TYPE_DEFAULT,
                                'pocomos.import',
                                'Pocomos import process completed',
                                [
                                    'execution_time' => $executionTime,
                                    'total_records' => $total_records,
                                    'new_records' => count($new_pids),
                                    'updated_records' => count($updated_pids),
                                    'errors' => $errors,
                                ]
                            )
                        );

                        // Update Sentry scope with completion statistics
                        \Sentry\SentrySdk::getCurrentHub()->configureScope(function (\Sentry\State\Scope $scope) use ($executionTime, $total_records, $new_pids, $updated_pids, $errors): void {
                            $scope->setContext('completion_stats', [
                                'execution_time' => $executionTime,
                                'total_records' => $total_records,
                                'new_records' => count($new_pids),
                                'updated_records' => count($updated_pids),
                                'errors' => $errors,
                            ]);
                        });

                        // Force flush any pending events to ensure they're sent before command completes
                        if (method_exists(\Sentry\SentrySdk::getCurrentHub()->getClient(), 'flush')) {
                            \Sentry\SentrySdk::getCurrentHub()->getClient()->flush();
                        }
                    }
                } catch (\Exception $e) {
                    // Log but continue if Sentry reporting fails
                    $this->logWarning("Sentry completion reporting failed: {$e->getMessage()}", ['stage' => 'completion']);
                }

                return Command::SUCCESS;
            } else {
                $this->error('Company type is not Pest.');

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            // Display error message to console
            $this->error("Command failed: {$e->getMessage()}");

            // Log detailed error information to Laravel log channel
            Log::error(
                'Pocomos Import Command Error',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'time' => date('Y-m-d H:i:s'),
                ]
            );

            // Also log to dedicated pocomos channel
            $this->logError(
                "Command execution failed: {$e->getMessage()}",
                [
                    'trace' => $e->getTraceAsString(),
                    'time' => date('Y-m-d H:i:s'),
                ]
            );

            // Mark the Sentry check-in as errored
            try {
                if (class_exists('\Sentry\SentrySdk') && $checkInId) {
                    // Create a unique monitor slug (must match the one used to start the check-in)
                    // Use the same monitor slug format that Laravel's scheduler generates
                    $monitorSlug = 'scheduled_artisan-pocomos-insert-batch1000-memory';

                    // Create the check-in with 'error' status
                    $checkIn = new \Sentry\CheckIn(
                        $monitorSlug,
                        \Sentry\CheckInStatus::error(),
                        null, // duration parameter
                        $checkInId // Important: must use the same ID
                    );

                    // Mark the check-in transaction as errored
                    $transaction = \Sentry\SentrySdk::getCurrentHub()->getTransaction();
                    if ($transaction) {
                        $transaction->setStatus(SpanStatus::internalError());
                        $transaction->finish();
                        $this->logInfo('Sentry check-in marked as error');
                    } else {
                        $this->logWarning('Could not find Sentry transaction for check-in error marking');
                    }

                    // Add error breadcrumb
                    \Sentry\SentrySdk::getCurrentHub()->addBreadcrumb(
                        new \Sentry\Breadcrumb(
                            \Sentry\Breadcrumb::LEVEL_ERROR,
                            \Sentry\Breadcrumb::TYPE_ERROR,
                            'pocomos.import',
                            'Pocomos import process failed'
                        )
                    );

                    // Update Sentry scope with error details
                    \Sentry\SentrySdk::getCurrentHub()->configureScope(function (\Sentry\State\Scope $scope) use ($e): void {
                        $scope->setContext('error_details', [
                            'message' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ]);
                    });

                    // Capture the exception
                    \Sentry\captureException($e);

                    // Force flush any pending events to ensure they're sent before command completes
                    if (method_exists(\Sentry\SentrySdk::getCurrentHub()->getClient(), 'flush')) {
                        \Sentry\SentrySdk::getCurrentHub()->getClient()->flush();
                    }
                }
            } catch (\Exception $sentryException) {
                // Silently continue if Sentry reporting fails
                $this->logWarning(
                    "Sentry error reporting failed: {$sentryException->getMessage()}",
                    [
                        'stage' => 'final_error_reporting',
                        'time' => date('Y-m-d H:i:s'),
                    ]
                );
            }

            return Command::FAILURE;
        }
    }
}
