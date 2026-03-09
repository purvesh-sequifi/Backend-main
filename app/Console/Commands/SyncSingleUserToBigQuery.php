<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BigQueryService;
use Illuminate\Console\Command;

class SyncSingleUserToBigQuery extends Command
{
    protected $signature = 'bigquery:sync-single-user {id : User ID to sync}';

    protected $description = 'Sync a single user to BigQuery by ID';

    public function handle(): int
    {
        $userId = $this->argument('id');
        $this->info("Attempting to sync user ID: {$userId} to BigQuery");

        // Find the user
        $user = User::find($userId);
        if (! $user) {
            $this->error("User with ID {$userId} not found");

            return Command::FAILURE;
        }

        $this->info("Found user: {$user->name} (ID: {$userId})");

        // Initialize BigQuery Service
        $bigQueryService = new BigQueryService;
        if (! $bigQueryService->isEnabled()) {
            $this->error('BigQuery integration is disabled');

            return Command::FAILURE;
        }

        // Prepare user data
        $userData = $this->prepareUserData($user);

        // Set dataset
        $datasetId = config('bigquery.default_dataset');

        // Delete existing user record if present
        $deleteQuery = "DELETE FROM `{$datasetId}.users` WHERE id = '{$userId}'";
        $this->info("Executing delete query: {$deleteQuery}");

        $result = $bigQueryService->executeRawQuery($deleteQuery);
        if ($result !== null) {
            $this->info("Successfully deleted any existing records for user ID {$userId}");
        } else {
            $this->warn('Possible issue with delete query, continuing with insert');
        }

        // Insert user data
        $this->info("Inserting user data for ID {$userId}");
        $result = $bigQueryService->insertData($datasetId, 'users', $userData);

        if ($result === true) {
            $this->info("Successfully synced user ID {$userId} to BigQuery");

            return Command::SUCCESS;
        } else {
            $this->error("Failed to sync user ID {$userId} to BigQuery");

            return Command::FAILURE;
        }
    }

    /**
     * Prepare user data for BigQuery.
     */
    protected function prepareUserData(User $user): array
    {
        // Get user data formatted for BigQuery
        $userData = [
            'id' => $user->id,
            'name' => $user->name ?? '',
            'email' => $user->email ?? '',
            'password' => '', // Don't sync actual password hash
            'remember_token' => '',
            'created_at' => $user->created_at ? $user->created_at->toDateTimeString() : null,
            'updated_at' => $user->updated_at ? $user->updated_at->toDateTimeString() : null,
            'billing_address' => $user->billing_address ?? '',
            'billing_city' => $user->billing_city ?? '',
            'billing_state' => $user->billing_state ?? '',
            'billing_zip' => $user->billing_zip ?? '',
            'company' => $user->company ?? '',
            'phone' => $user->phone ?? '',
            // Add other user fields as needed
        ];

        // Remove null values to prevent BigQuery type issues
        return array_filter($userData, function ($value) {
            return $value !== null;
        });
    }
}
