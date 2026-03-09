<?php

namespace App\Console\Commands;

use App\Models\FrEmployeeData;
use App\Models\Integration;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Log as LaravelLog;
use Symfony\Component\Process\Process;

class FieldRoutesEmployeeAndOfficeSync extends Command
{
    protected $signature = 'fieldroutes:sync-employees
                          {--employee_id= : Specific employee ID to sync}
                          {--office_id= : Specific office ID to sync}
                          {--single : Whether to process a single employee only}
                          {--batch-size=50 : Number of employees to process per batch}
                          {--recursive=0 : Whether to recursively discover and process connected offices}
                          {--memory-limit=512 : Memory limit in MB for the process}
                          {--parallel=4 : Number of parallel processes to run}
                          {--chunk-size=100 : Number of employees per chunk}';

    protected $description = 'Recursively fetch and sync all FieldRoutes employees starting from a specific office';

    protected $baseUrl;

    protected $authKey;

    protected $authToken;

    protected $processedOffices = [];

    protected $processedEmployees = [];

    protected $newEmployees = 0;

    protected $updatedEmployees = 0;

    protected $errors = 0;

    // API endpoints
    protected $EMPLOYEE_GET_ENDPOINT = '/employee/get/';

    protected $EMPLOYEE_MULTIPLE_ENDPOINT = '/employee/multiple';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Set memory limit based on option
        $memoryLimit = $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit.'M');
        $this->info("Memory limit set to {$memoryLimit}M");

        // Check if we're processing a single employee by ID
        $employeeId = $this->option('employee_id');
        $officeId = $this->option('office_id');
        $singleMode = $this->option('single');

        if ($employeeId && $singleMode) {
            $this->info("Single employee mode: Processing employee ID {$employeeId}");
            // For single employee mode, we'll directly call the employee processing
            $result = $this->processSingleEmployee($employeeId, $officeId);

            return $result ? 0 : 1;
        }

        $integrations = Integration::where('name', 'FieldRoutes')
            ->where('status', 1)
            ->get();
        // dd($integrations);

        $this->info('Found '.$integrations->count().' active FieldRoutes integrations');

        if ($integrations->isEmpty()) {
            $this->error('No active FieldRoutes integrations found');

            return 1;
        }

        foreach ($integrations as $integration) {
            try {
                // Decrypt the value field
                // Try Laravel's built-in decryption first
                try {
                    $decrypted = decrypt($integration->value);
                    $config = json_decode($decrypted, true);
                } catch (\Exception $decryptException) {
                    // If Laravel's decryption fails, try openssl_decrypt
                    $this->info("Trying alternative decryption method for integration ID: {$integration->id}");
                    $decrypted = openssl_decrypt(
                        $integration->value,
                        config('app.encryption_cipher_algo'),
                        config('app.encryption_key'),
                        0,
                        config('app.encryption_iv')
                    );
                    $config = json_decode($decrypted, true);
                    // dd($config);
                }

                if (! $config) {
                    $this->warn('Invalid configuration in integration settings for ID: '.$integration->id);

                    continue;
                }

                if (empty($config['office_id'])) {
                    $this->warn('No office ID found in integration config for ID: '.$integration->id);

                    continue;
                }

                $this->baseUrl = $config['base_url'];
                $this->authKey = $config['authenticationKey'];
                $this->authToken = $config['authenticationToken'];

                if (! $this->authKey || ! $this->authToken) {
                    $this->warn('Authentication credentials not found in integration config for ID: '.$integration->id);

                    continue;
                }
            } catch (\Exception $e) {
                $this->warn('Error decrypting configuration for ID: '.$integration->id.' - '.$e->getMessage());

                continue;
            }

            $officeId = $config['office_id'];
            $office_name = $integration->description;
            $this->info("Starting employee sync for office ID: {$officeId}");
            $this->processedOffices = [];
            $this->processedEmployees = [];
            $this->newEmployees = 0;
            $this->updatedEmployees = 0;
            $this->errors = 0;

            $recursive = (bool) $this->option('recursive');
            $this->info('Recursive office discovery: '.($recursive ? 'Enabled' : 'Disabled'));

            $this->processOffice($officeId, $recursive, $office_name);

            $this->info("Sync completed for office {$officeId}!");
            $this->info("New employees: {$this->newEmployees}");
            $this->info("Updated employees: {$this->updatedEmployees}");
            $this->info("Errors: {$this->errors}");
            $this->line('----------------------------------------');
        }

        return 0;
    }

    protected function processOffice($officeId, $recursive, $office_name)
    {
        if (in_array($officeId, $this->processedOffices)) {
            $this->info("Skipping already processed office ID: {$officeId}");

            return;
        }

        $this->processedOffices[] = $officeId;
        $this->info("\nProcessing office ID: {$officeId}");

        try {
            // Debug the API request
            $this->info("API URL: {$this->baseUrl}/employee/search");
            $this->info("Auth Key: {$this->authKey}");
            $this->info("Auth Token: {$this->authToken}");

            // Get all employees for this office with timeout configuration
            $response = Http::timeout(60)->withHeaders([
                'authenticationKey' => $this->authKey,
                'authenticationToken' => $this->authToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.'/employee/search', [
                'officeIDs' => [$officeId],
                'type' => 2,
                'includeData' => 1,
            ]);

            // Debug the response
            $this->info('Response Status: '.$response->status());
            $this->info('Response Body: '.$response->body());

            if (! $response->successful()) {
                $this->error("Failed to fetch employees for office {$officeId}");
                $this->error('Response Status: '.$response->status());
                $this->error('Response Body: '.$response->body());
                $this->errors++;

                return;
            }

            $data = $response->json();
            $this->info('Parsed JSON data: '.json_encode($data, JSON_PRETTY_PRINT));
            $employees = $data['employees'] ?? [];
            $this->info('Found '.count($employees).' employees in response');

            $chunkSize = (int) $this->option('chunk-size');
            $maxParallel = (int) $this->option('parallel');

            $this->info("Processing {$officeId} with {$maxParallel} parallel processes, chunk size: {$chunkSize}");

            // Split employees into chunks
            $chunks = array_chunk($employees, $chunkSize);
            $totalChunks = count($chunks);
            $this->info("Total chunks to process: {$totalChunks}");

            // Process chunks in parallel
            $processes = [];
            $completed = 0;

            foreach ($chunks as $index => $chunk) {
                // Convert chunk to JSON and save to temp file
                $tempFile = tempnam(sys_get_temp_dir(), 'chunk_');
                file_put_contents($tempFile, json_encode([
                    'chunk' => $chunk,
                    'office_name' => $office_name,
                    'auth_key' => $this->authKey,
                    'auth_token' => $this->authToken,
                ]));

                // Create artisan command to process this chunk
                $process = new Process([
                    'php',
                    'artisan',
                    'fieldroutes:process-chunk',
                    $tempFile,
                ]);

                $process->start();
                $processes[] = ['process' => $process, 'temp_file' => $tempFile];

                // If we've reached max parallel processes or this is the last chunk,
                // wait for some processes to complete
                if (count($processes) >= $maxParallel || $index === count($chunks) - 1) {
                    foreach ($processes as $key => $processData) {
                        if ($processData['process']->isRunning()) {
                            $processData['process']->wait(); // Wait for process to complete
                        }

                        // Process completed
                        $completed++;
                        $exitCode = $processData['process']->getExitCode();

                        if ($exitCode !== 0) {
                            $this->error('Chunk processing failed: '.$processData['process']->getErrorOutput());
                            $this->errors++;
                        } else {
                            // Parse the JSON output from the chunk processor
                            $output = trim($processData['process']->getOutput());
                            if ($output) {
                                try {
                                    $result = json_decode($output, true);
                                    if ($result) {
                                        $this->newEmployees += $result['new_employees'];
                                        $this->updatedEmployees += $result['updated_employees'];
                                        $this->errors += $result['errors'];
                                    }
                                } catch (\Exception $e) {
                                    $this->error('Failed to parse chunk result: '.$e->getMessage());
                                }
                            }
                        }

                        // Cleanup
                        if (file_exists($processData['temp_file'])) {
                            unlink($processData['temp_file']);
                        }
                        unset($processes[$key]);

                        // Show progress
                        $progress = ($completed / $totalChunks) * 100;
                        $this->info(sprintf('Progress: %.2f%% (%d/%d chunks)', $progress, $completed, $totalChunks));
                    }
                }
            }

            // Wait for any remaining processes
            while (! empty($processes)) {
                foreach ($processes as $key => $processData) {
                    if (! $processData['process']->isRunning()) {
                        $completed++;
                        unlink($processData['temp_file']);
                        unset($processes[$key]);
                    }
                }
                usleep(100000); // Sleep for 100ms
            }

            // Process additional offices if in recursive mode
            if ($recursive) {
                foreach ($employees as $employee) {
                    if (! empty($employee['regionalManagerOfficeIDs'])) {
                        $officeIds = explode(',', $employee['regionalManagerOfficeIDs']);
                        foreach ($officeIds as $newOfficeId) {
                            if (! in_array($newOfficeId, $this->processedOffices)) {
                                $this->processOffice($newOfficeId, $recursive, $office_name);
                            }
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("Error processing office {$officeId}: ".$e->getMessage());
            LaravelLog::error('FieldRoutes Office Processing Error', [
                'office_id' => $officeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errors++;
        }
    }

    protected function processEmployee($employeeData, $office_name)
    {
        try {
            $employeeId = $employeeData['employeeID'] ?? null;
            $officeId = $employeeData['officeID'] ?? null;

            if (! $employeeId || ! $officeId) {
                $this->error('Missing employee ID or office ID in data');
                $this->errors++;

                return;
            }

            // Log what we're processing
            $this->info('========================================');
            $this->info("Processing employee ID: {$employeeId} in office ID: {$officeId}");

            // Find matching Sequifi user by email
            $sequifiId = null;
            $email = isset($employeeData['email']) ? preg_replace('/^\s+|\s+$/u', '', $employeeData['email']) : null;

            if (! empty($email)) {
                // First check in users table (both email and work_email fields)
                $user = User::where('email', $email)->orWhere('work_email', $email)->first();

                if ($user) {
                    $sequifiId = $user->id;
                    $matchField = $user->email === $email ? 'email' : 'work_email';
                    $this->info("Found matching user in users table: ID {$sequifiId} for {$matchField} {$email}");
                } else {
                    // If not found in users table, check in users_additional_emails table
                    $additionalEmail = UsersAdditionalEmail::where('email', $email)->first();

                    if ($additionalEmail) {
                        $sequifiId = $additionalEmail->user_id;
                        $this->info("Found matching user in additional emails table: User ID {$sequifiId} for email {$email}");
                    } else {
                        $this->info("No matching user found for email {$email}");
                    }
                }
            } else {
                $this->info("Employee {$employeeId} has no email address");
            }

            // Get exact count of records with this employee_id
            $totalRecordsWithThisEmployeeId = FrEmployeeData::where('employee_id', $employeeId)->count();
            $this->info("Database has {$totalRecordsWithThisEmployeeId} records with employee_id {$employeeId}");

            // CRITICAL: Check if record exists with EXACT employee_id+office_id match
            $existingRecord = FrEmployeeData::where(function ($query) use ($employeeId, $officeId) {
                $query->where('employee_id', $employeeId)
                    ->where('office_id', $officeId);
            })->first();

            if ($existingRecord) {
                $this->info("FOUND EXISTING RECORD: ID={$existingRecord->id}, employee_id={$existingRecord->employee_id}, office_id={$existingRecord->office_id}");
                $this->info('Updating existing record');

                // Update the existing record
                $this->setEmployeeFields($existingRecord, $employeeData, $office_name, $sequifiId);
                $existingRecord->save();

                $this->updatedEmployees++;
            } else {
                $this->info("NO EXISTING RECORD found for employee_id={$employeeId}, office_id={$officeId}");
                $this->info('Creating new record');

                // Explicitly use DB transaction to ensure consistency
                \DB::beginTransaction();
                try {
                    // Create a brand new record
                    $newRecord = new FrEmployeeData;
                    $this->setEmployeeFields($newRecord, $employeeData, $office_name, $sequifiId);
                    $newRecord->save();

                    // Commit the transaction
                    \DB::commit();

                    $this->info("NEW RECORD CREATED with ID: {$newRecord->id}");
                    $this->newEmployees++;
                } catch (\Exception $transactionEx) {
                    // Rollback the transaction on error
                    \DB::rollBack();
                    $this->error("Transaction failed: {$transactionEx->getMessage()}");
                    throw $transactionEx; // Re-throw to be caught by outer catch
                }
            }

            // Double-check after operation
            $newTotalRecords = FrEmployeeData::where('employee_id', $employeeId)->count();
            $this->info("After operation: Database now has {$newTotalRecords} records with employee_id {$employeeId}");
            $this->info('========================================');

        } catch (\Exception $e) {
            $this->error("Error processing employee {$employeeData['employeeID']}: ".$e->getMessage());
            LaravelLog::error('FieldRoutes Employee Processing Error', [
                'employee_id' => $employeeData['employeeID'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errors++;
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
            $integrationByName = Integration::where('name', 'FieldRoutes')
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
                            $this->info("Correcting FieldRoutes data: office_id {$fieldRoutesOfficeId} → {$correctOfficeId} for {$officeName}");
                        }

                        return $correctOfficeId;
                    }
                } catch (\Exception $e) {
                    // Fall through to office_id matching
                }
            }

            // Fallback: Find by office_id if office name didn't work
            $integrations = Integration::where('name', 'FieldRoutes')
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
            $this->warn("No integration mapping found for FieldRoutes office_id: {$fieldRoutesOfficeId} ({$officeName}). Using original value.");

            return $fieldRoutesOfficeId;

        } catch (\Exception $e) {
            $this->error("Error mapping office_id for {$officeName}: ".$e->getMessage());

            return $fieldRoutesOfficeId; // Fallback to original value
        }
    }

    // Helper method to set employee fields
    protected function setEmployeeFields($employee, $employeeData, $office_name, $sequifiId)
    {
        $employee->employee_id = $employeeData['employeeID'];
        $employee->office_id = $this->mapFieldRoutesOfficeIdToIntegrationOfficeId($employeeData['officeID'], $office_name);
        $employee->office_name = $office_name;
        $employee->sequifi_id = $sequifiId;
        $employee->active = (bool) $employeeData['active'];
        $employee->fname = $employeeData['fname'];
        $employee->lname = $employeeData['lname'];
        $employee->initials = $employeeData['initials'];
        $employee->nickname = $employeeData['nickname'];
        $employee->type = $employeeData['type'];
        $employee->phone = $employeeData['phone'];
        $employee->email = isset($employeeData['email']) ? preg_replace('/^\s+|\s+$/u', '', $employeeData['email']) : null;
        $employee->username = $employeeData['username'];
        $employee->experience = $employeeData['experience'];
        $employee->pic = $employeeData['pic'];
        $employee->employee_link = $employeeData['employeeLink'];
        $employee->license_number = $employeeData['licenseNumber'];
        $employee->supervisor_id = $employeeData['supervisorID'];
        $employee->roaming_rep = (bool) $employeeData['roamingRep'];
        $employee->regional_manager_office_ids = $employeeData['regionalManagerOfficeIDs'];
        $employee->last_login = $employeeData['lastLogin'];
        $employee->address = $employeeData['address'] ?? null;
        $employee->city = $employeeData['city'] ?? null;
        $employee->state = $employeeData['state'] ?? null;
        $employee->zip = $employeeData['zip'] ?? null;
        $employee->start_address = $employeeData['startAddress'] ?? null;
        $employee->start_city = $employeeData['startCity'] ?? null;
        $employee->start_state = $employeeData['startState'] ?? null;
        $employee->start_zip = $employeeData['startZip'] ?? null;
        $employee->two_factor_required = (bool) ($employeeData['twoFactorRequired'] ?? false);
        $employee->two_factor_config_due_date = $employeeData['twoFactorConfigDueDate'] ?? null;
        $employee->skills = ! empty($employeeData['skills']) ? json_encode($employeeData['skills']) : null;
        $employee->access_control = ! empty($employeeData['accessControl']) ? json_encode($employeeData['accessControl']) : null;
        $employee->access_control_profile_name = ! empty($employeeData['accessControl']) ? ($employeeData['accessControl'][0]['accessControlProfileName'] ?? null) : null;
        $employee->date_updated = $employeeData['dateUpdated'] ?? now();
    }

    protected function showSummary()
    {
        // Report memory usage
        $memoryUsage = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->info("Peak memory usage: {$memoryUsage} MB");

        $this->newLine(2);
        $this->info('Sync Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Offices Processed', count($this->processedOffices)],
                ['Employees Processed', count($this->processedEmployees)],
                ['New Employees', $this->newEmployees],
                ['Updated Employees', $this->updatedEmployees],
                ['Errors', $this->errors],
            ]
        );
    }

    /**
     * Process a single employee by ID
     *
     * @param  string  $employeeId  The FieldRoutes employee ID to process
     * @param  string|null  $officeId  The office ID (optional)
     * @return bool Success or failure
     */
    protected function processSingleEmployee(string $employeeId, ?string $officeId = null): bool
    {
        $this->info("Processing single employee ID: {$employeeId}");

        try {
            // First, we need to set up auth credentials
            $integration = Integration::where('name', 'FieldRoutes')
                ->where('status', 1)
                ->first();

            if (! $integration) {
                $this->error('No active FieldRoutes integration found');

                return false;
            }

            try {
                // Try Laravel's built-in decryption first
                $decrypted = decrypt($integration->value);
                $config = json_decode($decrypted, true);
            } catch (\Exception $decryptException) {
                // If Laravel's decryption fails, try openssl_decrypt
                $this->info("Trying alternative decryption method for integration ID: {$integration->id}");
                $decrypted = openssl_decrypt(
                    $integration->value,
                    config('app.encryption_cipher_algo'),
                    config('app.encryption_key'),
                    0,
                    config('app.encryption_iv')
                );
                $config = json_decode($decrypted, true);
            }

            if (! $config) {
                $this->error('Invalid configuration in integration settings');

                return false;
            }

            $this->baseUrl = $config['base_url'] ?? 'https://aruza.fieldroutes.com/api';
            $this->authKey = $config['authenticationKey'];
            $this->authToken = $config['authenticationToken'];

            if (! $this->authKey || ! $this->authToken) {
                $this->error('Authentication credentials not found in integration config');

                return false;
            }

            // If office ID was not provided, try to get it from the employee record
            if (! $officeId) {
                $frEmployee = FrEmployeeData::where('employee_id', $employeeId)->first();
                if ($frEmployee) {
                    $officeId = $frEmployee->office_id;
                    $this->info("Found employee in database with office ID: {$officeId}");
                } else {
                    $this->warn("No local record found for employee ID: {$employeeId}");
                }
            }

            // Get employee details from FieldRoutes API
            $this->info('Fetching employee data from FieldRoutes API');

            // First try using employee/multiple endpoint as it's more reliable
            $response = Http::timeout(60)->withHeaders([
                'authenticationKey' => $this->authKey,
                'authenticationToken' => $this->authToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl.$this->EMPLOYEE_MULTIPLE_ENDPOINT, [
                'employeeIDs' => [$employeeId],
            ]);

            if (! $response->successful()) {
                $this->error("Failed to fetch employee with ID {$employeeId}");
                $this->error('Response Status: '.$response->status());
                $this->error('Response Body: '.$response->body());

                return false;
            }

            $data = $response->json();

            if (empty($data['employees'])) {
                $this->error("No employee data returned for ID {$employeeId}");

                return false;
            }

            // Process the employee data
            $employeeData = $data['employees'][0];
            $this->info('Successfully retrieved employee data');

            $result = $this->processEmployee($employeeData, $integration->description);

            if ($result) {
                $this->info("Employee {$employeeId} processed successfully");

                return true;
            } else {
                $this->error("Failed to process employee {$employeeId}");

                return false;
            }

        } catch (\Exception $e) {
            $this->error("Error processing employee {$employeeId}: ".$e->getMessage());
            LaravelLog::error('FieldRoutes Single Employee Processing Error', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
