<?php

namespace App\Console\Commands;

use App\Models\FieldRoutesSyncLog;
use App\Models\Integration;
use App\Services\FieldRoutesApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GetFieldRoutesSubscriptions extends Command
{
    protected $apiService;

    // Track API calls for rate limiting and statistics
    protected $apiCallCount = 0;

    protected $customerCallCount = 0;

    protected $appointmentCallCount = 0;

    protected $maxApiCallsPerMinute = 60;  // Conservative limit

    protected $maxDailyApiCalls = 2000;    // Conservative daily limit

    protected $lastApiCallTime = null;

    protected $dailyRequestCount = 0;

    // Default batch sizes
    protected $defaultBatchSize = 100;      // Reduced from 500

    protected $defaultCustomerChunkSize = 50; // Reduced from 1000

    protected $defaultAppointmentChunkSize = 25; // Reduced from 100

    public function __construct(FieldRoutesApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fieldroutes:get-subscriptions 
                          {from_date : Start date (Y-m-d format)} 
                          {to_date : End date (Y-m-d format)}
                          {--office= : Filter by specific office name (optional)}
                          {--employee= : Filter by specific employee ID (optional)}
                          {--sequifi : Only sync reps with sequifi_id (integrated with Sequifi)}
                          {--all : Sync all field reps (type = 2)}
                          {--save : Save results to FieldRoutes_Raw_Data table}
                          {--limit=50 : Limit number of results to display}
                          {--export= : Export results to CSV file}
                          {--max-per-rep=500 : Maximum subscriptions per rep per run}
                          {--max-reps-per-office= : Maximum reps per office (optional)}
                          {--max-results=10000 : Maximum total results to fetch per office}
                          {--debug : Show detailed debug information}
                          {--dry-run : Preview what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk sync FieldRoutes subscriptions with rate limiting (max 50,000 results per office) and optimized API calls';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Increase memory limit for large data processing
        ini_set('memory_limit', '512M');

        // Set debug mode in API service
        $this->apiService->setDebug($this->option('debug'));

        $fromDate = $this->argument('from_date');
        $toDate = $this->argument('to_date');

        // Validate rep filtering options
        if (! $this->validateRepFilterOptions()) {
            return 1;
        }

        // Validate dates
        if (! $this->validateDates($fromDate, $toDate)) {
            return 1;
        }

        $isDryRun = $this->option('dry-run');
        $maxPerRep = $this->option('max-per-rep');
        $maxResults = $this->option('max-results');

        // Warn if max-results is over 50,000
        if ($maxResults > 50000) {
            $this->warn("⚠️  Warning: max-results is set to {$maxResults}, which may result in many API calls.");
            $this->warn('   Consider using a lower value (default: 50,000) to avoid rate limits.');
            if (! $this->confirm('Do you want to continue?')) {
                return 1;
            }
        }

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        $this->info("🚀 Starting bulk sync for dateUpdated range: {$fromDate} to {$toDate}");
        $this->displayRepFilterInfo();
        $this->line('');

        // First check encryption configuration
        if (! $this->checkEncryptionConfig()) {
            return 1;
        }

        // Then check integration statuses
        if (! $this->checkIntegrationStatuses()) {
            return 1;
        }

        // Get all active offices (with optional filtering)
        $offices = $this->getActiveOffices();

        if ($offices->isEmpty()) {
            $this->error('❌ No active offices found.');

            return 1;
        }

        $this->info("🏢 Found {$offices->count()} active office(s) to process");
        $this->line('');

        $totalStats = [
            'offices_processed' => 0,
            'reps_processed' => 0,
            'subscriptions_found' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_touched' => 0,
            'records_skipped' => 0,
            'total_available' => 0,
            'records_not_fetched' => 0,
            'errors' => 0,
            'customers_created' => 0,
            'customers_updated' => 0,
            'customers_skipped' => 0,
            'customers_touched' => 0,
            'customer_personal_updates' => 0,
            'customer_address_updates' => 0,
            'customer_status_updates' => 0,
            'customer_financial_updates' => 0,
            'appointments_created' => 0,
            'appointments_updated' => 0,
            'appointments_skipped' => 0,
            'appointments_touched' => 0,
            'status_updates' => 0,
            'schedule_updates' => 0,
            'identifier_updates' => 0,
            'updated_subscription_ids' => [], // Track subscription IDs that were updated
        ];

        // Process each office
        foreach ($offices as $office) {
            $this->line("🏢 <comment>Processing Office: {$office->description}</comment>");

            $officeStartTime = now();

            try {
                $reps = $this->getActiveRepsForOffice($office->description);

                if ($reps->isEmpty()) {
                    $this->line("   ⚠️  No active reps found for {$office->description}");

                    continue;
                }

                $this->line("   👥 Found {$reps->count()} active rep(s)");

                $officeStats = $this->processOffice($office, $reps, $fromDate, $toDate, $maxPerRep, $isDryRun);
                $totalStats['offices_processed']++;

                // Calculate processing duration for this office
                $officeEndTime = now();
                $durationSeconds = $officeStartTime->diffInSeconds($officeEndTime);

                // Log office stats to database
                if ($this->option('save') && ! $isDryRun) {
                    // Remove duplicate subscription IDs before logging
                    if (isset($officeStats['updated_subscription_ids'])) {
                        $officeStats['updated_subscription_ids'] = array_unique($officeStats['updated_subscription_ids']);
                    }
                    $this->logOfficeStats($officeStats, $office, $fromDate, $toDate, $durationSeconds, $isDryRun);
                }

                // Update total stats
                foreach ($officeStats as $key => $value) {
                    if (isset($totalStats[$key]) && $key !== 'offices_processed') {
                        if (is_array($value)) {
                            // Merge arrays (e.g., updated_subscription_ids) and remove duplicates
                            $totalStats[$key] = array_unique(array_merge($totalStats[$key], $value));
                        } else {
                            // Add numeric values
                            $totalStats[$key] += $value;
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->error("   ❌ Error processing office {$office->description}: ".$e->getMessage());
                $totalStats['errors']++;

                // Log error for this office
                if ($this->option('save') && ! $isDryRun) {
                    $officeEndTime = now();
                    $durationSeconds = $officeStartTime->diffInSeconds($officeEndTime);
                    $this->logOfficeError($office, $fromDate, $toDate, $e->getMessage(), $durationSeconds, $isDryRun);
                }
            }
        }

        // Display final summary
        $this->displayFinalSummary($totalStats, $isDryRun);

        return 0;
    }

    /**
     * Process office
     */
    protected function processOffice($office, $reps, $fromDate, $toDate, $maxPerRep, $isDryRun)
    {
        $stats = [
            'reps_processed' => 0,
            'subscriptions_found' => 0,
            'records_created' => 0,
            'records_updated' => 0,
            'records_touched' => 0,
            'records_skipped' => 0,
            'errors' => 0,
            'total_available' => 0,
            'records_not_fetched' => 0,
            'offices_processed' => 1,
            'customers_created' => 0,
            'customers_updated' => 0,
            'customers_skipped' => 0,
            'customers_touched' => 0,
            'customer_personal_updates' => 0,
            'customer_address_updates' => 0,
            'customer_status_updates' => 0,
            'customer_financial_updates' => 0,
            'appointments_created' => 0,
            'appointments_updated' => 0,
            'appointments_skipped' => 0,
            'appointments_touched' => 0,
            'status_updates' => 0,
            'schedule_updates' => 0,
            'identifier_updates' => 0,
            'updated_subscription_ids' => [], // Track subscription IDs that were updated
        ];

        try {
            $credentials = $this->apiService->decryptCredentials($office->value);

            if ($this->option('debug')) {
                $this->line("   🔍 Fetching subscriptions for office {$office->description}");
            }

            // Get all rep IDs
            $repIds = $reps->pluck('employee_id')->toArray();
            $stats['reps_processed'] = count($repIds);

            // Build query exactly like Postman
            $query = [
                'soldBy' => $repIds,
                'dateUpdated' => ['operator' => 'BETWEEN', 'value' => [$fromDate, $toDate]],
                'status' => ['operator' => '>=', 'value' => [-3]],
                'includeData' => 1,
                'officeIDs' => [$credentials['office_id']],
            ];

            // Store the correct office_id from credentials for later use
            $correctOfficeId = $credentials['office_id'];

            if ($this->option('debug')) {
                $this->line('   📝 Query: '.json_encode($query));
            }

            // Make ONE API call to get subscriptions
            $allSubscriptions = $this->apiService->searchSubscriptionsWithQuery($credentials, $query, $this->option('max-results'));

            $subscriptions = $allSubscriptions['subscriptions'] ?? [];
            $subscriptionIds = $allSubscriptions['subscriptionIDs'] ?? [];
            $totalAvailable = count($subscriptionIds); // Use the actual count of subscription IDs

            $stats['total_available'] = $totalAvailable;
            $stats['subscriptions_found'] = count($subscriptions);
            $stats['records_not_fetched'] = $totalAvailable - count($subscriptions);

            if ($this->option('debug')) {
                $this->line("   ✅ Found {$stats['subscriptions_found']} subscriptions out of {$stats['total_available']} total available");
            }

            // PAGINATION FIX: Fetch detailed data for missing subscription IDs
            // FieldRoutes API with includeData=1 only returns first 1,000 detailed records
            // but provides all matching subscription IDs. We need to fetch the missing details.
            if ($this->option('debug')) {
                $this->line('   🔍 Checking pagination: '.count($subscriptionIds).' IDs vs '.count($subscriptions).' detailed records');
            }

            if (count($subscriptionIds) > count($subscriptions)) {
                // Identify which subscription IDs are missing detailed data
                $fetchedIds = array_column($subscriptions, 'subscriptionID');
                $missingIds = array_diff($subscriptionIds, $fetchedIds);

                if ($this->option('debug')) {
                    $this->line('   🔄 API returned '.count($subscriptions).' detailed records but '.count($subscriptionIds).' total IDs');
                    $this->line('   🔄 Fetching details for '.count($missingIds).' missing subscriptions');
                }

                if (! empty($missingIds)) {
                    try {
                        // Use existing API service method to fetch missing subscription details
                        // This method handles pagination automatically for large datasets
                        $missingSubscriptions = $this->apiService->getSubscriptionDetails($missingIds, $credentials);
                        $subscriptions = array_merge($subscriptions, $missingSubscriptions);

                        // Update statistics to reflect the additional fetched records
                        $stats['subscriptions_found'] = count($subscriptions);
                        $stats['records_not_fetched'] = max(0, count($subscriptionIds) - count($subscriptions));

                        if ($this->option('debug')) {
                            $this->line('   ✅ Successfully fetched '.count($missingSubscriptions).' additional subscriptions');
                            $this->line('   📊 Total subscriptions now available: '.count($subscriptions));
                        }
                    } catch (\Exception $e) {
                        // Handle errors gracefully - log but continue with available data
                        $this->error('   ❌ Failed to fetch missing subscription details: '.$e->getMessage());
                        $stats['errors']++;

                        // Log detailed error information for debugging
                        Log::error('Failed to fetch missing subscription details', [
                            'office' => $office->description,
                            'missing_count' => count($missingIds),
                            'error' => $e->getMessage(),
                            'missing_ids_sample' => array_slice($missingIds, 0, 10), // Log first 10 IDs for debugging
                        ]);
                    }
                }
            }

            // Only save if --save flag is used and not in dry run mode
            if ($this->option('save') && ! $isDryRun) {
                // Collect unique customer IDs and appointment IDs
                $customerIds = [];
                $appointmentIds = [];

                if ($this->option('debug')) {
                    $this->line('   📅 Collecting appointment IDs from subscriptions...');
                }

                foreach ($subscriptions as $subscription) {
                    $customerIds[] = $subscription['customerID'];

                    if (! empty($subscription['initialAppointmentID'])) {
                        $appointmentIds[] = $subscription['initialAppointmentID'];
                        if ($this->option('debug')) {
                            $this->line("      Found initial appointment: {$subscription['initialAppointmentID']} for subscription {$subscription['subscriptionID']}");
                        }
                    }

                    if (! empty($subscription['completedAppointmentIDs'])) {
                        $completedIds = explode(',', $subscription['completedAppointmentIDs']);
                        $appointmentIds = array_merge($appointmentIds, $completedIds);
                        if ($this->option('debug')) {
                            $this->line('      Found completed appointments: '.implode(', ', $completedIds)." for subscription {$subscription['subscriptionID']}");
                        }
                    }
                }

                $customerIds = array_unique($customerIds);
                $appointmentIds = array_unique(array_filter($appointmentIds));

                if ($this->option('debug')) {
                    $this->line('   📊 Total unique appointments to process: '.count($appointmentIds));
                }

                // Get existing records to avoid duplicates
                $existingCustomers = DB::table('FieldRoutes_Customer_Data')
                    ->whereIn('customer_id', $customerIds)
                    ->pluck('customer_id')
                    ->toArray();

                $existingAppointments = DB::table('FieldRoutes_Appointment_Data')
                    ->whereIn('appointment_id', $appointmentIds)
                    ->pluck('appointment_id')
                    ->toArray();

                if ($this->option('debug')) {
                    $this->line('   📊 Found '.count($existingCustomers).' existing customers in database');
                    $this->line('   📊 Found '.count($existingAppointments).' existing appointments in database');
                }

                // Process all appointments in the date range
                if (! empty($appointmentIds)) {
                    if ($this->option('debug')) {
                        $this->line('   📅 Processing '.count($appointmentIds).' appointments');
                    }

                    $appointmentData = $this->apiService->getAppointmentDetailsBulk($appointmentIds, $credentials);
                    foreach ($appointmentData as $appointment) {
                        try {
                            // Get existing appointment data
                            $existingAppointment = DB::table('FieldRoutes_Appointment_Data')
                                ->where('appointment_id', $appointment['appointmentID'])
                                ->first();

                            if ($this->option('debug')) {
                                $this->line("   🔍 Processing appointment {$appointment['appointmentID']}");
                                if ($existingAppointment) {
                                    $this->line('      Found existing appointment with date_updated_fr: '.
                                        ($existingAppointment->date_updated_fr ?? 'null'));
                                    $this->line('      API appointment date_updated: '.
                                        ($appointment['dateUpdated'] ?? 'null'));
                                }
                            }

                            // Prepare new appointment data
                            $appointmentRecord = [
                                // Primary Identifiers
                                'appointment_id' => $appointment['appointmentID'],
                                'office_id_fr' => $appointment['officeID'] ?? null,
                                'office_id' => $office->id ?? null,
                                'office_name' => $office->description,

                                // Relationship Links
                                'customer_id' => $appointment['customerID'] ?? null,
                                'subscription_id' => $appointment['subscriptionID'] ?? null,
                                'original_appointment_id' => $appointment['originalAppointmentID'] ?? null,

                                // Status & Core Data
                                'status' => $appointment['status'] ?? 0,
                                'status_text' => $appointment['statusText'] ?? null,
                                'sales_anchor' => $appointment['salesAnchor'] ?? false,

                                // Scheduling Information
                                'scheduled_date' => isset($appointment['date']) ? Carbon::parse($appointment['date']) : null,
                                'scheduled_time' => $appointment['start'] ?? null,
                                'date_added' => isset($appointment['dateAdded']) ? Carbon::parse($appointment['dateAdded']) : null,
                                'date_completed' => isset($appointment['dateCompleted']) ? Carbon::parse($appointment['dateCompleted']) : null,
                                'date_cancelled' => isset($appointment['dateCancelled']) ? Carbon::parse($appointment['dateCancelled']) : null,
                                'date_updated_fr' => isset($appointment['dateUpdated']) ?
                                    Carbon::parse($appointment['dateUpdated']) : null,

                                // Service Information
                                'service_id' => $appointment['serviceID'] ?? null,
                                'service_type' => $appointment['serviceType'] ?? null,
                                'target_pests' => isset($appointment['targetPests']) ? json_encode($appointment['targetPests']) : null,

                                // Route & Location
                                'route_id' => $appointment['routeID'] ?? null,
                                'spot_id' => $appointment['spotID'] ?? null,

                                // Employee/Tech Assignments
                                'employee_id' => $appointment['employeeID'] ?? null,
                                'employee_name' => $appointment['employeeName'] ?? null,
                                'sequifi_id' => $appointment['sequifiID'] ?? null,
                                'assigned_tech' => $appointment['assignedTech'] ?? null,
                                'assigned_tech_name' => $appointment['assignedTechName'] ?? null,
                                'serviced_by' => $appointment['servicedBy'] ?? null,
                                'serviced_by_name' => $appointment['servicedByName'] ?? null,
                                'completed_by' => $appointment['completedBy'] ?? null,
                                'completed_by_name' => $appointment['completedByName'] ?? null,
                                'cancelled_by' => $appointment['cancelledBy'] ?? null,
                                'cancelled_by_name' => $appointment['cancelledByName'] ?? null,
                                'additional_techs' => isset($appointment['additionalTechs']) ? json_encode($appointment['additionalTechs']) : null,

                                // Sales Information
                                'sales_team_id' => $appointment['salesTeamID'] ?? null,
                                'sales_team_name' => $appointment['salesTeamName'] ?? null,

                                // Service Details
                                'service_notes' => $appointment['serviceNotes'] ?? null,
                                'service_amount' => $appointment['serviceAmount'] ?? null,
                                'products_used' => isset($appointment['productsUsed']) ? json_encode($appointment['productsUsed']) : null,
                                'duration_minutes' => $appointment['durationMinutes'] ?? null,

                                // Appointment Outcomes
                                'completion_notes' => $appointment['completionNotes'] ?? null,
                                'cancellation_notes' => $appointment['cancellationNotes'] ?? null,
                                'customer_present' => $appointment['customerPresent'] ?? null,
                                'customer_satisfaction' => $appointment['customerSatisfaction'] ?? null,

                                // Raw Data & Sync Info
                                'appointment_data' => json_encode($appointment),
                                'sync_status' => 'completed',
                                'last_synced_at' => now(),
                                'sync_batch_id' => uniqid('sync_', true),
                                'updated_at' => now(),
                                // Note: last_modified will be set only when there are actual changes
                            ];

                            // Check for changes and get which fields changed
                            $changes = $this->hasAppointmentDataChanged($existingAppointment, $appointmentRecord);

                            if ($existingAppointment) {
                                // Check if the API date_updated is newer than our stored date_updated_fr
                                $apiDateUpdated = isset($appointment['dateUpdated']) ?
                                    Carbon::parse($appointment['dateUpdated']) : null;
                                $dbDateUpdated = $existingAppointment->date_updated_fr ?
                                    Carbon::parse($existingAppointment->date_updated_fr) : null;

                                $forceUpdate = false;
                                if ($apiDateUpdated && $dbDateUpdated && $apiDateUpdated->gt($dbDateUpdated)) {
                                    $forceUpdate = true;
                                    if ($this->option('debug')) {
                                        $this->line('      Force update due to newer API date_updated');
                                    }
                                }

                                if ($changes['any_change'] || $forceUpdate) {
                                    if ($this->option('debug')) {
                                        $this->line('      Changes detected in:');
                                        foreach ($changes as $field => $changed) {
                                            if ($changed && $field !== 'any_change') {
                                                $this->line('         - '.str_replace('_', ' ', ucfirst($field)));
                                            }
                                        }
                                    }

                                    // Only update last_modified if there are actual changes
                                    $appointmentRecord['last_modified'] = now();

                                    // Update existing record
                                    DB::table('FieldRoutes_Appointment_Data')
                                        ->where('appointment_id', $appointment['appointmentID'])
                                        ->update($appointmentRecord);

                                    // Update last_modified in related subscription record
                                    if (! empty($appointment['subscriptionID'])) {
                                        $subscriptionUpdated = DB::table('FieldRoutes_Raw_Data')
                                            ->where('subscription_id', $appointment['subscriptionID'])
                                            ->update(['last_modified' => now()]);

                                        if ($subscriptionUpdated > 0) {
                                            // Track the subscription ID that was affected by appointment update
                                            $stats['updated_subscription_ids'][] = $appointment['subscriptionID'];

                                            if ($this->option('debug')) {
                                                $this->line("      🔗 Updated last_modified for related subscription {$appointment['subscriptionID']}");
                                            }
                                        }
                                    }

                                    // Update specific stats based on what changed
                                    if ($changes['status']) {
                                        $stats['status_updates']++;
                                    }
                                    if ($changes['schedule']) {
                                        $stats['schedule_updates']++;
                                    }
                                    if ($changes['identifiers']) {
                                        $stats['identifier_updates']++;
                                    }

                                    $stats['appointments_updated']++;
                                } else {
                                    $stats['appointments_skipped']++;
                                    if ($this->option('debug')) {
                                        $this->line('      ⏭️  No changes detected');
                                    }
                                }
                            } else {
                                // Insert new record
                                $appointmentRecord['created_at'] = now();
                                $appointmentRecord['last_modified'] = now();

                                // Initialize field_changes for new record with current timestamp
                                $now = now()->toDateTimeString();
                                $appointmentRecord['field_changes'] = json_encode([
                                    'status' => $now,
                                    'schedule' => $now,
                                    'service_details' => $now,
                                    'tech_assignment' => $now,
                                    'completion_details' => $now,
                                ]);

                                DB::table('FieldRoutes_Appointment_Data')->insert($appointmentRecord);
                                $stats['appointments_created']++;
                                if ($this->option('debug')) {
                                    $this->line('      ➕ Created new appointment');
                                }
                            }

                            $stats['appointments_touched']++;
                        } catch (\Exception $e) {
                            $this->error("   ❌ Error processing appointment {$appointment['appointmentID']}: ".$e->getMessage());
                            $stats['errors']++;
                        }
                    }
                }

                // Process customers
                if (! empty($customerIds)) {
                    if ($this->option('debug')) {
                        $this->line('   👥 Processing '.count($customerIds).' customers');
                    }

                    $customerData = $this->apiService->getCustomerDetailsBulk($customerIds, $credentials);
                    foreach ($customerData as $customer) {
                        try {
                            // Get existing customer data
                            $existingCustomer = DB::table('FieldRoutes_Customer_Data')
                                ->where('customer_id', $customer['customerID'])
                                ->first();

                            if ($this->option('debug')) {
                                $this->line("   🔍 Processing customer {$customer['customerID']}");
                                if ($existingCustomer) {
                                    $this->line('      Found existing customer with last_modified: '.
                                        ($existingCustomer->last_modified ?? 'null'));
                                }
                            }

                            // Prepare new customer data
                            $customerRecord = [
                                // Primary identifiers
                                'customer_id' => $customer['customerID'],
                                'bill_to_account_id' => $customer['billToAccountID'] ?? null,
                                'office_id_fr' => $customer['officeID'] ?? null,
                                'office_id' => $office->id ?? null,
                                'office_name' => $office->description,

                                // Personal information
                                'fname' => $customer['fname'] ?? null,
                                'lname' => $customer['lname'] ?? null,
                                'company_name' => $customer['companyName'] ?? null,
                                'email' => $customer['email'] ?? null,
                                'phone1' => $customer['phone1'] ?? null,
                                'phone2' => $customer['phone2'] ?? null,

                                // Address information
                                'address' => $customer['address'] ?? null,
                                'city' => $customer['city'] ?? null,
                                'state' => $customer['state'] ?? null,
                                'zip' => $customer['zip'] ?? null,

                                // Status information
                                'status' => $customer['status'] ?? null,
                                'status_text' => $customer['statusText'] ?? null,
                                'active' => $customer['active'] ?? true,
                                'date_added' => isset($customer['dateAdded']) ? Carbon::parse($customer['dateAdded']) : null,
                                'date_cancelled' => isset($customer['dateCancelled']) ? Carbon::parse($customer['dateCancelled']) : null,

                                // Financial information
                                'balance' => $customer['balance'] ?? null,
                                'responsible_balance' => $customer['responsibleBalance'] ?? null,
                                'balance_age' => $customer['balanceAge'] ?? null,
                                'aging_date' => $this->safeParseDate($customer['agingDate'] ?? null)?->format('Y-m-d'),
                                'responsible_balance_age' => $customer['responsibleBalanceAge'] ?? null,
                                'responsible_aging_date' => $this->safeParseDate($customer['responsibleAgingDate'] ?? null)?->format('Y-m-d'),
                                'auto_pay_status' => $customer['autoPayStatus'] ?? false,
                                'a_pay' => $customer['aPay'] ?? null,

                                // Raw data & sync info
                                'customer_data' => json_encode($customer),
                                'sync_status' => 'completed',
                                'last_synced_at' => now(),
                                'sync_batch_id' => uniqid('sync_', true),
                                'updated_at' => now(),
                                // Note: last_modified will be set only when there are actual changes
                            ];

                            // Check for changes and get which fields changed
                            $changes = $this->hasCustomerDataChanged($existingCustomer, $customerRecord);

                            if ($existingCustomer) {
                                if ($changes['any_change']) {
                                    if ($this->option('debug')) {
                                        $this->line('      Changes detected in:');
                                        foreach ($changes as $field => $changed) {
                                            if ($changed && $field !== 'any_change') {
                                                $this->line('         - '.str_replace('_', ' ', ucfirst($field)));
                                            }
                                        }
                                    }

                                    // Only update last_modified if there are actual changes
                                    $customerRecord['last_modified'] = now();

                                    // Update existing record
                                    DB::table('FieldRoutes_Customer_Data')
                                        ->where('customer_id', $customer['customerID'])
                                        ->update($customerRecord);

                                    // Update last_modified in related subscription records
                                    $affectedSubscriptions = DB::table('FieldRoutes_Raw_Data')
                                        ->where('customer_id', $customer['customerID'])
                                        ->pluck('subscription_id')
                                        ->toArray();

                                    if (! empty($affectedSubscriptions)) {
                                        $subscriptionsUpdated = DB::table('FieldRoutes_Raw_Data')
                                            ->where('customer_id', $customer['customerID'])
                                            ->update(['last_modified' => now()]);

                                        if ($subscriptionsUpdated > 0) {
                                            // Track all subscription IDs that were affected by customer update
                                            $stats['updated_subscription_ids'] = array_merge(
                                                $stats['updated_subscription_ids'],
                                                $affectedSubscriptions
                                            );

                                            if ($this->option('debug')) {
                                                $this->line("      🔗 Updated last_modified for {$subscriptionsUpdated} related subscription(s): ".implode(', ', $affectedSubscriptions));
                                            }
                                        }
                                    }

                                    // Update specific stats based on what changed
                                    if ($changes['personal_info']) {
                                        $stats['customer_personal_updates']++;
                                    }
                                    if ($changes['address']) {
                                        $stats['customer_address_updates']++;
                                    }
                                    if ($changes['status']) {
                                        $stats['customer_status_updates']++;
                                    }
                                    if ($changes['financial']) {
                                        $stats['customer_financial_updates']++;
                                    }

                                    $stats['customers_updated']++;
                                } else {
                                    $stats['customers_skipped']++;
                                    if ($this->option('debug')) {
                                        $this->line('      ⏭️  No changes detected');
                                    }
                                }
                            } else {
                                // Insert new record
                                $customerRecord['created_at'] = now();
                                $customerRecord['last_modified'] = now();

                                // Initialize field_changes for new record with current timestamp
                                $now = now()->toDateTimeString();
                                $customerRecord['field_changes'] = json_encode([
                                    'personal_info' => $now,
                                    'address' => $now,
                                    'status' => $now,
                                    'financial' => $now,
                                ]);

                                DB::table('FieldRoutes_Customer_Data')->insert($customerRecord);
                                $stats['customers_created']++;
                                if ($this->option('debug')) {
                                    $this->line('      ➕ Created new customer');
                                }
                            }

                            $stats['customers_touched']++;
                        } catch (\Exception $e) {
                            $this->error("   ❌ Error processing customer {$customer['customerID']}: ".$e->getMessage());
                            $stats['errors']++;
                        }
                    }
                }

                // Process subscriptions
                foreach ($subscriptions as $subscription) {
                    try {
                        // Get existing subscription data
                        $existingSubscription = DB::table('FieldRoutes_Raw_Data')
                            ->where('subscription_id', $subscription['subscriptionID'])
                            ->first();

                        // Prepare new subscription data
                        $subscriptionData = [
                            // Primary Identifiers
                            'subscription_id' => $subscription['subscriptionID'],
                            'customer_id' => $subscription['customerID'],
                            'bill_to_account_id' => $subscription['billToAccountID'] ?? null,
                            'office_id_fr' => $subscription['officeID'] ?? null,
                            'office_id' => $correctOfficeId,
                            'office_name' => $office->description,

                            // Employee/Rep Data
                            'employee_id' => $subscription['employeeID'] ?? null,
                            'employee_name' => $subscription['employeeName'] ?? null,
                            'sequifi_id' => $subscription['sequifiID'] ?? null,
                            'sold_by' => $subscription['soldBy'] ?? null,
                            'sold_by_2' => $subscription['soldBy2'] ?? null,
                            'sold_by_3' => $subscription['soldBy3'] ?? null,
                            'preferred_tech' => $subscription['preferredTech'] ?? null,
                            'added_by' => $subscription['addedBy'] ?? null,

                            // Subscription Core Data
                            'active' => $subscription['active'] ?? 1,
                            'active_text' => $subscription['activeText'] ?? null,
                            'frequency' => $subscription['frequency'] ?? null,
                            'billing_frequency' => $subscription['billingFrequency'] ?? null,
                            'agreement_length' => $subscription['agreementLength'] ?? null,
                            'contract_added' => $this->safeParseDate($subscription['contractAdded']),
                            'on_hold' => $subscription['onHold'] ?? false,

                            // Service Data
                            'service_id' => $subscription['serviceID'] ?? null,
                            'service_type' => $subscription['serviceType'] ?? null,
                            'followup_service' => $subscription['followupService'] ?? null,
                            'annual_recurring_services' => $subscription['annualRecurringServices'] ?? null,
                            'template_type' => $subscription['templateType'] ?? null,
                            'parent_id' => $subscription['parentID'] ?? null,
                            'duration' => $subscription['duration'] ?? null,

                            // Financial Data
                            'initial_quote' => $subscription['initialQuote'] ?? null,
                            'initial_discount' => $subscription['initialDiscount'] ?? null,
                            'initial_service_total' => $subscription['initialServiceTotal'] ?? null,
                            'yif_discount' => $subscription['yifDiscount'] ?? null,
                            'recurring_charge' => $subscription['recurringCharge'] ?? null,
                            'contract_value' => $subscription['contractValue'] ?? null,
                            'annual_recurring_value' => $subscription['annualRecurringValue'] ?? null,
                            'max_monthly_charge' => $subscription['maxMonthlyCharge'] ?? null,
                            'initial_billing_date' => $this->safeParseDate($subscription['initialBillingDate']),
                            'next_billing_date' => $this->safeParseDate($subscription['nextBillingDate']),
                            'billing_terms_days' => $subscription['billingTermsDays'] ?? null,
                            'autopay_payment_profile_id' => $subscription['autopayPaymentProfileID'] ?? null,

                            // Lead Data
                            'lead_id' => $subscription['leadID'] ?? null,
                            'lead_date_added' => $this->safeParseDate($subscription['leadDateAdded']),
                            'lead_updated' => $this->safeParseDate($subscription['leadUpdated']),
                            'lead_added_by' => $subscription['leadAddedBy'] ?? null,
                            'lead_source_id' => $subscription['leadSourceID'] ?? null,
                            'lead_source' => $subscription['leadSource'] ?? null,
                            'lead_status' => $subscription['leadStatus'] ?? null,
                            'lead_status_text' => $subscription['leadStatusText'] ?? null,
                            'lead_stage_id' => $subscription['leadStageID'] ?? null,
                            'lead_stage' => $subscription['leadStage'] ?? null,
                            'lead_assigned_to' => $subscription['leadAssignedTo'] ?? null,
                            'lead_date_assigned' => $this->safeParseDate($subscription['leadDateAssigned']),
                            'lead_value' => $subscription['leadValue'] ?? null,
                            'lead_date_closed' => $this->safeParseDate($subscription['leadDateClosed']),
                            'lead_lost_reason' => $subscription['leadLostReason'] ?? null,
                            'lead_lost_reason_text' => $subscription['leadLostReasonText'] ?? null,

                            // Source Data
                            'source_id' => $subscription['sourceID'] ?? null,
                            'source' => $subscription['source'] ?? null,
                            'sub_source_id' => $subscription['subSourceID'] ?? null,
                            'sub_source' => $subscription['subSource'] ?? null,

                            // Scheduling & Preferences
                            'next_service' => $this->safeParseDate($subscription['nextService']),
                            'last_completed' => $this->safeParseDate($subscription['lastCompleted']),
                            'next_appointment_due_date' => $this->safeParseDate($subscription['nextAppointmentDueDate']),
                            'last_appointment' => $this->safeParseDate($subscription['lastAppointment']),
                            'preferred_days' => $subscription['preferredDays'] ?? null,
                            'preferred_start' => $subscription['preferredStart'] ?? null,
                            'preferred_end' => $subscription['preferredEnd'] ?? null,
                            'call_ahead' => $subscription['callAhead'] ?? false,
                            'seasonal_start' => $this->safeParseDate($subscription['seasonalStart']),
                            'seasonal_end' => $this->safeParseDate($subscription['seasonalEnd']),
                            'custom_schedule_id' => $subscription['customScheduleID'] ?? null,

                            // Appointment Data
                            'initial_appointment_id' => $subscription['initialAppointmentID'] ?? null,
                            'initial_status' => $subscription['initialStatus'] ?? null,
                            'initial_status_text' => $subscription['initialStatusText'] ?? null,
                            'appointment_ids' => isset($subscription['appointmentIDs']) ? json_encode($subscription['appointmentIDs']) : null,
                            'completed_appointment_ids' => isset($subscription['completedAppointmentIDs']) ? json_encode(explode(',', $subscription['completedAppointmentIDs'])) : null,

                            // Specialized Features
                            'sentricon_connected' => $subscription['sentriconConnected'] ?? false,
                            'sentricon_site_id' => $subscription['sentriconSiteID'] ?? null,
                            'region_id' => $subscription['regionID'] ?? null,
                            'capacity_estimate' => $subscription['capacityEstimate'] ?? null,
                            'unit_ids' => isset($subscription['unitIDs']) ? json_encode($subscription['unitIDs']) : null,
                            'add_ons' => isset($subscription['addOns']) ? json_encode($subscription['addOns']) : null,

                            // Renewal & Contract
                            'renewal_frequency' => $subscription['renewalFrequency'] ?? null,
                            'renewal_date' => $this->safeParseDate($subscription['renewalDate']),
                            'custom_date' => $this->safeParseDate($subscription['customDate']),
                            'expiration_date' => $this->safeParseDate($subscription['expirationDate']),

                            // Financial Documents
                            'initial_invoice' => $subscription['initialInvoice'] ?? null,
                            'po_number' => $subscription['poNumber'] ?? null,
                            'recurring_ticket' => isset($subscription['recurringTicket']) ? json_encode($subscription['recurringTicket']) : null,

                            // Cancellation Data
                            'date_cancelled' => $this->safeParseDate($subscription['dateCancelled']),
                            'cancellation_notes' => $subscription['cancellationNotes'] ?? null,
                            'cancelled_by' => $subscription['cancelledBy'] ?? null,

                            // External Links
                            'subscription_link' => $subscription['subscriptionLink'] ?? null,

                            // Timestamps
                            'date_added' => $this->safeParseDate($subscription['dateAdded']),
                            'date_updated_fr' => $this->safeParseDate($subscription['dateUpdated']),

                            // Raw Data Storage
                            'subscription_data' => json_encode($subscription),

                            // Sync Metadata
                            'sync_status' => 'completed',
                            'last_synced_at' => now(),
                            'last_modified' => now(), // Always use current timestamp for new/updated records
                            'sync_batch_id' => uniqid('sync_', true),

                            // Standard Timestamps
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // Check if data has actually changed
                        if ($existingSubscription) {
                            if ($this->hasSubscriptionDataChanged($existingSubscription, $subscriptionData)) {
                                // Update existing record with current timestamp for last_modified
                                $subscriptionData['last_modified'] = now();

                                DB::table('FieldRoutes_Raw_Data')
                                    ->where('subscription_id', $subscription['subscriptionID'])
                                    ->update($subscriptionData);
                                $stats['records_updated']++;

                                // Track the updated subscription ID
                                $stats['updated_subscription_ids'][] = $subscription['subscriptionID'];

                                if ($this->option('debug')) {
                                    $this->line("   🔄 Updated subscription {$subscription['subscriptionID']} due to changes");
                                }
                            } else {
                                // No changes detected
                                $stats['records_skipped']++;
                                if ($this->option('debug')) {
                                    $this->line("   ⏭️  Skipped subscription {$subscription['subscriptionID']} - no changes");
                                }
                            }
                        } else {
                            // Insert new record
                            DB::table('FieldRoutes_Raw_Data')->insert($subscriptionData);
                            $stats['records_created']++;

                            // Track the created subscription ID
                            $stats['updated_subscription_ids'][] = $subscription['subscriptionID'];

                            if ($this->option('debug')) {
                                $this->line("   ➕ Created new subscription {$subscription['subscriptionID']}");
                            }
                        }

                        $stats['records_touched']++;
                    } catch (\Exception $e) {
                        $this->error("   ❌ Error processing subscription {$subscription['subscriptionID']}: ".$e->getMessage());
                        $stats['errors']++;
                    }
                }

                if ($this->option('debug')) {
                    $this->line("   ✅ Created {$stats['records_created']} subscriptions");
                    $this->line("   ✅ Created {$stats['customers_created']} customers");
                    $this->line("   ✅ Created {$stats['appointments_created']} appointments");
                }
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error processing office {$office->description}: ".$e->getMessage());
            $stats['errors']++;
        }

        return $stats;
    }

    /**
     * Display final summary of the bulk sync operation
     */
    protected function displayFinalSummary($stats, $isDryRun)
    {
        $this->line('');
        $this->info('🎯 Bulk Sync Summary');
        $this->line('================');

        if ($isDryRun) {
            $this->comment('(DRY RUN MODE - No actual changes made)');
            $this->line('');
        }

        $this->line("🏢 Offices Processed: <info>{$stats['offices_processed']}</info>");
        $this->line("👥 Reps Processed: <info>{$stats['reps_processed']}</info>");
        $this->line("📄 Total Records Available: <info>{$stats['total_available']}</info>");
        $this->line("📥 Subscriptions Fetched: <info>{$stats['subscriptions_found']}</info>");
        $this->line('');

        // Records Created section
        $this->line('📊 Records Created:');
        $this->line("   Subscriptions: <info>{$stats['records_created']}</info>");
        $this->line("   Customers: <info>{$stats['customers_created']}</info>");
        $this->line("   Appointments: <info>{$stats['appointments_created']}</info>");
        $this->line('');

        // Records Updated section with field-specific stats
        $this->line('🔄 Records Updated:');
        $this->line("   Subscriptions: <info>{$stats['records_updated']}</info>");
        $this->line("   Customers: <info>{$stats['customers_updated']}</info>");
        $this->line("   Appointments: <info>{$stats['appointments_updated']}</info>");
        $this->line('');

        // Customer Update Details
        if ($stats['customers_updated'] > 0) {
            $this->line('👥 Customer Update Details:');
            $this->line("   Personal Info Changes: <info>{$stats['customer_personal_updates']}</info>");
            $this->line("   Address Changes: <info>{$stats['customer_address_updates']}</info>");
            $this->line("   Status Changes: <info>{$stats['customer_status_updates']}</info>");
            $this->line("   Financial Changes: <info>{$stats['customer_financial_updates']}</info>");
            $this->line('');
        }

        // Appointment Update Details
        if ($stats['appointments_updated'] > 0) {
            $this->line('📅 Appointment Update Details:');
            $this->line("   Status Changes: <info>{$stats['status_updates']}</info>");
            $this->line("   Schedule Changes: <info>{$stats['schedule_updates']}</info>");
            $this->line("   Identifier Changes: <info>{$stats['identifier_updates']}</info>");
            $this->line('');
        }

        $this->line("👆 Records Touched: <info>{$stats['records_touched']}</info>");
        $this->line("⏭️  Records Skipped: <info>{$stats['records_skipped']}</info>");
        $this->line('');

        if ($stats['errors'] > 0) {
            $this->line("❌ Errors: <error>{$stats['errors']}</error>");
        } else {
            $this->line('❌ Errors: <info>0</info>');
        }

        $totalProcessed = $stats['records_created'] + $stats['records_updated'];
        $this->line('');
        $this->info("✅ Successfully processed {$totalProcessed} total records!");

        // Only show records_not_fetched if there's actually an issue
        if ($stats['records_not_fetched'] > 0) {
            $this->line('');
            $this->warn("⚠️  {$stats['records_not_fetched']} records were not fetched (API inconsistency detected)");
            $this->line('💡 This may indicate FieldRoutes API issues or data corruption');
            $this->line("   📊 Total available: {$stats['total_available']}, Fetched: {$stats['subscriptions_found']}");
        }
    }

    /**
     * Validate rep filtering options
     */
    protected function validateRepFilterOptions()
    {
        $sequifi = $this->option('sequifi');
        $all = $this->option('all');

        // Both options are exclusive
        if ($sequifi && $all) {
            $this->error('❌ Cannot use both --sequifi and --all options. Please choose one:');
            $this->line('   --sequifi: Sync only reps with sequifi_id (integrated with Sequifi)');
            $this->line('   --all: Sync all field reps (type = 2)');

            return false;
        }

        // At least one option must be specified
        if (! $sequifi && ! $all) {
            $this->error('❌ Please specify which reps to sync:');
            $this->line('   --sequifi: Sync only reps with sequifi_id (integrated with Sequifi)');
            $this->line('   --all: Sync all field reps (type = 2)');

            return false;
        }

        return true;
    }

    /**
     * Display rep filtering information
     */
    protected function displayRepFilterInfo()
    {
        if ($this->option('sequifi')) {
            $this->info('🎯 Rep Filter: Sequifi-integrated reps only (sequifi_id IS NOT NULL)');
        } elseif ($this->option('all')) {
            $this->info('🎯 Rep Filter: All field reps (type = 2)');
        }
    }

    /**
     * Validate date inputs
     */
    protected function validateDates($fromDate, $toDate)
    {
        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromDate);
            $to = Carbon::createFromFormat('Y-m-d', $toDate);

            if ($from->gt($to)) {
                $this->error('From date cannot be after to date.');

                return false;
            }

            if ($from->diffInDays($to) > 365) {
                $this->error('Date range cannot exceed 365 days.');

                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2024-01-01).');

            return false;
        }
    }

    /**
     * Check encryption configuration is properly set up
     */
    protected function checkEncryptionConfig()
    {
        $this->info('🔐 Checking Encryption Configuration...');

        $encryptionKey = config('app.encryption_key');
        $encryptionIv = config('app.encryption_iv');
        $encryptionCipher = config('app.encryption_cipher_algo', 'aes-256-cbc');

        $errors = [];

        if (! $encryptionKey) {
            $errors[] = 'ENCRYPTION_KEY is not set in environment';
        }

        if (! $encryptionIv) {
            $errors[] = 'ENCRYPTION_IV is not set in environment';
        }

        if (! $encryptionCipher) {
            $errors[] = 'ENCRYPTION_CIPHER_ALGO is not set in environment';
        }

        if (! empty($errors)) {
            $this->error('❌ Encryption configuration is incomplete:');
            foreach ($errors as $error) {
                $this->line("   • {$error}");
            }
            $this->line('');
            $this->comment('💡 Please ensure these environment variables are set:');
            $this->line('   ENCRYPTION_CIPHER_ALGO=aes-256-cbc');
            $this->line('   ENCRYPTION_IV=your_iv_here');
            $this->line('   ENCRYPTION_KEY=base64:your_key_here');

            return false;
        }

        $this->info('✅ Encryption configuration is properly set up.');
        $this->line('');

        return true;
    }

    /**
     * Check integration statuses before proceeding
     */
    protected function checkIntegrationStatuses()
    {
        $this->info('🔧 Checking Integration Statuses...');
        $this->line('');

        // Build query for integrations
        $query = Integration::fieldRoutes();

        // Apply office filter if provided
        if ($office = $this->option('office')) {
            $query->where('description', 'like', "%{$office}%");
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->error('❌ No FieldRoutes integrations found'.($office ? " matching office: {$office}" : '').'.');

            return false;
        }

        // Show integration status summary
        $activeCount = 0;
        $inactiveCount = 0;
        $headers = ['Office', 'Status', 'Created Date'];
        $rows = [];

        foreach ($integrations as $integration) {
            $isActive = $integration->status == 1;
            if ($isActive) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }

            $statusDisplay = $isActive ? '✅ Active' : '❌ Inactive';
            $rows[] = [
                $integration->description,
                $statusDisplay,
                $integration->created_at ? $integration->created_at->format('Y-m-d') : 'N/A',
            ];
        }

        $this->table($headers, $rows);
        $this->line('');

        // Summary
        $this->line('📊 <comment>Integration Status Summary:</comment>');
        $this->line("   Active Integrations: <info>{$activeCount}</info>");
        $this->line("   Inactive Integrations: <info>{$inactiveCount}</info>");
        $this->line('');

        if ($activeCount === 0) {
            $this->error('❌ No active integrations found. Cannot proceed with subscription fetch.');
            $this->line('');
            $this->comment('💡 To activate integrations, update the status field to 1 in the integrations table.');

            return false;
        }

        $this->info("✅ Found {$activeCount} active integration(s). Proceeding with subscription fetch...");
        $this->line('');

        return true;
    }

    /**
     * Get all active offices for bulk processing
     */
    protected function getActiveOffices()
    {
        $query = Integration::where('status', 1)
            ->where('name', 'FieldRoutes');

        // Apply office filter if provided
        if ($office = $this->option('office')) {
            $query->where('description', 'like', "%{$office}%");
        }

        return $query->get();
    }

    /**
     * Get all active reps for a specific office based on filtering options
     */
    protected function getActiveRepsForOffice($officeName)
    {
        $query = DB::table('fr_employee_data')
            ->where('office_name', $officeName)
            ->where('active', 1)
            ->select('employee_id', 'fname', 'lname', 'sequifi_id', 'type', 'office_name');

        // Apply rep filtering based on command options
        if ($this->option('sequifi')) {
            // Only reps with sequifi_id (integrated with Sequifi)
            $query->whereNotNull('sequifi_id');
        } elseif ($this->option('all')) {
            // All field reps (type = 2)
            $query->where('type', 2);
        }

        // Apply employee filter if provided
        if ($employee = $this->option('employee')) {
            $query->where('employee_id', $employee);
        }

        // Get total count for progress tracking before applying limit
        $totalCount = $query->count();

        // Add max-reps limit if specified (for large offices)
        if ($maxReps = $this->option('max-reps-per-office')) {
            $query->limit((int) $maxReps);
        }

        if ($totalCount > 100 && $this->option('debug')) {
            $filterType = $this->option('sequifi') ? 'Sequifi-integrated' : 'field';
            $this->line("   📊 Large office detected: {$totalCount} eligible {$filterType} employees");
            if ($maxReps && $totalCount > $maxReps) {
                $this->line("   🎯 Limiting to {$maxReps} employees per --max-reps-per-office option");
            }
        }

        $results = $query->get();

        // Debug info about the filtering results
        if ($this->option('debug') && $results->count() > 0) {
            if ($this->option('sequifi')) {
                $sequifiCount = $results->whereNotNull('sequifi_id')->count();
                $this->line("   🔍 Found {$sequifiCount} reps with sequifi_id out of {$results->count()} total");
            } elseif ($this->option('all')) {
                $fieldRepsCount = $results->where('type', 2)->count();
                $this->line("   🔍 Found {$fieldRepsCount} field reps (type=2) out of {$results->count()} total");
            }
        }

        return $results;
    }

    /**
     * Check if we've hit the rate limit and wait if necessary
     */
    protected function checkRateLimit()
    {
        // Check daily limit
        if ($this->dailyRequestCount >= $this->maxDailyApiCalls) {
            $this->error("   ⚠️  Daily API request limit reached ({$this->maxDailyApiCalls}). Please try again tomorrow.");

            return false;
        }

        // Check per-minute limit
        if ($this->lastApiCallTime) {
            $timeSinceLastCall = microtime(true) - $this->lastApiCallTime;
            $minWaitTime = 60 / $this->maxApiCallsPerMinute; // Time between calls to stay under rate limit

            if ($timeSinceLastCall < $minWaitTime) {
                $sleepTime = ceil(($minWaitTime - $timeSinceLastCall) * 1000000);
                usleep($sleepTime);
            }
        }

        $this->lastApiCallTime = microtime(true);
        $this->dailyRequestCount++;

        return true;
    }

    /**
     * Check if subscription data has changed by comparing key fields
     */
    protected function hasSubscriptionDataChanged($existingData, $newData)
    {
        // Key fields to compare for changes - exclude tracking fields like last_modified
        $compareFields = [
            // Core Status Fields
            'active',
            'active_text',
            'customer_id',
            'sold_by',
            'added_by',

            // Service Configuration
            'frequency',
            'billing_frequency',
            'agreement_length',
            'service_type',
            'template_type',
            'duration',
            'on_hold',
            'annual_recurring_services',

            // Financial Fields
            'initial_quote',
            'initial_service_total',
            'recurring_charge',
            'contract_value',
            'billing_terms_days',
            'autopay_payment_profile_id',

            // Dates
            'contract_added',
            'initial_billing_date',
            'next_billing_date',
            'next_service',
            'last_completed',
            'next_appointment_due_date',
            'renewal_date',
            'date_added',
            'date_updated_fr',

            // Status and Source
            'initial_status',
            'initial_status_text',
            'source',

            // Appointments
            'initial_appointment_id',
            'appointment_ids',
            'completed_appointment_ids',

            // Raw Data
            'raw_data',
            'subscription_data',
        ];

        // If existing data is empty, consider it changed
        if (empty($existingData)) {
            return true;
        }

        // Compare each field
        foreach ($compareFields as $field) {
            // Handle special case for completed_appointment_ids and appointment_ids which are stored as JSON
            if (in_array($field, ['completed_appointment_ids', 'appointment_ids'])) {
                $existingIds = json_decode($existingData->$field ?? '[]', true);
                $newIds = json_decode($newData[$field] ?? '[]', true);
                if ($existingIds != $newIds) {
                    if ($this->option('debug')) {
                        $this->line("   📝 Change detected in {$field}");
                        $this->line('   Old value: '.json_encode($existingIds));
                        $this->line('   New value: '.json_encode($newIds));
                    }

                    return true;
                }

                continue;
            }

            // Handle special case for raw data fields
            if (in_array($field, ['raw_data', 'subscription_data'])) {
                $existingJson = json_decode($existingData->$field ?? '{}', true);
                $newJson = json_decode($newData[$field] ?? '{}', true);
                if ($existingJson != $newJson) {
                    if ($this->option('debug')) {
                        $this->line("   📝 Change detected in {$field}");
                    }

                    return true;
                }

                continue;
            }

            // Handle date fields
            if (in_array($field, [
                'next_service',
                'last_completed',
                'next_appointment_due_date',
                'date_updated_fr',
                'initial_billing_date',
                'next_billing_date',
                'renewal_date',
                'date_added',
            ])) {
                $existingDate = $existingData->$field ? Carbon::parse($existingData->$field) : null;
                $newDate = isset($newData[$field]) ? Carbon::parse($newData[$field]) : null;

                // Compare dates, accounting for null values
                if (($existingDate === null && $newDate !== null) ||
                    ($existingDate !== null && $newDate === null) ||
                    ($existingDate && $newDate && ! $existingDate->equalTo($newDate))) {
                    if ($this->option('debug')) {
                        $this->line("   📝 Change detected in {$field}");
                        $this->line('   Old value: '.($existingDate ? $existingDate->toDateTimeString() : 'null'));
                        $this->line('   New value: '.($newDate ? $newDate->toDateTimeString() : 'null'));
                    }

                    return true;
                }

                continue;
            }

            // Special handling for contract_added - compare only dates, ignore time
            if ($field === 'contract_added') {
                $existingDate = $existingData->$field ? Carbon::parse($existingData->$field)->startOfDay() : null;
                $newDate = isset($newData[$field]) ? Carbon::parse($newData[$field])->startOfDay() : null;

                // Compare dates, accounting for null values
                if (($existingDate === null && $newDate !== null) ||
                    ($existingDate !== null && $newDate === null) ||
                    ($existingDate && $newDate && ! $existingDate->equalTo($newDate))) {
                    if ($this->option('debug')) {
                        $this->line("   📝 Change detected in {$field}");
                        $this->line('   Old value: '.($existingDate ? $existingDate->toDateString() : 'null'));
                        $this->line('   New value: '.($newDate ? $newDate->toDateString() : 'null'));
                    }

                    return true;
                }

                continue;
            }

            // Compare regular fields with null handling
            $existingValue = $existingData->$field ?? null;
            $newValue = $newData[$field] ?? null;

            // Convert to strings for comparison if not null
            if ($existingValue !== null) {
                $existingValue = (string) $existingValue;
            }
            if ($newValue !== null) {
                $newValue = (string) $newValue;
            }

            if ($existingValue !== $newValue) {
                if ($this->option('debug')) {
                    $this->line("   📝 Change detected in {$field}");
                    $this->line('   Old value: '.($existingValue ?? 'null'));
                    $this->line('   New value: '.($newValue ?? 'null'));
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Check if customer data has changed by comparing selected key fields only
     * Returns array with changed field groups and whether any changes were detected
     */
    protected function hasCustomerDataChanged($existingData, &$newData)
    {
        $changes = [
            'personal_info' => false,
            'address' => false,
            'status' => false,
            'financial' => false,
            'any_change' => false,
        ];

        // If no existing data, treat all fields as changed
        if (empty($existingData)) {
            $now = now()->toDateTimeString();
            $fieldChanges = [
                'personal_info' => $now,
                'address' => $now,
                'status' => $now,
                'financial' => $now,
            ];

            $newData['field_changes'] = json_encode($fieldChanges);

            return array_merge($changes, [
                'personal_info' => true,
                'address' => true,
                'status' => true,
                'financial' => true,
                'any_change' => true,
            ]);
        }

        // Get existing field changes or initialize empty array
        $fieldChanges = json_decode($existingData->field_changes ?? '{}', true) ?: [];
        $now = now()->toDateTimeString();

        // Personal info fields
        if ($this->compareFields($existingData, $newData, [
            'fname', 'lname', 'company_name', 'email', 'phone1', 'phone2',
        ])) {
            $changes['personal_info'] = true;
            $fieldChanges['personal_info'] = $now;
        }

        // Address fields
        if ($this->compareFields($existingData, $newData, [
            'address', 'city', 'state', 'zip',
        ])) {
            $changes['address'] = true;
            $fieldChanges['address'] = $now;
        }

        // Status fields
        if ($this->compareFields($existingData, $newData, [
            'status', 'status_text', 'active', 'date_added', 'date_cancelled',
        ])) {
            $changes['status'] = true;
            $fieldChanges['status'] = $now;
        }

        // Financial fields
        if ($this->compareFields($existingData, $newData, [
            'balance', 'responsible_balance', 'balance_age', 'aging_date',
            'responsible_balance_age', 'responsible_aging_date', 'a_pay',
        ])) {
            $changes['financial'] = true;
            $fieldChanges['financial'] = $now;
        }

        // Set any_change if any group changed
        $changes['any_change'] = array_reduce(array_keys($changes), function ($carry, $key) use ($changes) {
            return $carry || ($key !== 'any_change' && $changes[$key]);
        }, false);

        // Only update timestamps if there were actual changes
        if ($changes['any_change']) {
            $newData['field_changes'] = json_encode($fieldChanges);
        }

        return $changes;
    }

    /**
     * Check if appointment data has changed by comparing selected key fields only
     * Returns array with changed field groups and whether any changes were detected
     */
    protected function hasAppointmentDataChanged($existingData, &$newData)
    {
        $changes = [
            'status' => false,
            'schedule' => false,
            'identifiers' => false,
            'any_change' => false,
        ];

        // If no existing data, treat all fields as changed
        if (empty($existingData)) {
            $now = now()->toDateTimeString();
            $fieldChanges = [
                'status' => $now,
                'schedule' => $now,
                'identifiers' => $now,
            ];

            $newData['field_changes'] = json_encode($fieldChanges);
            $newData['last_modified'] = $now;

            return array_merge($changes, [
                'status' => true,
                'schedule' => true,
                'identifiers' => true,
                'any_change' => true,
            ]);
        }

        // Get existing field changes or initialize empty array
        $fieldChanges = json_decode($existingData->field_changes ?? '{}', true) ?: [];
        $now = now()->toDateTimeString();

        // Status fields
        if ($this->compareFields($existingData, $newData, ['status', 'status_text'])) {
            $changes['status'] = true;
            $fieldChanges['status'] = $now;
        }

        // Schedule fields
        if ($this->compareFields($existingData, $newData, [
            'scheduled_date', 'date_added', 'date_completed', 'date_cancelled',
        ])) {
            $changes['schedule'] = true;
            $fieldChanges['schedule'] = $now;
        }

        // Identifier fields
        if ($this->compareFields($existingData, $newData, [
            'appointment_id', 'customer_id', 'subscription_id', 'original_appointment_id',
        ])) {
            $changes['identifiers'] = true;
            $fieldChanges['identifiers'] = $now;
        }

        // Set any_change if any group changed
        $changes['any_change'] = array_reduce(array_keys($changes), function ($carry, $key) use ($changes) {
            return $carry || ($key !== 'any_change' && $changes[$key]);
        }, false);

        // Only update timestamps if there were actual changes
        if ($changes['any_change']) {
            $newData['last_modified'] = $now;
            $newData['field_changes'] = json_encode($fieldChanges);
        }

        return $changes;
    }

    /**
     * Compare a group of fields between existing and new data
     */
    protected function compareFields($existingData, $newData, $fields)
    {
        // If existing data is null, consider all fields changed
        if (empty($existingData)) {
            return true;
        }

        foreach ($fields as $field) {
            // Handle JSON fields
            if (in_array($field, ['target_pests', 'additional_techs', 'products_used'])) {
                $existingJson = json_decode($existingData->$field ?? '[]', true);
                $newJson = json_decode($newData[$field] ?? '[]', true);
                if ($existingJson != $newJson) {
                    if ($this->option('debug')) {
                        $this->line("   📝 Change detected in {$field}");
                        $this->line('   Old value: '.json_encode($existingJson));
                        $this->line('   New value: '.json_encode($newJson));
                    }

                    return true;
                }

                continue;
            }

            // Handle date fields
            if (in_array($field, [
                'scheduled_date', 'scheduled_time', 'date_completed', 'date_cancelled',
                'time_in', 'time_out', 'check_in', 'check_out',
            ])) {
                $existingDate = $existingData->$field ? Carbon::parse($existingData->$field) : null;
                $newDate = isset($newData[$field]) ? Carbon::parse($newData[$field]) : null;

                if (($existingDate === null && $newDate !== null) ||
                    ($existingDate !== null && $newDate === null) ||
                    ($existingDate && $newDate && ! $existingDate->equalTo($newDate))) {
                    if ($this->option('debug')) {
                        $this->line("   📝 Change detected in {$field}");
                        $this->line('   Old value: '.($existingDate ? $existingDate->toDateTimeString() : 'null'));
                        $this->line('   New value: '.($newDate ? $newDate->toDateTimeString() : 'null'));
                    }

                    return true;
                }

                continue;
            }

            // Compare regular fields
            $existingValue = $existingData->$field ?? null;
            $newValue = $newData[$field] ?? null;

            // Convert to strings for comparison if not null
            if ($existingValue !== null) {
                $existingValue = (string) $existingValue;
            }
            if ($newValue !== null) {
                $newValue = (string) $newValue;
            }

            if ($existingValue !== $newValue) {
                if ($this->option('debug')) {
                    $this->line("   📝 Change detected in {$field}");
                    $this->line('   Old value: '.($existingValue ?? 'null'));
                    $this->line('   New value: '.($newValue ?? 'null'));
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Safely parse a date string, returning null if parsing fails
     */
    protected function safeParseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        // Skip parsing if it's just a numeric ID
        if (is_numeric($dateString)) {
            return null;
        }

        try {
            return Carbon::parse($dateString);
        } catch (\Exception $e) {
            if ($this->option('debug')) {
                $this->line("   ⚠️  Could not parse date: {$dateString}");
            }

            return null;
        }
    }

    /**
     * Log office statistics to database
     */
    protected function logOfficeStats($stats, $office, $startDate, $endDate, $durationSeconds, $isDryRun)
    {
        try {
            // Build command parameters string
            $commandParams = $this->buildCommandParametersString($startDate, $endDate);

            // Create log entry
            FieldRoutesSyncLog::createFromStats(
                $stats,
                $office,
                $startDate,
                $endDate,
                $commandParams,
                $durationSeconds,
                $isDryRun
            );

            if ($this->option('debug')) {
                $this->line("   📝 Logged stats to database for office: {$office->description}");
            }

        } catch (\Exception $e) {
            $this->warn("   ⚠️  Failed to log stats for office {$office->description}: ".$e->getMessage());
        }
    }

    /**
     * Log office error to database
     */
    protected function logOfficeError($office, $startDate, $endDate, $errorMessage, $durationSeconds, $isDryRun)
    {
        try {
            // Build command parameters string
            $commandParams = $this->buildCommandParametersString($startDate, $endDate);

            // Create error log entry with minimal stats
            FieldRoutesSyncLog::create([
                'execution_timestamp' => now(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'command_parameters' => $commandParams,
                'office_id' => $office->id ?? null,
                'office_name' => $office->description,
                'errors' => 1,
                'duration_seconds' => $durationSeconds,
                'is_dry_run' => $isDryRun,
                'error_details' => $errorMessage,
            ]);

            if ($this->option('debug')) {
                $this->line("   📝 Logged error to database for office: {$office->description}");
            }

        } catch (\Exception $e) {
            $this->warn("   ⚠️  Failed to log error for office {$office->description}: ".$e->getMessage());
        }
    }

    /**
     * Build command parameters string for logging
     */
    protected function buildCommandParametersString($startDate, $endDate)
    {
        $params = [
            'from_date' => $startDate,
            'to_date' => $endDate,
        ];

        // Add relevant options
        $options = ['all', 'sequifi', 'save', 'office', 'employee', 'max-results', 'max-per-rep', 'debug', 'dry-run'];

        foreach ($options as $option) {
            if ($this->option($option)) {
                $value = $this->option($option);
                $params["--{$option}"] = is_bool($value) ? 'true' : $value;
            }
        }

        return json_encode($params);
    }
}
