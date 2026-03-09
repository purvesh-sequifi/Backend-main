<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AddUpdateUserOnBigQueryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userArray;

    public $tries = 3; // Number of retry attempts

    public $timeout = 120; // Timeout in seconds

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // No BigQueryService initialization here
    }

    /**
     * Execute the job.
     */
    /**
     * Write to a dedicated BigQuery sync log file
     */
    private function logBigQuery(string $message, array $context = [])
    {
        $logPath = storage_path('logs/bigquery_sync.log');
        $timestamp = now()->format('Y-m-d H:i:s');
        $contextStr = ! empty($context) ? ' '.json_encode($context) : '';
        $logLine = "[{$timestamp}] {$message}{$contextStr}\n";

        File::append($logPath, $logLine);
        Log::info($message, $context);
    }

    public function handle(): void
    {
        try {
            // Check if BigQuery integration is enabled
            $bigQueryService = new BigQueryService;
            if (! $bigQueryService->isEnabled()) {
                $this->logBigQuery('BigQuery integration is disabled. Skipping user sync job.');

                return;
            }

            $successCount = 0;
            $failureCount = 0;
            $startTime = now();

            // Log current BigQuery dataset and project info
            $this->logBigQuery('Starting BigQuery user sync job', [
                'dataset' => config('bigquery.default_dataset'),
                'project_id' => config('bigquery.project_id'),
                'total_users' => User::count(),
            ]);

            // Only sync active users by default, unless disabled in config
            $query = User::query();
            if (config('bigquery.sync_active_users_only', true)) {
                $query->where('is_active', 1);
                $this->logBigQuery('Filtering to sync only active users', [
                    'active_user_count' => User::where('is_active', 1)->count(),
                    'total_user_count' => User::count(),
                ]);
            }

            $query->chunk(100, function ($users) use ($bigQueryService, &$successCount, &$failureCount) {
                $this->logBigQuery('Processing chunk of users', ['chunk_size' => count($users)]);
                foreach ($users as $user) {
                    try {
                        $result = $this->addOrUpdateUserOnBigQuery($user, $bigQueryService);
                        if ($result) {
                            $successCount++;
                        } else {
                            $failureCount++;
                            $this->logBigQuery('Failed to sync user', [
                                'user_id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                            ]);
                        }
                    } catch (\Exception $e) {
                        $failureCount++;
                        $this->logBigQuery('Error syncing user to BigQuery: '.$e->getMessage(), [
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $endTime = now();
            $duration = $endTime->diffInSeconds($startTime);

            $this->logBigQuery('AddUpdateUserOnBigQueryJob completed', [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'duration_seconds' => $duration,
                'local_database_total_users' => User::count(),
                'local_database_active_users' => User::where('is_active', 1)->count(),
            ]);
        } catch (\Exception $e) {
            $this->logBigQuery('AddUpdateUserOnBigQueryJob failed with exception: '.$e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Add or update user record on BigQuery.
     *
     * @return bool Success status
     */
    private function addOrUpdateUserOnBigQuery(User $user, BigQueryService $bigQueryService): bool
    {
        try {
            // Prepare user data for BigQuery
            $this->userArray = $this->prepareUserData($user);

            // Use configuration values instead of hardcoded strings
            $datasetId = config('bigquery.default_dataset');
            $tableId = 'users';
            $userIdField = 'id';
            $userId = $user->id;

            // Log the sync operation start
            $this->logBigQuery('Processing user sync', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'is_active' => $user->is_active,
            ]);

            // Check if the user exists in BigQuery
            $userExists = $bigQueryService->checkRecordExists($datasetId, $tableId, $userIdField, $userId, 'INTEGER');

            $result = false;
            if ($userExists) {
                $result = $this->updateUserOnBigQuery($bigQueryService);
                if ($result === true) {
                    $this->logBigQuery('Updated user in BigQuery', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'success',
                    ]);
                } else {
                    $this->logBigQuery('Failed to update user in BigQuery', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'failed',
                        'error' => is_array($result) ? json_encode($result) : 'Unknown error',
                    ], 'error');
                }
            } else {
                $result = $this->addUserOnBigQuery($bigQueryService);
                if ($result === true) {
                    $this->logBigQuery('Added new user to BigQuery', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'success',
                    ]);
                } else {
                    $this->logBigQuery('Failed to add user to BigQuery', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => 'failed',
                        'error' => is_array($result) ? json_encode($result) : 'Unknown error',
                    ], 'error');
                }
            }

            return $result === true; // Ensure we return a boolean
        } catch (\Exception $e) {
            $this->logBigQuery('Exception processing user for BigQuery', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            return false;
        }
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

        // Log the prepared user data size
        $this->logBigQuery('Prepared user data for BigQuery', [
            'user_id' => $user->id,
            'field_count' => count($userData),
        ]);

        return $userData;
    }

    /**
     * Add a new user record to BigQuery
     *
     * @return bool|array Success status or error details
     */
    private function addUserOnBigQuery(BigQueryService $bigQueryService)
    {
        try {
            // Specifically check for BigQuery integration status
            $integration = Integration::where('name', 'BigQuery')->where('status', 1)->first();
            if (! $integration) {
                $this->logBigQuery('BigQuery integration is not enabled in the database', [
                    'user_id' => $this->userArray['id'] ?? 'unknown',
                ]);

                return false;
            }

            // Use the enhanced preprocessing to ensure schema compatibility
            // We pass 'users' as the table name to ensure schema matching
            $processedData = $bigQueryService->preprocessDataForBigQuery($this->userArray, 'users');

            // Log data size and fields
            $this->logBigQuery('Inserting user into BigQuery', [
                'user_id' => $processedData['id'] ?? 'unknown',
                'field_count' => count($processedData),
                'has_required_fields' => isset($processedData['id']) && isset($processedData['email']),
            ]);

            // Format data properly for BigQuery insertion
            $dataset = config('bigquery.default_dataset');

            // Log dataset configuration for debugging
            $this->logBigQuery('Using BigQuery dataset for insert', [
                'dataset' => $dataset,
                'config_value' => config('bigquery.default_dataset'),
                'env_value' => config('services.bigquery.default_dataset', 'not_set'),
                'user_id' => $processedData['id'] ?? 'unknown',
            ]);

            // Ensure dataset is not empty, use 'sequifi' as fallback
            if (empty($dataset)) {
                $dataset = 'sequifi';
                $this->logBigQuery('Empty dataset detected, using fallback value', [
                    'fallback_dataset' => $dataset,
                    'user_id' => $processedData['id'] ?? 'unknown',
                ]);
            }

            $bigQueryResponse = $bigQueryService->insertData($dataset, 'users', $processedData);

            // Handle different response types
            if (is_bool($bigQueryResponse) && $bigQueryResponse === true) {
                return true;
            } elseif (is_array($bigQueryResponse) && ! empty($bigQueryResponse['errors'])) {
                // Return error details for better logging
                $this->logBigQuery('BigQuery insert errors', [
                    'user_id' => $processedData['id'] ?? 'unknown',
                    'errors' => json_encode($bigQueryResponse['errors']),
                ], 'error');

                return $bigQueryResponse;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->logBigQuery('Exception adding user to BigQuery', [
                'user_id' => $this->userArray['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ], 'error');

            return false;
        }
    }

    /**
     * Update an existing user record in BigQuery
     *
     * @return bool|array Success status or error details
     */
    private function updateUserOnBigQuery(BigQueryService $bigQueryService)
    {
        try {
            // Specifically check for BigQuery integration status
            $integration = Integration::where('name', 'BigQuery')->where('status', 1)->first();
            if (! $integration) {
                $this->logBigQuery('BigQuery integration is not enabled in the database', [
                    'user_id' => $this->userArray['id'] ?? 'unknown',
                ]);

                return false;
            }

            // Use the enhanced preprocessing to ensure schema compatibility
            $processedData = $bigQueryService->preprocessDataForBigQuery($this->userArray, 'users');

            $dataset = config('bigquery.default_dataset');

            // Log dataset configuration for debugging
            $this->logBigQuery('Using BigQuery dataset for update', [
                'dataset' => $dataset,
                'config_value' => config('bigquery.default_dataset'),
                'env_value' => config('services.bigquery.default_dataset', 'not_set'),
                'user_id' => $processedData['id'] ?? 'unknown',
            ]);

            // Ensure dataset is not empty, use 'sequifi' as fallback
            if (empty($dataset)) {
                $dataset = 'sequifi';
                $this->logBigQuery('Empty dataset detected, using fallback value', [
                    'fallback_dataset' => $dataset,
                    'user_id' => $processedData['id'] ?? 'unknown',
                ]);
            }

            // Use INTEGER type since we previously updated the field type in BigQueryService::fieldTypeMapping
            $condition = "id = {$processedData['id']}";

            // Log update operation details
            $this->logBigQuery('Updating user in BigQuery', [
                'user_id' => $processedData['id'],
                'field_count' => count($processedData),
            ]);

            // Additional check to ensure we're actually updating something
            if (count($processedData) <= 1) { // Only has ID
                $this->logBigQuery('No data to update for user in BigQuery', [
                    'user_id' => $processedData['id'],
                ], 'warning');

                return false;
            }

            $bigQueryResponse = $bigQueryService->updateData($dataset, 'users', $processedData, $condition);

            // Handle different response types
            if (is_bool($bigQueryResponse) && $bigQueryResponse === true) {
                return true;
            } elseif (is_array($bigQueryResponse) && ! empty($bigQueryResponse['errors'])) {
                // Return error details for better logging
                $this->logBigQuery('BigQuery update errors', [
                    'user_id' => $processedData['id'],
                    'errors' => json_encode($bigQueryResponse['errors']),
                ], 'error');

                return $bigQueryResponse;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $this->logBigQuery('Exception updating user in BigQuery', [
                'user_id' => $this->userArray['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ], 'error');

            return false;
        }
    }
}
