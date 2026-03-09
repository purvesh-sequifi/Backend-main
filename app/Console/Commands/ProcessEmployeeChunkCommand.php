<?php

namespace App\Console\Commands;

use App\Models\FrEmployeeData;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessEmployeeChunkCommand extends Command
{
    protected $signature = 'fieldroutes:process-chunk {file}';

    protected $description = 'Process a chunk of employees from a temporary file';

    private $newEmployees = 0;

    private $updatedEmployees = 0;

    private $errors = 0;

    public function handle(): int
    {
        $tempFile = $this->argument('file');

        if (! file_exists($tempFile)) {
            $this->error("Temp file not found: {$tempFile}");

            return 1;
        }

        try {
            $data = json_decode(file_get_contents($tempFile), true);

            if (! $data || ! isset($data['chunk']) || ! isset($data['office_name'])) {
                $this->error('Invalid data format in temp file');

                return 1;
            }

            foreach ($data['chunk'] as $employeeData) {
                try {
                    // Only link to existing users - never create new ones
                    $sequifiId = null;
                    $rawEmail = $employeeData['email'] ?? null;
                    $email = null;
                    if (is_string($rawEmail)) {
                        // Remove zero-width and BOM-like characters, then trim unicode whitespace
                        $email = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawEmail);
                        $email = preg_replace('/^[\p{Z}\s\h\v]+|[\p{Z}\s\h\v]+$/u', '', $email);
                    }
                    if (! empty($email)) {
                        // First check in users table (both email and work_email fields)
                        $user = User::where('email', $email)->orWhere('work_email', $email)->first();

                        if ($user) {
                            $sequifiId = $user->id;
                            $matchField = $user->email === $email ? 'email' : 'work_email';
                            // Log successful match
                            Log::info("Found matching user in users table: ID {$sequifiId} for {$matchField} {$email}");
                        } else {
                            // If not found in users table, check in additional emails
                            $additionalEmail = UsersAdditionalEmail::where('email', $email)->first();

                            if ($additionalEmail) {
                                $sequifiId = $additionalEmail->user_id;
                                Log::info("Found matching user via additional email: User ID {$sequifiId} for email {$email}");
                            } else {
                                // No matching user found - log it but don't create one
                                Log::info("No matching user found for FieldRoutes employee with email: {$email}");
                            }
                        }

                        // We no longer add additional emails here since we're not creating users
                    }

                    // Map FieldRoutes office_id to our integration office_id
                    $mappedOfficeId = $this->mapFieldRoutesOfficeIdToIntegrationOfficeId($employeeData['officeID'], $data['office_name']);

                    // Update or create employee record based on both employee_id AND office_id combination
                    // This ensures we have separate records for the same employee in different offices
                    $employee = FrEmployeeData::updateOrCreate(
                        [
                            'employee_id' => $employeeData['employeeID'],
                            'office_id' => $mappedOfficeId,
                        ],
                        [
                            'sequifi_id' => $sequifiId,
                            'office_id' => $mappedOfficeId,
                            'office_name' => $data['office_name'],
                            'active' => (bool) $employeeData['active'],
                            'fname' => $employeeData['fname'],
                            'lname' => $employeeData['lname'],
                            'initials' => $employeeData['initials'],
                            'nickname' => $employeeData['nickname'],
                            'type' => $employeeData['type'],
                            'phone' => $employeeData['phone'],
                            // Model mutator will also trim, but we pre-trim here for lookups consistency
                            'email' => $email,
                            'username' => $employeeData['username'],
                            'experience' => $employeeData['experience'],
                            'pic' => $employeeData['pic'],
                            'linked_employee_ids' => $employeeData['linkedEmployeeIDs'],
                            'employee_link' => $employeeData['employeeLink'],
                            'license_number' => $employeeData['licenseNumber'],
                            'supervisor_id' => $employeeData['supervisorID'],
                            'roaming_rep' => $employeeData['roamingRep'],
                            'regional_manager_office_ids' => $employeeData['regionalManagerOfficeIDs'],
                            'last_login' => $employeeData['lastLogin'],
                            'team_ids' => json_encode($employeeData['teamIDs']),
                            'primary_team' => $employeeData['primaryTeam'],
                            'access_control_profile_id' => $employeeData['accessControlProfileID'],
                            'start_address' => $employeeData['startAddress'],
                            'start_city' => $employeeData['startCity'],
                            'start_state' => $employeeData['startState'],
                            'start_zip' => $employeeData['startZip'],
                            'start_lat' => $employeeData['startLat'],
                            'start_lng' => $employeeData['startLng'],
                            'end_address' => $employeeData['endAddress'],
                            'end_city' => $employeeData['endCity'],
                            'end_state' => $employeeData['endState'],
                            'end_zip' => $employeeData['endZip'],
                            'end_lat' => $employeeData['endLat'],
                            'end_lng' => $employeeData['endLng'],
                            'two_factor_required' => (bool) $employeeData['twoFactorRequired'],
                            'two_factor_config_due_date' => $employeeData['twoFactorConfigDueDate'],
                            'skills' => json_encode($employeeData['skills']),
                            'access_control' => json_encode($employeeData['accessControl']),
                            'access_control_profile_name' => $employeeData['accessControl'][0]['accessControlProfileName'] ?? null,
                            'date_updated' => $employeeData['dateUpdated'],
                        ]
                    );

                    if ($employee->wasRecentlyCreated) {
                        $this->newEmployees++;
                    } else {
                        $this->updatedEmployees++;
                    }

                } catch (\Exception $e) {
                    Log::error('Error processing employee in chunk', [
                        'employee_id' => $employeeData['employeeID'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->errors++;
                }
            }

            // Output results
            $this->info(json_encode([
                'new_employees' => $this->newEmployees,
                'updated_employees' => $this->updatedEmployees,
                'errors' => $this->errors,
            ]));

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to process chunk: '.$e->getMessage());

            return 1;
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Map FieldRoutes officeID to our integration office_id
     * This fixes the discrepancy where FieldRoutes API returns different office IDs
     * than what we have configured in our integrations
     */
    protected function mapFieldRoutesOfficeIdToIntegrationOfficeId($fieldRoutesOfficeId, $officeName)
    {
        try {
            // First, try to find integration by office name (most reliable for data correction)
            $integrationByName = \App\Models\Integration::where('name', 'FieldRoutes')
                ->where('description', $officeName)
                ->where('status', 1)
                ->first();

            if ($integrationByName) {
                try {
                    // Decrypt the integration config
                    try {
                        $decrypted = decrypt($integrationByName->value);
                        $config = json_decode($decrypted, true);
                    } catch (\Exception $decryptException) {
                        $decrypted = openssl_decrypt(
                            $integrationByName->value,
                            config('app.encryption_cipher_algo'),
                            config('app.encryption_key'),
                            0,
                            config('app.encryption_iv')
                        );
                        $config = json_decode($decrypted, true);
                    }

                    if ($config && isset($config['office_id'])) {
                        $correctOfficeId = $config['office_id'];

                        // If FieldRoutes office_id doesn't match our config, log the correction
                        if ($fieldRoutesOfficeId != $correctOfficeId) {
                            Log::info("Correcting FieldRoutes data: office_id {$fieldRoutesOfficeId} → {$correctOfficeId} for {$officeName}");
                        }

                        return $correctOfficeId;
                    }
                } catch (\Exception $e) {
                    // Fall through to office_id matching
                }
            }

            // Fallback: Find by office_id if office name didn't work
            $integrations = \App\Models\Integration::where('name', 'FieldRoutes')
                ->where('status', 1)
                ->get();

            foreach ($integrations as $integration) {
                try {
                    // Decrypt the integration config
                    try {
                        $decrypted = decrypt($integration->value);
                        $config = json_decode($decrypted, true);
                    } catch (\Exception $decryptException) {
                        $decrypted = openssl_decrypt(
                            $integration->value,
                            config('app.encryption_cipher_algo'),
                            config('app.encryption_key'),
                            0,
                            config('app.encryption_iv')
                        );
                        $config = json_decode($decrypted, true);
                    }

                    if (! $config || ! isset($config['office_id'])) {
                        continue; // Skip invalid config
                    }

                    // Match by office_id as fallback
                    if ($config['office_id'] == $fieldRoutesOfficeId) {
                        return $config['office_id'];
                    }

                } catch (\Exception $e) {
                    continue;
                }
            }

            // If no mapping found, log warning and return original
            Log::warning("No integration mapping found for FieldRoutes office_id: {$fieldRoutesOfficeId} ({$officeName}). Using original value.");

            return $fieldRoutesOfficeId;

        } catch (\Exception $e) {
            Log::error("Error mapping office_id for {$officeName}: ".$e->getMessage());

            return $fieldRoutesOfficeId; // Fallback to original value
        }
    }
}
