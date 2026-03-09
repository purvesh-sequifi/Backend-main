<?php

namespace App\Console\Commands;

use App\Jobs\Sales\SaleMasterJobAwsLambda;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FindLegacyDataByEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legacy:find-by-email {emails* : The email addresses to search for, separated by spaces}
                            {--save : Save the log records to the main legacy_api_raw_data_histories table}
                            {--queue=sales-process : Queue to dispatch the job to}
                            {--batch=100 : Batch size for processing records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search for user data in legacy tables by email addresses';

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
        $userEmails = $this->argument('emails');

        if (empty($userEmails)) {
            $this->error('No email addresses provided.');

            return 1;
        }

        $saveOption = $this->option('save');
        if ($saveOption) {
            $this->info('Proceeding with save option...');
        }

        $this->info('Searching for legacy data for '.count($userEmails).' email(s)...');

        // Get records from both tables and group them by PID
        $legacyLogsQuery = LegacyApiRawDataHistoryLog::whereIn('sales_rep_email', $userEmails)
            ->orderBy('created_at', 'desc');

        $legacyRawsQuery = LegacyApiRawDataHistory::whereIn('sales_rep_email', $userEmails)
            ->orderBy('created_at', 'desc');

        // Get all records first
        $legacyLogs = $legacyLogsQuery->get();
        $legacyRaws = $legacyRawsQuery->get();

        $logsCount = $legacyLogs->count();
        $rawsCount = $legacyRaws->count();
        $totalRecords = $logsCount + $rawsCount;

        if ($totalRecords == 0) {
            $this->warn('No legacy data found for the provided email(s).');
            Log::info('No legacy data found for emails: '.implode(', ', $userEmails));

            return 0;
        }

        // Group records by PID and get the latest for each PID
        $latestLogsByPid = $legacyLogs->groupBy('pid')->map(function ($group) {
            return $group->sortByDesc('created_at')->first();
        });

        $latestRawsByPid = $legacyRaws->groupBy('pid')->map(function ($group) {
            return $group->sortByDesc('created_at')->first();
        });

        $latestLogsCount = $latestLogsByPid->count();
        $latestRawsCount = $latestRawsByPid->count();
        $latestTotalCount = $latestLogsCount + $latestRawsCount;

        // Display summary
        $this->info("Found a total of {$totalRecords} legacy records:");
        $this->info("- {$logsCount} records in LegacyApiRawDataHistoryLog");
        $this->info("- {$rawsCount} records in LegacyApiRawDataHistory");
        $this->info("\nAfter grouping by PID and taking latest records:");
        $this->info("- {$latestLogsCount} unique PIDs in LegacyApiRawDataHistoryLog");
        $this->info("- {$latestRawsCount} unique PIDs in LegacyApiRawDataHistory");

        // Display Legacy Log records grouped by PID (latest only)
        if ($latestLogsCount > 0) {
            $this->info("\nLatest Legacy Log Records (grouped by PID):");
            $savedCount = 0;

            foreach ($latestLogsByPid as $pid => $log) {
                $this->info("\n-- PID: {$pid} (Email: {$log->sales_rep_email}) --");

                // Save log record to the main history table if requested
                if ($this->option('save')) {

                    // Create new record in the history table
                    $attributes = $log->getAttributes();
                    unset($attributes['id']);
                    unset($attributes['created_at']);
                    unset($attributes['updated_at']);

                    // Set import_to_sales to 0 for new records
                    $attributes['import_to_sales'] = 0;

                    // Find user ID from the users table based on email and update closer1_id
                    if (! empty($log->sales_rep_email)) {
                        $user = User::where('email', $log->sales_rep_email)->first();
                        if ($user) {
                            $attributes['closer1_id'] = $user->id;
                            $this->info("  - Updated closer1_id to {$user->id} for email: {$log->sales_rep_email}");
                        } else {
                            $this->error("  - No user found with email: {$log->sales_rep_email}. Stopping execution.");
                            Log::error("Failed to import legacy data - no user found with email: {$log->sales_rep_email}");

                            return 1; // Stop execution with error status code
                        }
                    } else {
                        $this->error('  - Email address is empty. Stopping execution.');
                        Log::error('Failed to import legacy data - empty email address');

                        return 1; // Stop execution with error status code
                    }

                    try {
                        LegacyApiRawDataHistory::create($attributes);
                        $this->info('  - Saved to legacy_api_raw_data_histories table with import_to_sales=0');
                        $savedCount++;
                    } catch (\Exception $e) {
                        $this->error("  - Failed to save: {$e->getMessage()}");
                    }

                }
            }

            Log::info("Found {$latestLogsCount} unique PIDs in legacy log records");

            if ($this->option('save') && $savedCount > 0) {
                $this->info("\nSaved {$savedCount} new records to legacy_api_raw_data_histories table.");
                Log::info("Saved {$savedCount} new legacy records to the history table");

                // Get job parameters from command options
                $workerQueue = $this->option('queue');
                $batchSize = (int) $this->option('batch');
                $dataSourceType = 'FirstCoastArriveWebhook'; // Use the actual data_source_type from the database
                $includeCloser1IdNull = false; // Set to false as we've already ensured closer1_id is populated

                // Dispatch SaleMasterJobAwsLambda to process the newly added records
                dispatch((new SaleMasterJobAwsLambda($dataSourceType, $batchSize, $workerQueue, $includeCloser1IdNull))->onQueue($workerQueue));

                $this->info("Dispatched SaleMasterJobAwsLambda to process the imported records on '{$workerQueue}' queue with batch size {$batchSize}");
                Log::info('Dispatched SaleMasterJobAwsLambda for processing', [
                    'data_source_type' => $dataSourceType,
                    'queue' => $workerQueue,
                    'batch_size' => $batchSize,
                ]);
            }
        }

        // Display Legacy Raw records grouped by PID (latest only)
        if ($latestRawsCount > 0) {
            $this->info("\nLatest Legacy Raw Records (grouped by PID):");
            foreach ($latestRawsByPid as $pid => $raw) {
                $this->info("\n-- PID: {$pid} (Email: {$raw->sales_rep_email}) --");
            }
            Log::info("Found {$latestRawsCount} unique PIDs in legacy raw records");
        }

        return 0;
    }
}
