<?php

namespace App\Core\Traits;

use App\Models\FrEmployeeData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRawDataHistoryLog;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * FieldRoutesUserDataTrait provides functionality for interacting with the FieldRoutes API
 * and processing employee data.
 *
 * This trait handles the following operations:
 * - Gathering user email addresses from various sources
 * - Finding matching legacy data in LegacyApiRawDataHistoryLog
 * - Finding matching employee data in FrEmployeeData
 * - Pulling employee data from FieldRoutes API for processing
 * - Processing and 808patching the retrieved data to SaleMasterJob
 * - Ensuring proper dataLink formatting in API calls
 */
trait FieldRoutesUserDataTrait
{
    /**
     * Prepares employee data with properly formatted dataLink parameter for FieldRoutes API
     *
     * This method takes employee data and ensures the dataLink parameter is correctly formatted
     * before sending it to the FieldRoutes API.
     *
     * @param  array|string  $employeeData  The employee data to prepare
     * @return array The prepared employee data with correctly formatted dataLink
     */
    public function prepareFieldRoutesEmployeeData($employeeData): array
    {
        // Check if the data is already JSON encoded
        if (is_string($employeeData) && $this->isJson($employeeData)) {
            $employeeData = json_decode($employeeData, true);
        }

        // Remove the dataLink parameter completely - we'll reformat it correctly
        if (isset($employeeData['dataLink'])) {
            unset($employeeData['dataLink']);
        }

        // Set employee_id properly
        $employeeId = '';
        if (! empty($employeeData['employee_id'])) {
            $employeeId = $employeeData['employee_id'];
        }

        // Add the dataLink in the correct format expected by FieldRoutes API
        // Based on log analysis, we need to create dataLink with proper structure
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');

        // These are separate fields for FieldRoutes, not nested
        $employeeData['dataLinkAlias'] = $employeeId;
        $employeeData['dataLink'] = '{"timeMark":"'.$currentDateTime.'"}';

        // Log the fix with the exact format we're using
        Log::info('FieldRoutesUserDataTrait: Fixed dataLink format for FieldRoutes API v2', [
            'dataLinkAlias' => $employeeData['dataLinkAlias'],
            'dataLink' => $employeeData['dataLink'],
        ]);

        return $employeeData;
    }

    /**
     * Helper function to check if a string is valid JSON
     *
     * @param  string  $string  The string to check
     * @return bool Whether the string is valid JSON
     */
    protected function isJson(string $string): bool
    {
        if (! is_string($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Properly format dataLink parameter for FieldRoutes API calls
     *
     * @param  string|array  $dataLink  The data link information to format
     * @return string Properly formatted dataLink JSON
     */
    public function formatFieldRoutesDataLink($dataLink): string
    {
        // If already a string and seems properly formatted, return as is
        if (is_string($dataLink) && strpos($dataLink, 'dataLinkAlias') !== false) {
            try {
                // Validate it's proper JSON
                $decoded = json_decode($dataLink, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['dataLinkAlias'])) {
                    return $dataLink;
                }
            } catch (\Exception $e) {
                // Fall through to reformatting
            }
        }

        // Parse the string if it's a malformed JSON string
        if (is_string($dataLink)) {
            try {
                $dataLink = json_decode($dataLink, true);
            } catch (\Exception $e) {
                // If can't parse, create a basic structure
                $dataLink = [
                    'dataLinkAlias' => '',
                    'dataLink' => json_encode(['timeMark' => Carbon::now()->format('Y-m-d H:i:s')]),
                ];
            }
        }

        // Handle array format
        if (is_array($dataLink)) {
            // Ensure proper structure
            if (! isset($dataLink['dataLinkAlias'])) {
                $dataLink['dataLinkAlias'] = $dataLink['employee_id'] ?? '';
            }

            // Handle inner dataLink property
            if (! isset($dataLink['dataLink']) || ! is_string($dataLink['dataLink'])) {
                $innerData = ['timeMark' => Carbon::now()->format('Y-m-d H:i:s')];
                $dataLink['dataLink'] = json_encode($innerData);
            }

            // Convert to properly formatted JSON
            return json_encode($dataLink);
        }

        // Fallback - create a valid default
        $defaultDataLink = [
            'dataLinkAlias' => '',
            'dataLink' => json_encode(['timeMark' => Carbon::now()->format('Y-m-d H:i:s')]),
        ];

        return json_encode($defaultDataLink);
    }

    /**
     * Fix the dataLink format in FieldRoutes API calls - no trait conflict method
     *
     * This method is designed to be called from createEmployeeForFieldRoutes to fix dataLink formatting
     * without causing trait method conflicts
     *
     * @param  array  $fieldRoutesData  The data to be sent to FieldRoutes API
     * @return array The updated data with fixed dataLink format
     */
    public function fixFieldRoutesDataLink(array $fieldRoutesData): array
    {
        // If dataLink exists in the data
        if (isset($fieldRoutesData['dataLink'])) {
            // Get a properly formatted dataLink JSON
            $fieldRoutesData['dataLink'] = $this->formatFieldRoutesDataLink($fieldRoutesData['dataLink']);
            Log::info('FieldRoutesUserDataTrait: Fixed dataLink format', ['dataLink' => $fieldRoutesData['dataLink']]);
        }

        return $fieldRoutesData;
    }

    /**
     * Fix DataLink format for FieldRoutes API
     *
     * This is an enhanced version of the fieldRoutesCreateEmployee method from FieldRoutesTrait
     * that ensures proper dataLink formatting before sending to the API
     *
     * @param  object  $data  The employee data object
     * @param  mixed  $checkStatus  The check status
     * @param  mixed  $uid  User ID
     * @param  string  $authenticationKey  FieldRoutes authentication key
     * @param  string  $authenticationToken  FieldRoutes authentication token
     * @param  string  $baseURL  FieldRoutes base URL
     * @return mixed The result from the createEmployeeForFieldRoutes method
     */
    public function enhancedFieldRoutesCreateEmployee(object $data, $checkStatus, $uid, string $authenticationKey, string $authenticationToken, string $baseURL)
    {
        // Log what we're doing
        Log::info('FieldRoutesUserDataTrait: Using enhanced fieldRoutesCreateEmployee with fixed dataLink format');

        // First, create a properly formatted dataLink
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');

        // Create the correct dataLink structure
        $innerData = ['timeMark' => $currentDateTime];
        $dataLinkObject = [
            'dataLinkAlias' => $data->employee_id ?? '',
            'dataLink' => json_encode($innerData),
        ];

        // Create properly formatted field routes data
        $fieldRoutesData = [
            'fname' => $data->first_name ?? null,
            'lname' => $data->last_name ?? null,
            'phone' => $data->mobile_no,
            'email' => $data->email,
            'dataLink' => json_encode($dataLinkObject), // Use our properly formatted dataLink
        ];

        // Log the data we're sending
        Log::info('FieldRoutesUserDataTrait: Sending properly formatted fieldRoutesData', [
            'dataLink' => json_encode($dataLinkObject),
            'email' => $data->email,
        ]);

        // Call the createEmployeeForFieldRoutes method directly
        $create_employees = $this->createEmployeeForFieldRoutes($fieldRoutesData, $authenticationKey, $authenticationToken, $baseURL, $data->id, $data->id);

        Log::info(['create_fieldroutes_employee_result' => $create_employees]);

        return $create_employees;
    }

    /**
     * Process FieldRoutes data for a user
     *
     * @param  User  $user  The user being onboarded
     */
    public function processFieldRoutesUserData(User $user): void
    {
        try {
            // Collect all possible emails for the user
            $userEmails = [];
            // Primary email from users table
            if (! empty($user->email)) {
                $userEmails[] = strtolower($user->email);
            }

            // Work email from users table
            if (! empty($user->work_email)) {
                $userEmails[] = strtolower($user->work_email);
            }

            // Additional emails from UsersAdditionalEmail table - use the relationship if loaded
            $additionalEmails = [];
            if ($user->relationLoaded('additionalEmails')) {
                // Use the loaded relationship
                $additionalEmails = $user->additionalEmails->pluck('email')->toArray();
            } else {
                // Fall back to a new query if relationship not loaded
                $additionalEmails = UsersAdditionalEmail::where('user_id', $user->id)
                    ->pluck('email')
                    ->toArray();
            }

            // Add the additional emails to our collection
            foreach ($additionalEmails as $email) {
                if (! empty($email)) {
                    $userEmails[] = strtolower($email);
                }
            }

            // Remove duplicates
            $userEmails = array_unique($userEmails);

            Log::info('Checking '.count($userEmails)." email addresses for user {$user->id}");

            // Step 1: Check LegacyApiRawDataHistoryLog for any of the user's emails - optimized query
            $legacyData = null;
            $matchedEmail = null;

            if (! empty($userEmails)) {
                // First try to find in log table
                $legacyLog = LegacyApiRawDataHistoryLog::whereIn('sales_rep_email', $userEmails)->first();
                if ($legacyLog) {
                    $legacyData = $legacyLog;
                    $matchedEmail = $legacyLog->sales_rep_email;
                    Log::info("Legacy log data found for email: {$matchedEmail}");
                }
                // If not in log table, check in main history table
                else {
                    $legacyRaw = LegacyApiRawDataHistory::whereIn('sales_rep_email', $userEmails)->first();
                    if ($legacyRaw) {
                        $legacyData = $legacyRaw;
                        $matchedEmail = $legacyRaw->sales_rep_email;
                        Log::info("Legacy raw data found for email: {$matchedEmail}");
                    }
                }

                // If legacy data found in either table, process it
                if (isset($matchedEmail)) {
                    try {
                        // Dynamically call the legacy:find-by-email command with --save option
                        $exitCode = \Illuminate\Support\Facades\Artisan::call('legacy:find-by-email', [
                            'emails' => [$matchedEmail],
                            '--save' => true,
                        ]);

                        if ($exitCode === 0) {
                            Log::info("Successfully executed legacy:find-by-email command for {$matchedEmail}");
                        } else {
                            Log::warning("Command legacy:find-by-email returned non-zero exit code {$exitCode} for {$matchedEmail}");
                        }
                    } catch (\Exception $e) {
                        Log::error('Error executing legacy:find-by-email command: '.$e->getMessage());
                    }
                }
            }

            if ($legacyData) {
                // We already have the matched email from the loop above
                Log::info("Legacy data found for user {$user->id} with email {$matchedEmail}");

                // Update the import_to_sales column to status code 3
                $legacyData->import_to_sales = 3;
                $legacyData->save();
                Log::info("Updated import_to_sales to 3 for legacy data ID: {$legacyData->id}");

                // Assign user to existing sale_masters records
                SalesMaster::whereIn('sales_rep_email', $userEmails)
                    ->update(['employee_id' => $user->id]);
                Log::info("Assigned user {$user->id} to sale_masters records for email(s): ".implode(', ', $userEmails));

                // Step 2: Search for FieldRoutes employee with any of the user's emails - optimized query
                $frEmployee = null;
                $frMatchedEmail = null;

                if (! empty($userEmails)) {
                    $frEmployee = FrEmployeeData::whereIn('email', $userEmails)
                        ->first();

                    if ($frEmployee) {
                        $frMatchedEmail = $frEmployee->email;
                        Log::info("FieldRoutes employee found with email: {$frMatchedEmail}");
                    }
                }
                if ($frEmployee) {
                    Log::info("FieldRoutes employee found: {$frEmployee->employee_id}");

                    // Step 3: Update FrEmployeeData with sequifi_id
                    $frEmployee->sequifi_id = $user->id;
                    $frEmployee->save();
                    Log::info("Updated FieldRoutes employee ID {$frEmployee->employee_id} with sequifi_id {$user->id}");

                    // Step 4: Sync FieldRoutes data for the employee with error handling
                    Log::info("Starting FieldRoutes data sync for user ID: {$user->id}, FR Employee ID: {$frEmployee->employee_id}");
                    try {
                        $this->syncEmployeeWithFieldRoutes($frEmployee->email);
                        Log::info("Successfully synced FieldRoutes data for {$frEmployee->email}");
                    } catch (\Exception $e) {
                        Log::error("Error syncing FieldRoutes data for {$frEmployee->email}: ".$e->getMessage());
                    }
                }
            } else {
                // Initialize variables once
                $frEmployee = null;
                $frMatchedEmail = null;

                // Check for FieldRoutes employee with any of user's emails
                if (! empty($userEmails)) {
                    $frEmployee = FrEmployeeData::whereIn('email', $userEmails)
                        ->first();
                    if ($frEmployee) {
                        $frMatchedEmail = $frEmployee->email;
                        Log::info("FieldRoutes employee found with email: {$frMatchedEmail}");
                    }
                }
                if ($frEmployee) {
                    Log::info("FieldRoutes employee found: {$frEmployee->employee_id}");

                    // Step 3: Update FrEmployeeData with sequifi_id
                    $frEmployee->sequifi_id = $user->id;
                    $frEmployee->save();
                    Log::info("Updated FieldRoutes employee ID {$frEmployee->employee_id} with sequifi_id {$user->id}");

                    // Step 4: Sync FieldRoutes data for the employee with error handling
                    Log::info("Starting FieldRoutes data sync for user ID: {$user->id}, FR Employee ID: {$frEmployee->employee_id}");
                    try {
                        $this->syncEmployeeWithFieldRoutes($frEmployee->email);
                        Log::info("Successfully synced FieldRoutes data for {$frEmployee->email}");
                    } catch (\Exception $e) {
                        Log::error("Error syncing FieldRoutes data for {$frEmployee->email}: ".$e->getMessage());
                    }
                }
                Log::info("No legacy data found for user {$user->id} with any of their associated emails");
            }
        } catch (\Exception $e) {
            Log::error('Error in processFieldRoutesUserData: '.$e->getMessage());
        }
    }

    public function syncEmployeeWithFieldRoutes($employeeEmail, $batchSize = null, $recursive = false)
    {
        if (empty($employeeEmail)) {
            Log::error('Employee email is required for syncing');

            return false;
        }

        $employeeId = null;
        $officeId = null;
        try {
            // Find user by email
            $user = User::where('email', $employeeEmail)->orwhere('work_email', $employeeEmail)->first();
            Log::info("User found for email {$employeeEmail}", ['user' => $user]);
            if (! $user) {
                // Try additional emails
                $additionalEmail = UsersAdditionalEmail::where('email', $employeeEmail)->first();
                if ($additionalEmail) {
                    $user = User::find($additionalEmail->user_id);
                }
            }
            Log::warning("User not found for email {$user}");
            if (! $user) {
                Log::warning("User not found for email {$employeeEmail}");

                return false;
            }

            // Find employee record for this user
            $frEmployee = FrEmployeeData::where('sequifi_id', $user->id)->first();

            if (! $frEmployee) {
                Log::warning("No FieldRoutes employee data found for {$employeeEmail}", [
                    'user_id' => $user->id,
                ]);

                return false;
            }

            $employeeId = $frEmployee->employee_id;
            $officeId = $frEmployee->office_id;

            Log::info("Found employee data for {$employeeEmail}", [
                'employee_id' => $employeeId,
                'office_id' => $officeId,
                'user_id' => $user->id,
            ]);
            // Build the command with required options
            $commandOptions = [
                '--employee_id' => $employeeId,
                '--office_id' => $officeId,
                '--single' => true,
            ];

            // Add optional parameters if provided
            if ($batchSize !== null) {
                $commandOptions['--batch'] = $batchSize;
            }

            if ($recursive) {
                $commandOptions['--recursive'] = true;
            }
            // Run the sync command
            $exitCode = Artisan::call('fieldroutes:sync-employees');
            $success = $exitCode === 0;
            if (! $success) {
                Log::error("Failed to sync employee data for {$employeeEmail}", [
                    'exit_code' => $exitCode,
                    'command_output' => Artisan::output(),
                    'employee_id' => $employeeId,
                    'office_id' => $officeId,
                ]);

                return false;
            }

            Log::info("Successfully synced employee data for {$employeeEmail}");

            // Import subscriptions for this employee
            $this->importSubscriptionsForEmployee($employeeId, $officeId, $employeeEmail);

        } catch (\Exception $e) {
            Log::error('Exception in syncEmployeeWithFieldRoutes: '.$e->getMessage(), [
                'email' => $employeeEmail,
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    protected function importSubscriptionsForEmployee($employeeId, $officeId, $employeeEmail = null)
    {
        try {
            if (empty($employeeId) || empty($officeId)) {
                Log::error('Cannot import subscriptions: Missing employee_id or office_id');

                return false;
            }

            // Use the current year's data (Jan 1st to today)
            $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
            $endDate = Carbon::now()->format('Y-m-d');

            Log::info('Importing subscriptions', [
                'employee_id' => $employeeId,
                // 'office_id' => $officeId,
                'email' => $employeeEmail,
                'period' => "{$startDate} to {$endDate}",
            ]);
            // Run the import command with focused parameters
            $exitCode = Artisan::call('fieldroutes:import-subscriptions', [
                $startDate,                  // First positional argument (startDate)
                $endDate,                    // Second positional argument (endDate)
                '--employee_id' => $employeeId,
                '--single_employee' => true,
            ]);
            if ($exitCode !== 0) {
                Log::error('Failed importing subscriptions', [
                    'employee_id' => $employeeId,
                    'exit_code' => $exitCode,
                ]);

                return false;
            }

            Log::info("Successfully imported subscriptions for employee {$employeeId}");

            return true;

        } catch (\Exception $e) {
            Log::error('Subscription import error: '.$e->getMessage(), [
                'employee_id' => $employeeId,
                'office_id' => $officeId,
            ]);

            return false;
        }
    }
}
