<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class DiagnoseBigQuerySync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bigquery:diagnose 
                            {--table=users : The BigQuery table name to diagnose}
                            {--fix : Attempt to fix identified issues}
                            {--only-active : Only consider active users}
                            {--sample=10 : Number of records to sample for detailed analysis}
                            {--batch-size=25 : Number of users to process in each batch}
                            {--max-batches=0 : Maximum number of batches to process, 0 for all}
                            {--parallel : Use parallel batch processing for faster diagnosis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose and fix BigQuery synchronization issues';

    /**
     * The BigQuery service instance.
     *
     * @var \App\Services\BigQueryService
     */
    protected $bigQueryService;

    /**
     * Path to the diagnostic log file.
     *
     * @var string
     */
    protected $logPath;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->logPath = storage_path('logs/bigquery-diagnostic.log');
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = now();
        $this->info('Starting BigQuery synchronization diagnosis for table: '.$this->option('table'));

        // Initialize BigQuery service
        $this->bigQueryService = app(BigQueryService::class);

        // Get command options
        $tableName = $this->option('table');
        $shouldFix = $this->option('fix');
        $onlyActive = $this->option('only-active');
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $useParallel = $this->option('parallel');

        // Log command start
        $this->log('Command started', [
            'table' => $tableName,
            'fix' => $shouldFix,
            'only_active' => $onlyActive,
            'batch_size' => $batchSize,
            'max_batches' => $maxBatches,
            'parallel' => $useParallel,
        ]);

        // If parallel flag is set, handle in batches
        if ($useParallel) {
            return $this->handleParallelDiagnostic($tableName, $shouldFix, $onlyActive, $batchSize, $maxBatches);
        }

        // 1. Get diagnostic information from BigQuery
        $this->info('Fetching BigQuery diagnostic information...');
        $diagnosticInfo = $this->bigQueryService->getDiagnosticInfo('', $tableName);

        if (! isset($diagnosticInfo['status']) || $diagnosticInfo['status'] !== 'enabled') {
            $this->error('Error fetching BigQuery information: '.json_encode($diagnosticInfo));
            $this->log('Error fetching BigQuery information: '.json_encode($diagnosticInfo), 'error');

            return 1;
        }

        $bigQueryCount = $diagnosticInfo['record_count'] ?? 0;
        $bigQuerySchema = $this->getBigQueryTableSchema($diagnosticInfo);

        // 2. Compare with local database
        $userQuery = User::query()->select('id');
        if ($onlyActive) {
            try {
                $userQuery->where('is_active', 1);
            } catch (\Exception $e) {
                $this->warn('is_active column not found, ignoring --only-active flag');
            }
        }

        // Get total count for progress reporting
        $totalUsers = $userQuery->count();

        // Show summary information
        $this->info("\nSynchronization Status:");
        $this->info("- Local database records: {$totalUsers}");
        $this->info("- BigQuery records: {$bigQueryCount}");
        $this->info('- Difference: '.abs($totalUsers - $bigQueryCount).' records');

        $this->log('Sync status comparison', [
            'local_count' => $totalUsers,
            'bigquery_count' => $bigQueryCount,
            'difference' => abs($totalUsers - $bigQueryCount),
        ]);

        if ($totalUsers == $bigQueryCount) {
            $this->info("\n✅ The record counts match! Running sample check to verify consistency.");
        }

        // 3. Sample random records for detailed analysis
        $sampleSize = min($this->option('sample'), $totalUsers);
        $this->info("\nAnalyzing {$sampleSize} random records for detailed diagnostics...");

        $userSample = $userQuery->inRandomOrder()->limit($sampleSize)->get();
        $missingUsers = [];
        $existingUsers = [];

        foreach ($userSample as $user) {
            // Check if user exists in BigQuery
            $exists = $this->bigQueryService->checkRecordExists('', $tableName, 'id', $user->id, 'INTEGER');

            if ($exists) {
                $existingUsers[] = $user->id;
            } else {
                $missingUsers[] = $user->id;

                if ($shouldFix) {
                    $this->info("Fixing: Adding user {$user->id} to BigQuery");
                    $this->syncUserToBigQuery($user);
                }
            }
        }

        // 4. Show diagnostic results
        $this->info("\nDiagnostic Results:");
        $this->info('- Records analyzed: '.count($userSample));
        $this->info('- Records found in BigQuery: '.count($existingUsers));
        $this->info('- Records missing from BigQuery: '.count($missingUsers));

        if (count($missingUsers) > 0) {
            $this->warn("\nDetected Missing Users:");
            $missingUserDetails = User::whereIn('id', $missingUsers)->get(['id', 'name', 'email', 'created_at']);

            $this->table(
                ['ID', 'Name', 'Email', 'Created At'],
                $missingUserDetails->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'created_at' => $user->created_at,
                    ];
                })
            );

            if (! $shouldFix) {
                $this->info("\nTo fix these issues, run the command with the --fix flag.");
            }
        } else {
            $this->info("\n✅ All sampled records exist in BigQuery!");
        }

        // 5. Log results and show execution time
        $duration = now()->diffInSeconds($startTime);
        $this->info("\nDiagnosis completed in {$duration} seconds.");

        $this->log('Command completed', [
            'duration_seconds' => $duration,
            'missing_count' => count($missingUsers),
            'sample_size' => count($userSample),
        ]);

        return 0;
    }

    /**
     * Log message to file
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] [{$level}] {$message} ".json_encode($context).PHP_EOL;

        // Append to log file
        File::append($this->logPath, $formattedMessage);
    }

    /**
     * Handle parallel diagnostic processing using batch jobs
     */
    protected function handleParallelDiagnostic(string $tableName, bool $shouldFix, bool $onlyActive, int $batchSize, int $maxBatches): int
    {
        $startTime = now();

        // 1. Get diagnostic information from BigQuery
        $this->info('Fetching BigQuery diagnostic information...');
        $diagnosticInfo = $this->bigQueryService->getDiagnosticInfo('', $tableName);

        if (! isset($diagnosticInfo['status']) || $diagnosticInfo['status'] !== 'enabled') {
            $this->error('Error fetching BigQuery information: '.json_encode($diagnosticInfo));
            $this->log('Error fetching BigQuery information: '.json_encode($diagnosticInfo), 'error');

            return 1;
        }

        $bigQueryCount = $diagnosticInfo['record_count'] ?? 0;

        // 2. Compare with local database
        $userQuery = User::query()->select('id');
        if ($onlyActive) {
            try {
                $userQuery->where('is_active', 1);
            } catch (\Exception $e) {
                $this->warn('is_active column not found, ignoring --only-active flag');
            }
        }

        // Get total count for progress reporting
        $totalUsers = $userQuery->count();

        // Show summary information
        $this->info("\nSynchronization Status:");
        $this->info("- Local database records: {$totalUsers}");
        $this->info("- BigQuery records: {$bigQueryCount}");
        $this->info('- Difference: '.abs($totalUsers - $bigQueryCount).' records');

        $this->log('Sync status comparison', [
            'local_count' => $totalUsers,
            'bigquery_count' => $bigQueryCount,
            'difference' => abs($totalUsers - $bigQueryCount),
        ]);

        // Check if we have dedicated workers for the diagnostics queue
        $workersCount = 0;
        exec('ps aux | grep "[q]ueue:work --queue=bigquery-diagnostics" | wc -l', $workersOutput);
        if (! empty($workersOutput)) {
            $workersCount = (int) trim($workersOutput[0]);
        }

        $this->info("\nStarting diagnostic processing in batches of {$batchSize} users");

        if ($workersCount === 0) {
            $this->warn("No dedicated queue workers found for 'bigquery-diagnostics' queue.");
            $this->info('For optimal performance, run the following command in separate terminals:');
            $this->line('  php artisan queue:work --queue=bigquery-diagnostics --tries=3');
            $this->info('Or run ./start-parallel-workers.sh to start multiple workers automatically.');
        } else {
            $this->info("{$workersCount} dedicated worker(s) found for processing diagnostic jobs.");
        }

        $batchCount = 0;
        $processedUsers = 0;

        // Clear any existing batch results from cache
        Cache::forget('bigquery_diagnostic_batches');
        Cache::forget('bigquery_diagnostic_results');

        // Store some metadata about the batches
        $batchMeta = [
            'total_users' => $totalUsers,
            'batch_size' => $batchSize,
            'started_at' => $startTime->toDateTimeString(),
            'should_fix' => $shouldFix,
            'table_name' => $tableName,
        ];
        Cache::put('bigquery_diagnostic_meta', $batchMeta, now()->addHours(24));

        // Calculate optimal delay between batches based on worker count
        $optimalDelay = max(1, min(5, ceil(10 / max(1, $workersCount)))); // Between 1-5 seconds

        // Process in batches
        $userQuery->chunkById($batchSize, function ($users) use (&$batchCount, &$processedUsers, $maxBatches, $batchSize, $totalUsers, $tableName, $shouldFix, $optimalDelay, $workersCount) {
            $userIds = $users->pluck('id')->toArray();
            $batchCount++;
            $processedUsers += count($userIds);

            // Check if we've hit the max batches limit
            if ($maxBatches > 0 && $batchCount > $maxBatches) {
                return false;
            }

            // Log progress
            $progressPercent = round(($processedUsers / $totalUsers) * 100, 1);
            $this->info("Dispatching diagnostic batch {$batchCount} with ".count($userIds)." users. Progress: {$processedUsers}/{$totalUsers} ({$progressPercent}%)");

            try {
                // Dispatch the job with a small delay to avoid overwhelming the queue
                // Use different delays for each batch to improve parallel processing
                \App\Jobs\ProcessBigQueryDiagnosticBatchJob::dispatch(
                    $userIds,
                    $tableName,
                    $batchCount,
                    ceil($totalUsers / $batchSize),
                    $shouldFix
                )->delay(now()->addSeconds($batchCount % max(1, $workersCount) * $optimalDelay));
            } catch (\Exception $e) {
                $this->error("Failed to dispatch batch {$batchCount}: ".$e->getMessage());
            }

            // Break if we've processed all users
            if ($processedUsers >= $totalUsers) {
                return false;
            }
        });

        $elapsedSeconds = now()->diffInSeconds($startTime);

        $this->info("\nFinished dispatching {$batchCount} diagnostic batch jobs for {$processedUsers} users in {$elapsedSeconds} seconds");
        $this->line('--------------------------------------------------------------------');
        $this->info("Jobs are now processing in the parallel queue 'bigquery-diagnostics'.");

        if ($workersCount === 0) {
            $this->warn("No workers detected for 'bigquery-diagnostics' queue. Run workers with:");
            $this->line('  ./start-parallel-workers.sh');
            $this->line('  or');
            $this->line('  php artisan queue:work --queue=bigquery-diagnostics');
        }

        $this->line('--------------------------------------------------------------------');
        $this->info('To fix synchronization issues, run the command with the --fix flag.');
        $this->info('To check the final results after jobs complete, run: php artisan bigquery:diagnose-results');

        return 0;
    }

    /**
     * Get the BigQuery table schema fields
     */
    protected function getBigQueryTableSchema(array $diagnosticInfo): array
    {
        if (! isset($diagnosticInfo['schema']) || ! is_array($diagnosticInfo['schema'])) {
            return [];
        }

        // Extract field names and types
        return array_map(function ($field) {
            return [
                'name' => $field['name'] ?? '',
                'type' => $field['type'] ?? '',
            ];
        }, $diagnosticInfo['schema']);
    }

    /**
     * Find potential data issues in a user record that might prevent BigQuery syncing
     */
    protected function findPotentialDataIssues(User $user, array $tableFields): array
    {
        $issues = [];

        // Convert user to array
        $userData = $user->toArray();

        // Check each field in the schema
        foreach ($tableFields as $field) {
            $fieldName = $field['name'];
            $fieldType = $field['type'];

            // Skip if field doesn't exist in user data
            if (! array_key_exists($fieldName, $userData)) {
                continue;
            }

            $value = $userData[$fieldName];

            // Check for type mismatches
            if ($fieldType === 'TIMESTAMP' && $value && ! $this->isValidDateTime($value)) {
                $issues[] = "Field '{$fieldName}' has invalid datetime value: {$value}";
            }

            if ($fieldType === 'INTEGER' && $value && ! is_numeric($value)) {
                $issues[] = "Field '{$fieldName}' has non-numeric value: {$value}";
            }

            // Check for extremely long string values
            if ($fieldType === 'STRING' && $value && is_string($value) && strlen($value) > 1000) {
                $issues[] = "Field '{$fieldName}' has extremely long value (".strlen($value).' chars)';
            }
        }

        return $issues;
    }

    /**
     * Check if a value is a valid datetime
     *
     * @param  mixed  $value
     */
    protected function isValidDateTime($value): bool
    {
        if (! $value) {
            return true;
        }

        try {
            if ($value instanceof \DateTime) {
                return true;
            }

            new \DateTime($value);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Sync a user to BigQuery
     */
    protected function syncUserToBigQuery(User $user): bool
    {
        try {
            // Load the full user model if we only have an ID
            if (is_numeric($user) || (is_object($user) && ! method_exists($user, 'toArray'))) {
                $user = User::find($user);
            }

            if (! $user) {
                $this->log('Cannot sync user - not found', [], 'error');

                return false;
            }

            // Convert to array
            $userData = $user->toArray();

            // Remove sensitive fields
            foreach (['password', 'remember_token', 'api_token'] as $field) {
                if (array_key_exists($field, $userData)) {
                    unset($userData[$field]);
                }
            }

            // Send to BigQuery
            $result = $this->bigQueryService->insertOrUpdate('users', $userData);

            $this->log('User sync result', [
                'user_id' => $user->id,
                'success' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log('User sync error', [
                'user_id' => is_object($user) ? $user->id : $user,
                'error' => $e->getMessage(),
            ], 'error');

            return false;
        }
    }
}
