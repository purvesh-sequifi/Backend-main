<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessUserBigQueryBatchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // Number of retry attempts

    public $timeout = 120; // Timeout in seconds

    protected $userIds;

    protected $batchNumber;

    protected $totalBatches;

    protected $attemptedUserIds = [];

    protected $successfulUserIds = [];

    protected $failedUserIds = [];

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'user_bigquery_batch_'.$this->batchNumber;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): int
    {
        return 600; // 10 minutes - enough time for the job to complete or fail
    }

    /**
     * Create a new job instance.
     *
     * @param  array  $userIds  Array of user IDs to process in this batch
     * @return void
     */
    public function __construct(array $userIds, int $batchId, int $totalBatches)
    {
        $this->userIds = $userIds;
        $this->batchId = $batchId;
        $this->totalBatches = $totalBatches;

        // Use dedicated queue for parallel processing
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $bigQueryService = new BigQueryService;
        if (! $bigQueryService->isEnabled()) {
            $this->logBigQuery('BigQuery integration is disabled. Skipping user batch sync job.');

            return;
        }

        $this->logBigQuery('Starting batch processing', [
            'batch_size' => count($this->userIds),
            'first_few_ids' => array_slice($this->userIds, 0, 5),
        ]);

        $startTime = now();
        $successCount = 0;
        $failureCount = 0;

        // Process each user in the batch
        foreach ($this->userIds as $userId) {
            try {
                $user = User::find($userId);
                if (! $user) {
                    $this->logBigQuery('User not found', ['user_id' => $userId]);
                    $this->failedUserIds[] = $userId;
                    $failureCount++;

                    continue;
                }

                $this->attemptedUserIds[] = $userId;

                // Process each user directly here since AddUpdateUserOnBigQueryJob's methods are private
                // Use Redis lock to prevent race conditions when processing the same user
                $lockKey = "bigquery_user_sync:{$userId}";
                $lockAcquired = false;

                try {
                    // Attempt to acquire a lock with 10-second expiry
                    // This ensures that even if the process dies, the lock will eventually release
                    $lockAcquired = Redis::set($lockKey, 1, 'EX', 10, 'NX');

                    if (! $lockAcquired) {
                        $this->logBigQuery('Skipping user - another process is handling this user', [
                            'user_id' => $userId,
                        ], 'info');

                        continue; // Skip to the next user
                    }

                    // Prepare user data for BigQuery
                    $userData = $this->prepareUserData($user);

                    // Use a more reliable approach for handling user data in BigQuery
                    $datasetId = config('bigquery.default_dataset');

                    // Important: BigQuery schema uses STRING for the id column, not INTEGER
                    $userId = $user->id;

                    // Log the user ID being processed
                    $this->logBigQuery('Processing user with ID', [
                        'user_id' => $userId,
                        'php_type' => gettype($userId),
                    ]);

                    // IMPORTANT: Use deduplication approach instead of trying to update
                    // First, delete the user record if it exists using raw SQL (won't error if doesn't exist)
                    // Use quotes around the ID value since BigQuery stores it as a STRING
                    $deleteQuery = "DELETE FROM `{$datasetId}.users` WHERE id = '{$userId}'";

                    // Use the existing executeRawQuery method which already has retry logic
                    $result = $bigQueryService->executeRawQuery($deleteQuery);

                    if ($result !== null) {
                        $this->logBigQuery('Deleted existing user record if present', ['user_id' => $userId]);
                    } else {
                        $this->logBigQuery('Possible issue deleting user, will proceed with insert', [
                            'user_id' => $userId,
                        ], 'warning');
                    }

                    // Then insert as new record
                    $result = $bigQueryService->insertData($datasetId, 'users', $userData);
                } finally {
                    // Always release the lock when we're done, even if an exception occurred
                    if ($lockAcquired) {
                        Redis::del($lockKey);
                        $this->logBigQuery('Released lock for user', ['user_id' => $userId]);
                    }
                }

                if ($result === true) {
                    $this->successfulUserIds[] = $userId;
                    $successCount++;
                } else {
                    $this->failedUserIds[] = $userId;
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->logBigQuery('Exception processing user in batch', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], 'error');

                $this->failedUserIds[] = $userId;
                $failureCount++;
            }
        }

        $duration = now()->diffInSeconds($startTime);
        $this->logBigQuery('Batch processing completed', [
            'batch_size' => count($this->userIds),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'duration_seconds' => $duration,
            'avg_seconds_per_user' => $duration / max(1, count($this->userIds)),
        ]);
    }

    /**
     * Log a message to the BigQuery sync log file
     */
    private function logBigQuery(string $message, array $context = [], string $level = 'info'): void
    {
        $context['job'] = 'ProcessUserBigQueryBatchJob';
        $context['job_id'] = $this->job ? $this->job->getJobId() : 'unknown';
        $context['batch_size'] = count($this->userIds);

        Log::channel('bigquery')->$level($message, $context);
    }

    /**
     * Prepare user data for BigQuery
     *
     * This method has been enhanced to handle schema mismatches by:
     * 1. Removing sensitive data
     * 2. Adding common calculated fields
     * 3. Converting date fields to proper format
     * 4. Adding domain information
     */
    private function prepareUserData(User $user): array
    {
        // Convert user model to array
        $userData = $user->toArray();

        // Remove sensitive fields
        unset($userData['password']);
        unset($userData['remember_token']);

        // Add any calculated or related fields
        if (isset($user->userType)) {
            $userData['user_type_name'] = $user->userType->name ?? '';
        } else {
            $userData['user_type_name'] = '';
        }

        // Add domain info if relevant
        if (function_exists('getActiveDomain')) {
            $domain = getActiveDomain();
            if ($domain) {
                $userData['domain'] = $domain->domain;
            }
        }

        // Ensure numeric fields are correctly typed
        foreach (['id', 'user_type', 'is_active', 'is_admin', 'status'] as $numField) {
            if (isset($userData[$numField])) {
                $userData[$numField] = (int) $userData[$numField];
            }
        }

        // Ensure boolean fields are correctly typed
        foreach (['is_admin', 'is_active'] as $boolField) {
            if (isset($userData[$boolField])) {
                $userData[$boolField] = (bool) $userData[$boolField];
            }
        }

        // Ensure dates are properly formatted for BigQuery
        foreach (['created_at', 'updated_at', 'email_verified_at', 'birth_date', 'hire_date', 'start_date', 'dob', 'last_login_at'] as $dateField) {
            if (isset($userData[$dateField]) && ! empty($userData[$dateField])) {
                try {
                    $date = new \DateTime($userData[$dateField]);
                    $userData[$dateField] = $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // If date parsing fails, set to null
                    $userData[$dateField] = null;
                }
            }
        }

        // Ensure all string fields are actually strings
        foreach ($userData as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $userData[$key] = json_encode($value);
            }
        }

        return $userData;
    }
}
