<?php

namespace App\Console\Commands;

use App\Core\Traits\EvereeTrait;
use App\Models\evereeTransectionLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchEvereePaymentStatusesCommand extends Command
{
    use EvereeTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'everee:fetch-payment-statuses 
                            {--start-date= : Start date for payables (YYYY-MM-DD)}
                            {--end-date= : End date for payables (YYYY-MM-DD)}
                            {--days=7 : Number of days to look back if no dates provided}
                            {--update : Update database with fetched statuses}
                            {--test-mode : Simulate update without dispatching jobs (for testing)}
                            {--log-to-db : Log API responses to the database}
                            {--debug : Show detailed debug information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch payment statuses from Everee and optionally update the database';

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
        $this->info('Starting to fetch Everee payment statuses...');

        // Get options
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $daysToLookBack = $this->option('days');
        $debug = $this->option('debug');
        $testMode = $this->option('test-mode');

        // If no dates provided, calculate based on days to look back
        if (empty($startDate) || empty($endDate)) {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$daysToLookBack} days"));
        }

        $this->info("Fetching payables from $startDate to $endDate");

        // Fetch both 1099 and W2 payables
        $payables1099 = $this->fetchPayables('1099', $startDate, $endDate);
        $payablesW2 = $this->fetchPayables('W2', $startDate, $endDate);

        $allPayables = array_merge($payables1099, $payablesW2);
        $this->info('Total payables found: '.count($allPayables));

        if (empty($allPayables)) {
            $this->warn('No payables found for the specified date range.');

            return 0;
        }

        // Display payable information
        $this->displayPayablesSummary($allPayables);

        // Update database if requested
        if ($this->option('update')) {
            if ($testMode) {
                $this->info('Running in TEST MODE - no jobs will actually be dispatched');
                $updated = $this->updateDatabase($allPayables, true);
                $this->info("TEST MODE: Would have processed $updated records");
            } else {
                $updated = $this->updateDatabase($allPayables, false);
                $this->info("Updated $updated records in the database");
            }
        } else {
            $this->info('Database update skipped. Run with --update flag to update the database.');
        }

        $this->info('Command completed successfully!');

        return 0;
    }

    /**
     * Fetch payables from Everee API based on date range and worker type
     */
    private function fetchPayables(string $workerType, string $startDate, string $endDate): array
    {
        $token = $this->gettoken($workerType);
        $this->api_token = $token->password;
        $this->company_id = $token->username;

        if (empty($this->api_token) || empty($this->company_id)) {
            $this->warn("Missing API credentials for $workerType workers.");

            return [];
        }

        // Show timeframe being queried in a user-friendly format
        $this->line("<fg=green>Querying Everee for $workerType payments from</> <fg=yellow>".date('M j, Y', strtotime($startDate)).'</> <fg=green>to</> <fg=yellow>'.date('M j, Y', strtotime($endDate)).'</>');

        $params = [
            'size' => 500,
            'sort' => 'createdAt,desc',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $url = 'https://api-prod.everee.com/api/v2/payables?'.http_build_query($params);
        $method = 'GET';
        $headers = [
            'Authorization: Basic '.base64_encode($this->api_token),
            'accept: application/json',
            'content-type: application/json',
            'x-everee-tenant-id: '.$this->company_id,
        ];

        $this->info("Fetching $workerType payables...");
        $response = curlRequest($url, '', $headers, $method);

        // Only log to database if specifically requested to reduce DB load
        if ($this->option('log-to-db')) {
            try {
                evereeTransectionLog::create([
                    'api_name' => "fetch_{$workerType}_payables_command",
                    'api_url' => $url,
                    'payload' => '',  // Empty string since we're not sending a payload
                    'response' => $response,
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to log to database: {$e->getMessage()}");
                // Continue execution anyway
            }
        }

        $responseData = json_decode($response, true);
        $payables = [];

        // Debug API response if debug flag is set
        if ($this->option('debug')) {
            $this->info("API Response Structure for $workerType workers:");
            $this->line(json_encode(array_keys($responseData), JSON_PRETTY_PRINT));

            // Try to determine the response structure
            if (isset($responseData['items'])) {
                $this->info("Response structure uses 'items' array.");
                if (count($responseData['items']) > 0) {
                    $this->line('Sample payable structure:');
                    $this->line(json_encode($responseData['items'][0], JSON_PRETTY_PRINT));
                }
            } elseif (isset($responseData['_embedded']) && isset($responseData['_embedded']['payables'])) {
                $this->info("Response structure uses '_embedded.payables' array.");
                if (count($responseData['_embedded']['payables']) > 0) {
                    $this->line('Sample payable structure:');
                    $this->line(json_encode($responseData['_embedded']['payables'][0], JSON_PRETTY_PRINT));
                }
            } else {
                $this->warn('Could not determine response structure.');
                $this->line('Full response:');
                $this->line(json_encode($responseData, JSON_PRETTY_PRINT));
            }
        }

        // Handle different response structures
        if (isset($responseData['items'])) {
            foreach ($responseData['items'] as $item) {
                $item['workerType'] = $workerType;
                $payables[] = $item;
            }
        } elseif (isset($responseData['_embedded']) && isset($responseData['_embedded']['payables'])) {
            $payables = $responseData['_embedded']['payables'];
            foreach ($payables as &$payable) {
                $payable['workerType'] = $workerType;
            }
        } else {
            $this->error("Error: Unexpected response structure for $workerType payables");

            return [];
        }

        $this->info('Found '.count($payables)." $workerType payables");

        return $payables;
    }

    /**
     * Display a summary of the payables
     */
    private function displayPayablesSummary(array $payables): void
    {
        $headers = ['ID', 'External Worker ID', 'Amount', 'Status', 'Update Time', 'Worker Type'];
        $rows = [];

        foreach ($payables as $payable) {
            // Format the earningTimestamp if present
            $earningTime = 'N/A';
            if (isset($payable['earningTimestamp'])) {
                $timestamp = $payable['earningTimestamp'];
                $earningTime = date('Y-m-d H:i:s', $timestamp);
            }

            // Format amount as currency - use earningAmount field from API response
            $amount = 'N/A';

            // For debugging if debug option is enabled
            if ($this->option('debug')) {
                $this->line("Keys for payable ID {$payable['id']}: ".implode(', ', array_keys($payable)));
            }

            // Based on API documentation, earningAmount is a nested object with amount and currency
            if (isset($payable['earningAmount']) && isset($payable['earningAmount']['amount'])) {
                $amountValue = $payable['earningAmount']['amount'];
                if (is_numeric($amountValue)) {
                    $amount = '$'.number_format((float) $amountValue, 2);
                } else {
                    $amount = '$'.$amountValue; // If it's already formatted
                }
            }
            // Fallback if the structure is different than expected
            elseif (isset($payable['earningAmount']) && is_numeric($payable['earningAmount'])) {
                $amount = '$'.number_format($payable['earningAmount'], 2);
            }
            // Additional fallbacks for other possible field structures
            elseif (isset($payable['amount']) && is_numeric($payable['amount'])) {
                $amount = '$'.number_format($payable['amount'], 2);
            } elseif (isset($payable['payment']) && isset($payable['payment']['amount']) && is_numeric($payable['payment']['amount'])) {
                $amount = '$'.number_format($payable['payment']['amount'], 2);
            } elseif (isset($payable['paymentAmount']) && is_numeric($payable['paymentAmount'])) {
                $amount = '$'.number_format($payable['paymentAmount'], 2);
            } elseif (isset($payable['gross']) && is_numeric($payable['gross'])) {
                $amount = '$'.number_format($payable['gross'], 2);
            }

            $rows[] = [
                $payable['id'],
                $payable['externalWorkerId'] ?? 'N/A',
                $amount,
                $payable['paymentStatus'] ?? 'N/A',
                $earningTime,
                $payable['workerType'] ?? 'N/A',
            ];
        }

        $this->table($headers, $rows);

        // Display status summary
        $statusCounts = [];
        foreach ($payables as $payable) {
            $status = $payable['paymentStatus'] ?? 'UNKNOWN';
            if (! isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;
        }

        $this->info("\nPayment Status Summary:");
        foreach ($statusCounts as $status => $count) {
            $this->line("- $status: $count");
        }
    }

    /**
     * Update the database with the latest payment statuses
     */
    /**
     * Update database with payable information using the same flow as webhooks
     *
     * @param  array  $payables  Array of payable data from API
     * @param  bool  $testMode  If true, will only log actions without dispatching jobs
     * @return int Number of records processed
     */
    private function updateDatabase(array $payables, bool $testMode = false): int
    {
        $updatedCount = 0;
        $logFile = 'everee_payment_updates.log';

        // Start transaction log for test mode
        if ($testMode) {
            $logContent = "\n".str_repeat('=', 80)."\n";
            $logContent .= 'TEST MODE LOG - '.date('Y-m-d H:i:s')."\n";
            $logContent .= "The following actions would be performed if run in real mode:\n";
            $logContent .= str_repeat('-', 80)."\n";
            Log::channel('daily')->info($logContent);
        }

        // Prepare for progress tracking
        $this->output->progressStart(count($payables));

        foreach ($payables as $payable) {
            $payableId = $payable['id'] ?? null;
            $paymentId = $payable['paymentId'] ?? null;
            $status = $payable['paymentStatus'] ?? null;
            $externalWorkerId = $payable['externalWorkerId'] ?? null;

            if (! $payableId || ! $status || ! $externalWorkerId) {
                $this->output->progressAdvance();

                continue;
            }

            // In test mode, we assume all workers exist and no payments are processed
            $userExist = true;
            $exists = false;

            if (! $testMode) {
                // Check if user exists - Only in real mode
                $userExist = \App\Models\User::where('employee_id', $externalWorkerId)->exists();
                if (! $userExist) {
                    $message = "Worker ID $externalWorkerId not found in the system. Skipping.";
                    $this->line("<fg=yellow>$message</>");
                    $this->output->progressAdvance();

                    continue;
                }

                // Check if this payment has already been processed (same logic as webhook) - Only in real mode
                $exists = \App\Models\OneTimePayments::where('everee_paymentId', $paymentId)
                    ->where('everee_payment_status', 1)
                    ->union(
                        \App\Models\PayrollHistory::where('everee_paymentId', $paymentId)
                            ->where('everee_payment_status', 3)
                    )
                    ->exists();
            } else {
                // In test mode, just log the actions we would take
                Log::channel('daily')->info("WOULD CHECK: Verify if worker ID $externalWorkerId exists");
                Log::channel('daily')->info("WOULD CHECK: Verify if payment ID $paymentId is already processed");
            }

            if (! $exists) {
                // Format payable data to match webhook data structure
                $payableData = [
                    'paymentId' => $paymentId,
                    'paymentStatus' => $status,
                    'externalWorkerId' => $externalWorkerId,
                    'id' => $payableId,
                ];

                // If earningAmount exists, include it
                if (isset($payable['earningAmount']) && isset($payable['earningAmount']['amount'])) {
                    $payableData['earningAmount'] = $payable['earningAmount'];
                }

                // If earningTimestamp exists, include it
                if (isset($payable['earningTimestamp'])) {
                    $payableData['earningTimestamp'] = $payable['earningTimestamp'];
                }

                // If payableIds exists, include it
                if (isset($payable['payableIds'])) {
                    $payableData['payableIds'] = $payable['payableIds'];
                }

                if ($status === 'PAID') {
                    $message = "Processing PAID status for payment ID $paymentId (Worker: $externalWorkerId)";

                    if ($testMode) {
                        Log::channel('daily')->info("WOULD DISPATCH: $message");
                        Log::channel('daily')->info(json_encode($payableData, JSON_PRETTY_PRINT));
                        $this->line("<fg=green>TEST MODE: Would dispatch PAID event for payment ID $paymentId</>");
                    } else {
                        // Use the same event dispatch logic as the webhook
                        \App\Jobs\EvereewebhookJob::dispatch($payableData, true);
                        $this->line("<fg=green>Dispatched PAID event for payment ID $paymentId</>");
                    }
                    $updatedCount++;
                } elseif ($status === 'ERROR') {
                    $message = "Processing ERROR status for payment ID $paymentId (Worker: $externalWorkerId)";

                    if ($testMode) {
                        Log::channel('daily')->info("WOULD DISPATCH: $message");
                        Log::channel('daily')->info(json_encode($payableData, JSON_PRETTY_PRINT));
                        $this->line("<fg=red>TEST MODE: Would dispatch ERROR event for payment ID $paymentId</>");
                    } else {
                        // Use the same event dispatch logic as the webhook for error status
                        \App\Jobs\EvereewebhookJob::dispatch($payableData, false);
                        $this->line("<fg=red>Dispatched ERROR event for payment ID $paymentId</>");
                    }
                    $updatedCount++;
                } else {
                    $this->line("<fg=yellow>Status '$status' not handled for payment ID $paymentId</>");
                    if ($testMode) {
                        Log::channel('daily')->info("NOT HANDLED: Status '$status' for payment ID $paymentId");
                    }
                }
            } else {
                $message = "Payment ID $paymentId already processed. Skipping.";
                $this->line("<fg=blue>$message</>");
                if ($testMode) {
                    Log::channel('daily')->info("ALREADY PROCESSED: $message");
                }
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        // Close transaction log for test mode
        if ($testMode) {
            $logContent = "\n".str_repeat('-', 80)."\n";
            $logContent .= "TEST MODE COMPLETE - Would have processed $updatedCount records\n";
            $logContent .= str_repeat('=', 80)."\n";
            Log::channel('daily')->info($logContent);

            $this->info('Test mode details have been logged to the Laravel log file.');
            $this->info('Check storage/logs/laravel-'.date('Y-m-d').'.log for details.');
        }

        return $updatedCount;
    }
}
