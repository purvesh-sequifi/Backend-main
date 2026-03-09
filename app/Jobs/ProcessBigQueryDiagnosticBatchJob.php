<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessBigQueryDiagnosticBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userIds;

    protected $tableName;

    protected $batchId;

    protected $totalBatches;

    protected $shouldFix;

    /**
     * Create a new job instance.
     *
     * @param  array  $userIds  Array of user IDs to check
     * @param  string  $tableName  BigQuery table name
     * @param  int  $batchId  Current batch identifier
     * @param  int  $totalBatches  Total number of batches
     * @param  bool  $shouldFix  Whether to fix missing records
     * @return void
     */
    public function __construct(array $userIds, string $tableName, int $batchId, int $totalBatches, bool $shouldFix = false)
    {
        $this->userIds = $userIds;
        $this->tableName = $tableName;
        $this->batchId = $batchId;
        $this->totalBatches = $totalBatches;
        $this->shouldFix = $shouldFix;

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
            Log::channel('bigquery')->warning('BigQuery integration is disabled. Skipping diagnostic batch job.');

            return;
        }

        $batchResults = [
            'checked_count' => 0,
            'present_count' => 0,
            'missing_count' => 0,
            'fixed_count' => 0,
            'error_count' => 0,
            'missing_users' => [],
        ];

        Log::channel('bigquery')->info("Processing diagnostic batch {$this->batchId}/{$this->totalBatches}", [
            'batch_size' => count($this->userIds),
        ]);

        foreach ($this->userIds as $userId) {
            try {
                $user = User::find($userId);
                if (! $user) {
                    $batchResults['error_count']++;

                    continue;
                }

                $batchResults['checked_count']++;

                // Ensure proper integer type for BigQuery
                $userId = (int) $userId;

                // Check if user exists in BigQuery
                $exists = $bigQueryService->checkRecordExists('', $this->tableName, 'id', $userId, 'INTEGER');

                if ($exists) {
                    $batchResults['present_count']++;
                } else {
                    $batchResults['missing_count']++;
                    $batchResults['missing_users'][] = [
                        'id' => $userId,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];

                    // Fix the missing record if requested
                    if ($this->shouldFix) {
                        $this->fixMissingUser($user, $bigQueryService);
                        $batchResults['fixed_count']++;
                    }
                }
            } catch (\Exception $e) {
                Log::channel('bigquery')->error('Error processing user in diagnostic batch', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'batch_id' => $this->batchId,
                ]);

                $batchResults['error_count']++;
            }
        }

        // Store batch results in cache with expiration time
        $cacheKey = "bigquery_diagnostic_batch_{$this->batchId}";
        Cache::put($cacheKey, $batchResults, now()->addMinutes(30));

        Log::channel('bigquery')->info("Completed diagnostic batch {$this->batchId}/{$this->totalBatches}", [
            'checked' => $batchResults['checked_count'],
            'present' => $batchResults['present_count'],
            'missing' => $batchResults['missing_count'],
            'fixed' => $batchResults['fixed_count'],
            'errors' => $batchResults['error_count'],
        ]);
    }

    /**
     * Fix missing user record in BigQuery
     */
    protected function fixMissingUser(User $user, BigQueryService $bigQueryService): bool
    {
        try {
            // Prepare user data for BigQuery
            $userData = $this->prepareUserData($user);

            // Add the user to BigQuery
            $result = $bigQueryService->insertData('', $this->tableName, $userData);

            return $result === true;
        } catch (\Exception $e) {
            Log::channel('bigquery')->error('Failed to fix missing user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Prepare user data for BigQuery
     */
    protected function prepareUserData(User $user): array
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
