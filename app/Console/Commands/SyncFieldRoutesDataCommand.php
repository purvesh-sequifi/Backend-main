<?php

namespace App\Console\Commands;

use App\Jobs\Sales\SaleMasterJob;
use App\Models\FieldRoutesSyncLog;
use App\Models\Integration;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncFieldRoutesDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fieldroutes:sync-data
                            {--start-date= : Start date for syncing records (YYYY-MM-DD format)}
                            {--end-date= : End date for syncing records (YYYY-MM-DD format)}
                            {--office-ids= : Comma-separated office IDs to sync (e.g., 1,7,22)}
                            {--user-ids= : Comma-separated user IDs to sync (e.g., 123,456,789)}
                            {--date-field=last_modified : Date field to filter by (date_added, last_modified, date_updated_fr)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize FieldRoutes data with legacy system. Use --start-date/--end-date for date range, --office-ids for specific offices, --user-ids for specific users, and --date-field to specify which date to filter by (default: last_modified).';

    /**
     * Last successful run timestamp key in system settings
     *
     * @var string
     */
    protected const LAST_RUN_TIMESTAMP_KEY = 'fieldroutes_sync_last_run';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = now();
        $stats = [
            'command_type' => 'sync-data',
            'raw_data_processed' => 0,
            'customer_data_processed' => 0,
            'appointment_data_processed' => 0,
            'legacy_records_saved' => 0,
            'jobs_dispatched' => 0,
            'data_source_types' => [],
            'errors' => 0,
            'subscription_ids' => [], // Track subscription IDs being processed
        ];

        try {
            $this->info('Starting FieldRoutes data synchronization...');

            // Increase memory limit for large data processing
            ini_set('memory_limit', '2048M');
            $this->info('Memory limit set to 2048M for large data processing');

            // Step 1: Get date and office parameters
            $startDate = $this->option('start-date');
            $endDate = $this->option('end-date');
            $officeIdsParam = $this->option('office-ids');
            $userIdsParam = $this->option('user-ids');
            $dateField = $this->option('date-field') ?: 'last_modified';
            $lastRunTimestamp = null;

            // Validate date parameters if provided
            if ($startDate || $endDate) {
                $dateValidation = $this->validateDateParameters($startDate, $endDate);
                if (! $dateValidation['valid']) {
                    $this->error($dateValidation['message']);

                    return 1;
                }
                $this->info("Date range specified: {$startDate} to {$endDate}");
            } else {
                $lastRunTimestamp = $this->getLastRunTimestamp();
                $this->info('Last successful run: '.($lastRunTimestamp ?? 'Never'));
            }

            // Validate and parse office IDs if provided
            $specificOfficeIds = null;
            if ($officeIdsParam) {
                $officeValidation = $this->validateOfficeIds($officeIdsParam);
                if (! $officeValidation['valid']) {
                    $this->error($officeValidation['message']);

                    return 1;
                }
                $specificOfficeIds = $officeValidation['office_ids'];
                $this->info('Office IDs specified: '.implode(', ', $specificOfficeIds));
            }

            // Validate and parse user IDs if provided
            $specificUserIds = null;
            if ($userIdsParam) {
                $userValidation = $this->validateUserIds($userIdsParam);
                if (! $userValidation['valid']) {
                    $this->error($userValidation['message']);

                    return 1;
                }
                $specificUserIds = $userValidation['user_ids'];
                $this->info('User IDs specified: '.implode(', ', $specificUserIds));
            }

            // Verify database tables exist
            $this->verifyTables();

            // Step 2: Collect and join data from FieldRoutes tables
            $this->info('Collecting data from FieldRoutes tables...');
            $data = $this->collectData($lastRunTimestamp, $startDate, $endDate, $specificOfficeIds, $dateField, $specificUserIds);
            $stats['raw_data_processed'] = count($data);
            $this->info('Found '.count($data).' records to process');

            if (empty($data)) {
                $this->info('No new data to process');

                // Only update timestamp when not using manual date range
                $currentTimestamp = now()->toDateTimeString();
                if (! $startDate && ! $endDate) {
                    $this->updateLastRunTimestamp($currentTimestamp);
                    $this->info("Synchronization completed successfully. Next sync will process records modified after: {$currentTimestamp}");
                } else {
                    $this->info("Synchronization completed successfully for date range: {$startDate} to {$endDate}");
                }

                // Log the sync run even with no data
                $this->logSyncStats($stats, $startTime, $lastRunTimestamp, $currentTimestamp);

                return 0;
            }

            // Step 3: Load mapping configuration
            $this->info('Loading mapping configuration...');
            $mappingConfig = $this->loadMappingConfig();
            if (! $mappingConfig) {
                $this->error('Failed to load mapping configuration');
                $stats['errors']++;
                $this->logSyncStats($stats, $startTime, $lastRunTimestamp, null);

                return 1;
            }
            $this->info('Mapping configuration loaded successfully');

            // Step 4: Process and transform data
            $this->info('Processing data...');
            $this->info("⏱️  Note: Processing {$stats['raw_data_processed']} records with transformations...");
            $processedData = $this->processData($data, $mappingConfig);
            $this->info('Processed '.count($processedData).' records');

            // Step 5: Save processed data
            $this->info('Saving processed data...');
            $savedResult = $this->saveProcessedData($processedData);
            $stats['legacy_records_saved'] = $savedResult['saved_count'];
            $stats['data_source_types'] = $savedResult['data_source_types'];
            $stats['jobs_dispatched'] = count($savedResult['data_source_types']);
            $stats['subscription_ids'] = $savedResult['subscription_ids']; // Track subscription IDs
            $this->info('Saved '.$savedResult['saved_count'].' records to legacy system');

            // Step 6: Update last successful run timestamp with current time (only when not using manual date range)
            $currentTimestamp = now()->toDateTimeString();
            if (! $startDate && ! $endDate) {
                $this->updateLastRunTimestamp($currentTimestamp);
                $this->info("Synchronization completed successfully. Next sync will process records modified after: {$currentTimestamp}");
            } else {
                $this->info("Synchronization completed successfully for date range: {$startDate} to {$endDate}");
            }

            // Log sync statistics
            $this->logSyncStats($stats, $startTime, $lastRunTimestamp, $currentTimestamp);

            return 0;
        } catch (Exception $e) {
            $this->error('Error during synchronization: '.$e->getMessage());
            $stats['errors']++;
            Log::error('FieldRoutes Sync Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Log failed sync stats
            $this->logSyncStats($stats, $startTime, $lastRunTimestamp, null);

            return 1;
        }
    }

    /**
     * Verify required tables exist
     *
     *
     * @throws Exception
     */
    protected function verifyTables(): void
    {
        $requiredTables = [
            'FieldRoutes_Raw_Data',
            'FieldRoutes_Customer_Data',
            'FieldRoutes_Appointment_Data',
            'legacy_api_raw_data_histories',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new Exception("Required table '{$table}' does not exist");
            }
            $this->info("Verified table exists: {$table}");

            // Check if table has any records
            $count = DB::table($table)->count();
            $this->info("Table {$table} has {$count} records");
        }
    }

    /**
     * Get the timestamp of the last successful run
     */
    protected function getLastRunTimestamp(): ?string
    {
        try {
            return SystemSetting::getValue(self::LAST_RUN_TIMESTAMP_KEY);
        } catch (Exception $e) {
            Log::error('Error getting last run timestamp', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update the last successful run timestamp
     */
    protected function updateLastRunTimestamp($timestamp): void
    {
        try {
            SystemSetting::setValue(
                self::LAST_RUN_TIMESTAMP_KEY,
                $timestamp,
                'fieldroutes',
                'Last successful run timestamp for FieldRoutes data synchronization'
            );
        } catch (Exception $e) {
            Log::error('Error updating last run timestamp', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get list of active office IDs from integrations table
     */
    protected function getActiveOfficeIds(): array
    {
        try {
            // Get all integrations - don't filter by value first, let's see all of them
            $allIntegrations = Integration::select('id', 'description', 'status', 'value')
                ->get();

            $this->info('🔍 Found '.count($allIntegrations).' total integrations');

            $activeOfficeIds = [];
            $inactiveOffices = [];
            $activeOffices = [];
            $invalidJson = [];

            $this->info('🔍 Integration Status Summary:');

            foreach ($allIntegrations as $integration) {
                $this->info("Processing integration ID: {$integration->id}, Status: {$integration->status}, Raw Status: ".var_export($integration->getRawOriginal('status'), true));

                // Check if value exists and is not empty
                if (empty($integration->value)) {
                    $this->warn("   ⚠️  Integration {$integration->id} ({$integration->description}): Empty value field");

                    continue;
                }

                try {
                    // Use the same decryption approach as other FieldRoutes commands
                    $decryptedValue = null;
                    $method = '';

                    // Try Laravel's built-in decryption first
                    try {
                        $decrypted = decrypt($integration->value);
                        $decryptedValue = json_decode($decrypted, true);
                        $method = 'Laravel decrypt + JSON';
                    } catch (\Exception $decryptException) {
                        // If Laravel's decryption fails, try openssl_decrypt with env variables
                        try {
                            $decrypted = openssl_decrypt(
                                $integration->value,
                                config('app.encryption_cipher_algo'),
                                config('app.encryption_key'),
                                0,
                                config('app.encryption_iv')
                            );
                            $decryptedValue = json_decode($decrypted, true);
                            $method = 'OpenSSL decrypt + JSON';
                        } catch (\Exception $opensslException) {
                            // Both methods failed
                            $this->warn("   ⚠️  Integration {$integration->id} ({$integration->description}): Both Laravel and OpenSSL decryption failed");
                            $invalidJson[] = $integration->id;

                            continue;
                        }
                    }

                    if (! $decryptedValue || json_last_error() !== JSON_ERROR_NONE) {
                        $this->warn("   ⚠️  Integration {$integration->id} ({$integration->description}): Invalid JSON after decryption");
                        $invalidJson[] = $integration->id;

                        continue;
                    }

                    $this->info("   🔍 Integration {$integration->id}: Successfully decoded using {$method}");

                    if (! isset($decryptedValue['office_id'])) {
                        $this->warn("   ⚠️  Integration {$integration->id} ({$integration->description}): Missing office_id in JSON");

                        continue;
                    }

                    $officeId = $decryptedValue['office_id'];
                    $officeName = $decryptedValue['office'] ?? $integration->description ?? 'Unknown';

                    // Use strict comparison and check both 1 and true for boolean cast
                    if ($integration->status == 1 || $integration->status === true) {
                        $activeOfficeIds[] = $officeId;
                        $activeOffices[] = [
                            'office_id' => $officeId,
                            'office_name' => $officeName,
                        ];
                        $this->info("   ✅ {$officeName} (office_id: {$officeId}) - Status: {$integration->status}");
                    } else {
                        $inactiveOffices[] = [
                            'office_id' => $officeId,
                            'office_name' => $officeName,
                        ];
                        $this->info("   ❌ {$officeName} (office_id: {$officeId}) (SKIPPED) - Status: {$integration->status}");
                    }
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    $this->warn("   ⚠️  Integration {$integration->id} ({$integration->description}): Decryption failed - ".$e->getMessage());
                    $invalidJson[] = $integration->id;
                } catch (Exception $e) {
                    $this->warn("   ⚠️  Integration {$integration->id} ({$integration->description}): Error processing - ".$e->getMessage());
                    $invalidJson[] = $integration->id;
                }
            }

            $this->info('✅ Active integrations (status=1): '.count($activeOffices));
            $this->info('❌ Inactive integrations (status=0): '.count($inactiveOffices));
            $this->info('⚠️  Invalid JSON integrations: '.count($invalidJson));
            $this->info('📊 Will process data from '.count($activeOfficeIds).' active office(s) only');

            return $activeOfficeIds;
        } catch (Exception $e) {
            $this->error('Error fetching active integrations: '.$e->getMessage());
            Log::error('Error fetching active integrations', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Collect and join data from FieldRoutes tables
     */
    protected function collectData(?string $lastRunTimestamp, ?string $startDate = null, ?string $endDate = null, ?array $specificOfficeIds = null, string $dateField = 'last_modified', ?array $specificUserIds = null): array
    {
        try {
            // Get office IDs to use for filtering
            if ($specificOfficeIds) {
                // Use specific office IDs provided by user
                $officeIdsToUse = $specificOfficeIds;
                $this->info('Using specified office IDs: '.implode(', ', $officeIdsToUse));

                // Validate that specified office IDs exist in active integrations
                $activeOfficeIds = $this->getActiveOfficeIds();
                $invalidOfficeIds = array_diff($officeIdsToUse, $activeOfficeIds);
                if (! empty($invalidOfficeIds)) {
                    $this->warn('Warning: Some specified office IDs are not in active integrations: '.implode(', ', $invalidOfficeIds));
                    $this->info('Available active office IDs: '.implode(', ', $activeOfficeIds));
                }
            } else {
                // Use all active office IDs from integrations table
                $officeIdsToUse = $this->getActiveOfficeIds();

                if (empty($officeIdsToUse)) {
                    $this->warn('No active integrations found. No data will be processed.');

                    return [];
                }

                $this->info('Active office IDs from integrations: '.implode(', ', $officeIdsToUse));
            }

            // Define only the columns we need
            $rawColumns = [
                'raw.subscription_id as subscriptionID',
                'raw.customer_id as customerID',
                'raw.initial_appointment_id as initialAppointmentID',
                'raw.office_id as officeId',
                'raw.office_name',
                'raw.sold_by as soldBy',
                'raw.sold_by_2 as soldBy2',
                'raw.active',
                'raw.active_text',
                'raw.frequency',
                'raw.agreement_length as agreementLength',
                'raw.service_type as serviceType',
                'raw.initial_service_total as initialServiceTotal',
                'raw.recurring_charge as recurringCharge',
                'raw.contract_value as contractValue',
                'raw.initial_status_text as initialStatusText',
                'raw.completed_appointment_ids as completedAppointmentIDs',
                'raw.date_cancelled as dateCancelled',
                'raw.date_added as dateAdded',
                'raw.last_modified',
            ];

            $customerColumns = [
                'customer.fname',
                'customer.lname',
                'customer.address',
                'customer.city',
                'customer.state',
                'customer.zip',
                'customer.email',
                'customer.phone1',
                'customer.most_recent_credit_card_last_four as mostRecentCreditCardLastFour',
                'customer.a_pay as aPay',
                'customer.balance_age as balanceAge',
                'customer.aging_date as agingDate',
                'customer.responsible_balance as responsibleBalance',
                'customer.responsible_balance_age as responsibleBalanceAge',
                'customer.responsible_aging_date as responsibleAgingDate',
            ];

            $appointmentColumns = [
                'appointment.scheduled_date',
                'appointment.status_text',
                'appointment.date_completed',
                'appointment.date_cancelled',
            ];

            $excludedServiceIds = [
                637, 638, 641, 930, 1237, 453, 690, 1407, 278, 390, 1504, 144, 147, 1260, 644, 308, 650, 179, 391, 180, 392, 293, 1048, 1049, 326, 279, 568, 847, 181, 375, 765, 249, 405,
                1074, 1075, 728, 774, 688, 445, 727, 639, 729, 542, 556, 675, 264, 406, 463, 376, 1033, 886, 82, 404, 889, 781, 1236, 773, 762, 735, 734, 1188, 1045, 572, 309, 699, 915,
                640, 628, 397, 1288, 377, 1296, 645, 898, 1191, 756, 1247, 758, 838, 826, 829, 1159, 182, 340, 768, 1026, 1259, 772, 782, 657, 685, 744, 460, 786, 767, 546, 755, 540, 586,
                789, 793, 749, 459, 902, 642, 643, 646, 730, 731, 754, 169, 428, 541, 635, 674, 702, 441, 814, 817, 252, 1078, 451, 658, 1405, 468, 469, 454, 766, 1193, 302, 684, 740,
                1184, 455, 771, 928, 715, 450, 667, 3, 12, 780, 1367, 1370, 1377, 1366, 1369, 1376, 1368, 1374, 1373, 1371, 1372, 1378, 1375, 1379, 1155, 1156, 1040, 940, 1020, 398, 693,
                933, 925, 431, 432, 175, 574, 701, 378, 920, 174, 759, 605, 671, 903, 649, 1360, 711, 1079, 1088, 165, 592, 677, 1076, 547, 393, 770, 787, 575, 636, 655, 656, 941, 1081,
                1167, 1409, 919, 1154, 732, 725, 1206, 764, 881, 745, 914, 1261, 310, 407, 1047, 1046, 394, 769, 647, 660, 1365,
            ];

            $this->info('Building optimized query');

            $query = DB::table('FieldRoutes_Raw_Data as raw')
                ->select(array_merge($rawColumns, $customerColumns, $appointmentColumns))
                ->leftJoin(DB::raw('(SELECT DISTINCT customer_id, fname, lname, address, city, state, zip, email, phone1, most_recent_credit_card_last_four, a_pay, balance_age, aging_date, responsible_balance, responsible_balance_age, responsible_aging_date FROM FieldRoutes_Customer_Data) as customer'), 'raw.customer_id', '=', 'customer.customer_id')
                ->leftJoin(DB::raw('(SELECT DISTINCT appointment_id, scheduled_date, status_text, date_completed, date_cancelled FROM FieldRoutes_Appointment_Data) as appointment'), 'raw.initial_appointment_id', '=', 'appointment.appointment_id')
                ->whereIn('raw.office_id', $officeIdsToUse); // Filter by specified or active integration office IDs

            $domain = config('app.domain_name') ?: '';
            if ($domain == 'whiteknight') {
                $this->info('Filtering records for whiteknight domain');
                $query->whereNotIn('raw.service_id', $excludedServiceIds);
            }

            if ($specificOfficeIds) {
                $this->info('Filtering records from specified office IDs: '.implode(', ', $officeIdsToUse));
            } else {
                $this->info('Filtering records only from active integrations with status=1 using office_id');
            }

            // Add user ID filtering if specified
            if ($specificUserIds) {
                $this->info('🎯 Applying user ID filtering for users: '.implode(', ', $specificUserIds));

                // Get FieldRoutes employee IDs for the specified user IDs from FrEmployeeData
                // Note: users.employee_id contains Sequifi employee IDs, NOT FieldRoutes employee IDs
                $employeeIds = \App\Models\FrEmployeeData::whereIn('sequifi_id', $specificUserIds)
                    ->pluck('employee_id')
                    ->toArray();

                // Use only the FieldRoutes employee IDs from fr_employee_data
                $allEmployeeIds = $employeeIds;

                if (! empty($allEmployeeIds)) {
                    $query->where(function ($q) use ($allEmployeeIds) {
                        $q->whereIn('raw.sold_by', $allEmployeeIds)
                            ->orWhereIn('raw.sold_by_2', $allEmployeeIds);
                    });
                    $this->info('✅ Found '.count($allEmployeeIds).' employee IDs for specified users: '.implode(', ', $allEmployeeIds));
                } else {
                    $this->warn('⚠️  No employee IDs found for specified user IDs. No records will be returned.');

                    return [];
                }
            }

            // Validate and map date field
            $validDateFields = ['date_added', 'last_modified', 'date_updated_fr'];

            // Handle blank/empty date field - default to last_modified
            if (empty($dateField) || trim($dateField) === '') {
                $dateField = 'last_modified';
                $this->info('No date field specified, using default: last_modified');
            } elseif (! in_array($dateField, $validDateFields)) {
                $this->warn("Invalid date field '{$dateField}'. Using 'last_modified' as default.");
                $dateField = 'last_modified';
            }

            // Apply date filtering based on parameters
            if ($startDate && $endDate) {
                // Use date range filtering when start/end dates are provided
                $startDateTime = $startDate.' 00:00:00';
                $endDateTime = $endDate.' 23:59:59';
                $query->whereBetween("raw.{$dateField}", [$startDateTime, $endDateTime]);
                $this->info("Filtering records by {$dateField} between: {$startDateTime} and {$endDateTime}");
            } elseif ($lastRunTimestamp) {
                // Use last run timestamp when no date range is provided (always use last_modified for incremental sync)
                $query->where('raw.last_modified', '>', $lastRunTimestamp);
                $this->info("Filtering records by last_modified after: {$lastRunTimestamp}");
            } else {
                $this->info('No date filters - processing all records from active offices');
            }

            // Process in batches of 500
            $batchSize = config('fieldroutes.sync.batch_size', 250);
            $this->info("Processing in batches of {$batchSize} records");

            $allData = [];
            $offset = 0;
            $totalProcessed = 0;

            do {
                $batch = $query->skip($offset)->take($batchSize)->get();
                $batchCount = $batch->count();
                $totalProcessed += $batchCount;

                if ($batchCount > 0) {
                    $allData = array_merge($allData, $batch->toArray());
                    $this->info("Processed batch: {$batchCount} records (Total: {$totalProcessed})");
                }

                $offset += $batchSize;
            } while ($batchCount > 0);

            $this->info('Total records collected: '.count($allData));

            if (count($allData) > 0) {
                $this->info('Sample record keys: '.implode(', ', array_keys((array) $allData[0])));
            }

            return $allData;

        } catch (Exception $e) {
            $this->error('Error in collectData: '.$e->getMessage());
            Log::error('FieldRoutes Data Collection Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'last_run' => $lastRunTimestamp,
            ]);
            throw $e;
        }
    }

    /**
     * Load the field mapping configuration
     */
    protected function loadMappingConfig(): ?array
    {
        $configPath = config_path('field-mappings/subscription.json');

        if (! file_exists($configPath)) {
            Log::error('Mapping configuration file not found', ['path' => $configPath]);

            return null;
        }

        try {
            $config = json_decode(file_get_contents($configPath), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in mapping configuration');
            }

            return $config;
        } catch (Exception $e) {
            Log::error('Error loading mapping configuration', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process and transform the collected data
     */
    protected function processData(array $data, array $mappingConfig): array
    {
        $transformationService = new \App\Services\FieldRoutes\DataTransformationService;

        // Add progress callback for large datasets
        $totalRecords = count($data);
        $chunkSize = 500; // Process in chunks of 500 records

        if ($totalRecords > 100) {
            $this->info("📊 Large dataset detected ({$totalRecords} records)");
            $this->info('🔍 Main bottlenecks: Sales rep lookups, field transformations, and validation');
            $this->info("🚀 Processing in chunks of {$chunkSize} records for better performance...");

            // Implement employee data pre-loading optimization
            $this->info('🚀 Implementing performance optimization: Pre-loading employee data...');

            // Collect unique employee_id + office_id combinations
            $employeeOfficePairs = collect($data)
                ->filter(function($record) {
                    return !empty($record->soldBy) && !empty($record->officeId);
                })
                ->map(function($record) {
                    return [
                        'employee_id' => $record->soldBy,
                        'office_id' => $record->officeId
                    ];
                })
                ->unique(function($pair) {
                    return $pair['employee_id'] . '_' . $pair['office_id'];
                })
                ->values();

            $this->info('📋 Found '.$employeeOfficePairs->count().' unique employee-office combinations to pre-load');

            if ($employeeOfficePairs->isNotEmpty()) {
                // Build a query that matches any of the employee_id + office_id pairs
                $employeeData = \App\Models\FrEmployeeData::where(function($query) use ($employeeOfficePairs) {
                    foreach ($employeeOfficePairs as $pair) {
                        $query->orWhere(function($q) use ($pair) {
                            $q->where('employee_id', $pair['employee_id'])
                              ->where('office_id', $pair['office_id']);
                        });
                    }
                })
                ->select('employee_id', 'office_id', 'sequifi_id', 'email', 'fname', 'lname')
                ->get()
                ->mapWithKeys(function($item) {
                    // Create composite key: employee_id_office_id
                    $key = $item->employee_id . '_' . $item->office_id;
                    return [$key => $item->toArray()];
                });

                $this->info('✅ Pre-loaded '.$employeeData->count().' employee-office records');
                $this->info('🏎️  Performance: Using composite key (employee_id + office_id) for accurate mapping');

                // Pass the pre-loaded data to the transformation service
                $transformationService->setEmployeeCache($employeeData->toArray());
            } else {
                $this->info('ℹ️  No sales reps to pre-load');
            }
        }

        // Process data in chunks to prevent memory issues and provide progress feedback
        $allTransformedData = [];
        $dataChunks = array_chunk($data, $chunkSize);
        $totalChunks = count($dataChunks);

        $this->info("🔄 Processing {$totalRecords} records in {$totalChunks} chunks...");

        foreach ($dataChunks as $chunkIndex => $chunk) {
            $chunkNumber = $chunkIndex + 1;
            $chunkRecordCount = count($chunk);

            $this->info("📦 Processing chunk {$chunkNumber}/{$totalChunks} ({$chunkRecordCount} records)...");

            try {
                $chunkTransformed = $transformationService->transformData($chunk, $mappingConfig);

                // Use array_push with splat operator for better performance than array_merge
                if (! empty($chunkTransformed)) {
                    array_push($allTransformedData, ...$chunkTransformed);
                }

                $this->info("✅ Chunk {$chunkNumber} completed: {$chunkRecordCount} records transformed");

                // Clear memory after each chunk
                unset($chunk, $chunkTransformed);

                // Force garbage collection to free memory
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

            } catch (Exception $e) {
                $this->error("❌ Error processing chunk {$chunkNumber}: ".$e->getMessage());
                Log::error('Chunk processing error', [
                    'chunk' => $chunkNumber,
                    'total_chunks' => $totalChunks,
                    'chunk_size' => $chunkRecordCount,
                    'error' => $e->getMessage(),
                ]);

                // Continue with next chunk instead of failing completely
                continue;
            }
        }

        $this->info('🎉 All chunks processed successfully! Total transformed: '.count($allTransformedData).' records');

        return $allTransformedData;
    }

    /**
     * Save the processed data to the legacy system
     *
     * @return array Results with saved count and data source types
     */
    protected function saveProcessedData(array $processedData): array
    {
        $savedCount = 0;
        $errors = [];
        $uniqueDataSourceTypes = []; // Track unique data source types
        $subscriptionIds = []; // Track subscription IDs being saved
        $totalRecords = count($processedData);

        $batchSize = 500; // Bulk insert batch size
        $recordsToInsert = []; // Batch array for bulk insertion
        $currentBatchSignature = null; // Sorted keys signature for current batch
        $currentBatchKeys = []; // Keys of current batch for logging

        // Retrieve actual table columns to strictly filter insert data
        $tableColumns = Schema::getColumnListing('legacy_api_raw_data_histories');
        $tableColumnsFlip = array_flip($tableColumns);

        $this->info("📊 Processing {$totalRecords} records for bulk insertion (batch size: {$batchSize})...");

        foreach ($processedData as $index => $record) {
            try {
                // Removed excessive individual record logging for performance

                // Validate required fields
                $missingFields = $this->validateRequiredFields($record);
                if (! empty($missingFields)) {
                    $this->warn('Missing required fields: '.implode(', ', $missingFields));

                    continue;
                }

                // Convert any array values to strings and remove internal flags
                $recordToSave = [];
                foreach ($record as $key => $value) {
                    // Skip internal processing flags
                    if (strpos($key, '_should_') === 0) {
                        continue;
                    }

                    if (is_array($value)) {
                        $recordToSave[$key] = implode(' ', $value);
                    } else {
                        // Additional validation for date fields to prevent invalid dates
                        if (in_array($key, ['date_cancelled', 'customer_signoff', 'm1_date', 'initial_service_date']) && ! is_null($value)) {
                            // Check if it's a Carbon instance or valid date string
                            if ($value instanceof \Carbon\Carbon) {
                                if ($value->year < 1000) {
                                    $this->warn("Invalid date detected for {$key}, setting to null. Record: {$record['pid']}");
                                    $recordToSave[$key] = null;
                                } else {
                                    $recordToSave[$key] = $value->format('Y-m-d H:i:s');
                                }
                            } elseif (is_string($value) && ! empty($value)) {
                                try {
                                    $carbonDate = \Carbon\Carbon::parse($value);
                                    if ($carbonDate->year < 1000) {
                                        $this->warn("Invalid date string detected for {$key}, setting to null. Record: {$record['pid']}, Value: {$value}");
                                        $recordToSave[$key] = null;
                                    } else {
                                        $recordToSave[$key] = $value;
                                    }
                                } catch (\Exception $e) {
                                    $this->warn("Failed to parse date for {$key}, setting to null. Record: {$record['pid']}, Value: {$value}");
                                    $recordToSave[$key] = null;
                                }
                            } else {
                                $recordToSave[$key] = $value;
                            }
                        } else {
                            $recordToSave[$key] = $value;
                        }
                    }
                }

                // Get the office name from raw data
                $rawData = DB::table('FieldRoutes_Raw_Data')
                    ->where('subscription_id', $record['pid'])
                    ->first();

                if (! $rawData) {
                    throw new Exception('No raw data found for subscription ID: '.$record['pid']);
                }

                // Set data_source_type based on domain or office name
                $domain = config('app.domain_name') ?: '';
                $dataSourceType = $domain == 'momentum' ? 'WhiteKnight' : 'FR_'.$rawData->office_name;
                $recordToSave['data_source_type'] = $dataSourceType;

                // Extract m1_date and m2_date from trigger_date JSON if it exists
                if (isset($recordToSave['trigger_date']) && ! empty($recordToSave['trigger_date'])) {
                    try {
                        $triggerData = json_decode($recordToSave['trigger_date'], true);
                        if (is_array($triggerData) && ! empty($triggerData)) {
                            // Extract the first date (m1) and second date (m2) from trigger_date array
                            $firstTrigger = $triggerData[0] ?? null;
                            $secondTrigger = $triggerData[1] ?? null;
                            if ($firstTrigger && isset($firstTrigger['date']) && ! empty($firstTrigger['date'])) {
                                $recordToSave['m1_date'] = $firstTrigger['date'];
                            }
                            if ($secondTrigger && isset($secondTrigger['date']) && ! empty($secondTrigger['date'])) {
                                $recordToSave['m2_date'] = $secondTrigger['date'];
                            }
                        }
                    } catch (Exception $e) {
                        Log::warning('Failed to extract milestone dates from trigger_date', [
                            'pid' => $record['pid'],
                            'trigger_date' => $recordToSave['trigger_date'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Track unique data source type
                if (! in_array($dataSourceType, $uniqueDataSourceTypes)) {
                    $uniqueDataSourceTypes[] = $dataSourceType;
                }

                // Domains with balance age logic default threshold is 45 days
                $domainsWithBalanceAgeLogic = ['whiteknight', 'evomarketing', 'momentum', 'homeguard', 'primepestandlawn','solvepestorlando','solvepestraleigh'];

                if (in_array($domain, $domainsWithBalanceAgeLogic)) {
                    $balanceAge = intval($recordToSave['balance_age'] ?? 0);
                    $cancellationDate = $recordToSave['date_cancelled'] ?? null;

                    $threshold = match ($domain) {
                        'whiteknight' => 60,
                        'momentum' => 45,
                        'evomarketing' => 30,
                        'homeguard' => 90,
                        'primepestandlawn' => 30,
                        'solvepestorlando' => 45,
                        'solvepestraleigh' => 45,
                        default => 45,
                    };

                    if (empty($cancellationDate) && $balanceAge >= $threshold) {
                        $recordToSave['date_cancelled'] = Carbon::now()->format('Y-m-d');
                    }
                }

                // Add timestamps for bulk insert
                $recordToSave['created_at'] = now();
                $recordToSave['updated_at'] = now();

                // Filter out any keys not present in the table to avoid unknown columns
                $recordToSave = array_intersect_key($recordToSave, $tableColumnsFlip);

                // Compute the sorted-keys signature for consistent batching
                $keys = array_keys($recordToSave);
                sort($keys);
                $signature = implode('|', $keys);

                // If this record's keys differ from current batch, flush current batch first
                $shouldFlushForSignatureChange = $currentBatchSignature !== null && $signature !== $currentBatchSignature && ! empty($recordsToInsert);
                $shouldFlushForBatchSize = count($recordsToInsert) >= $batchSize;
                $isLastRecord = ($index === $totalRecords - 1);

                // If we need to flush before adding this record
                if ($shouldFlushForSignatureChange || $shouldFlushForBatchSize) {
                    try {
                        DB::table('legacy_api_raw_data_histories')->insert($recordsToInsert);
                        $savedCount += count($recordsToInsert);
                        $this->info('Processed batch: '.count($recordsToInsert)." records (Total: {$savedCount})");
                    } catch (Exception $e) {
                        $this->error('Error during bulk insert: '.$e->getMessage());
                        // Log batch keys for diagnostics
                        Log::error('Failed batch key signature', [
                            'signature' => $currentBatchSignature,
                            'keys' => $currentBatchKeys,
                            'batch_size' => count($recordsToInsert),
                        ]);
                        // Fallback: Try inserting records individually with context
                        $this->warn('Attempting individual insertion as fallback for failed batch...');
                        foreach ($recordsToInsert as $idx => $failedRecord) {
                            try {
                                \App\Models\LegacyApiRawDataHistory::create($failedRecord);
                                $savedCount++;
                            } catch (Exception $individualError) {
                                $errors[] = [
                                    'error' => $individualError->getMessage(),
                                    'record' => $failedRecord,
                                    'record_keys' => array_keys($failedRecord),
                                ];
                                $this->error('Error saving individual record: '.$individualError->getMessage());
                            }
                        }
                    }
                    // Reset batch after flush
                    $recordsToInsert = [];
                    $currentBatchSignature = null;
                    $currentBatchKeys = [];
                }

                // Start a new batch signature if needed
                if ($currentBatchSignature === null) {
                    $currentBatchSignature = $signature;
                    $currentBatchKeys = $keys;
                }

                // Add to batch array (now guaranteed to share the same key signature)
                $recordsToInsert[] = $recordToSave;
                // Track the subscription ID
                $subscriptionIds[] = $record['pid'];

                // If this is the last record, flush remaining batch
                if ($isLastRecord && ! empty($recordsToInsert)) {
                    try {
                        DB::table('legacy_api_raw_data_histories')->insert($recordsToInsert);
                        $savedCount += count($recordsToInsert);
                        $this->info('Processed batch: '.count($recordsToInsert)." records (Total: {$savedCount})");
                    } catch (Exception $e) {
                        $this->error('Error during bulk insert: '.$e->getMessage());
                        Log::error('Failed final batch key signature', [
                            'signature' => $currentBatchSignature,
                            'keys' => $currentBatchKeys,
                            'batch_size' => count($recordsToInsert),
                        ]);
                        $this->warn('Attempting individual insertion as fallback for final batch...');
                        foreach ($recordsToInsert as $failedRecord) {
                            try {
                                \App\Models\LegacyApiRawDataHistory::create($failedRecord);
                                $savedCount++;
                            } catch (Exception $individualError) {
                                $errors[] = [
                                    'error' => $individualError->getMessage(),
                                    'record' => $failedRecord,
                                    'record_keys' => array_keys($failedRecord),
                                ];
                                $this->error('Error saving individual record: '.$individualError->getMessage());
                            }
                        }
                    }
                    $recordsToInsert = [];
                    $currentBatchSignature = null;
                    $currentBatchKeys = [];
                }
            } catch (Exception $e) {
                $errors[] = [
                    'error' => $e->getMessage(),
                    'record' => $record,
                ];
                $this->error('Error saving record: '.$e->getMessage());
            }
        }

        if (! empty($errors)) {
            $this->warn('Encountered '.count($errors).' errors while saving');
            Log::error('Errors saving records', ['errors' => $errors]);
        }

        $this->info("Saved {$savedCount} records to legacy system");

        // Post-process records to handle observer logic for bulk-inserted records
        $this->handlePostInsertObserverLogic($subscriptionIds);

        // Dispatch SaleMasterJob for each unique data source type
        if (! empty($uniqueDataSourceTypes)) {
            $this->info('Dispatching SaleMasterJob for data source types:');
            foreach ($uniqueDataSourceTypes as $dataSourceType) {
                $this->info("  - {$dataSourceType}");
                dispatch((new \App\Jobs\Sales\SaleMasterJob($dataSourceType, 1000, 'sales-process'))->onQueue('sales-process'));
            }
            $this->info('Successfully dispatched '.count($uniqueDataSourceTypes).' SaleMasterJobs');
        }

        return [
            'saved_count' => $savedCount,
            'data_source_types' => $uniqueDataSourceTypes,
            'subscription_ids' => $subscriptionIds,
            'errors' => count($errors),
        ];
    }

    /**
     * Handle observer logic for bulk-inserted records
     * Since bulk insert bypasses Eloquent observers, we manually process records that need special handling
     */
    private function handlePostInsertObserverLogic(array $subscriptionIds): void
    {
        if (empty($subscriptionIds)) {
            return;
        }

        $this->info('🔄 Post-processing '.count($subscriptionIds).' records for observer logic...');

        // Find records that need to be moved to excluded table
        // 1. Records with import_to_sales = 2 (newly failed)
        // 2. Records with import_to_sales = 0 AND "Invalid sales rep" reason (previously stuck)
        $failedRecords = \App\Models\LegacyApiRawDataHistory::whereIn('pid', $subscriptionIds)
            ->where(function ($query) {
                $query->where('import_to_sales', 2)
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('import_to_sales', 0)
                            ->where('import_status_reason', 'Invalid sales rep');
                    });
            })
            ->get();

        // Also process ALL existing stuck records with import_to_sales = 0 and "Invalid sales rep"
        $this->processExistingStuckRecords();

        if ($failedRecords->isEmpty()) {
            $this->info('✅ No failed records (import_to_sales=2) found to move to excluded table');

            return;
        }

        $this->info('📊 Found '.$failedRecords->count().' failed records to move to excluded table');

        $moved = 0;
        $observer = new \App\Observers\LegacyApiRawDataHistoryObserver;

        foreach ($failedRecords as $record) {
            try {
                // Use reflection to access the private moveToFilterTable method
                $reflection = new \ReflectionClass($observer);
                $method = $reflection->getMethod('moveToFilterTable');
                $method->setAccessible(true);
                $method->invoke($observer, $record);

                // Delete the record from legacy table after moving to excluded
                $record->delete();
                $moved++;

                $this->info("✅ Moved record PID {$record->pid} to excluded table");
            } catch (\Exception $e) {
                $this->error("❌ Failed to move record PID {$record->pid}: ".$e->getMessage());
                Log::error('Failed to move record to excluded table', [
                    'pid' => $record->pid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("🎉 Successfully moved {$moved} failed records to sale_masters_excluded table");
    }

    /**
     * Process existing stuck records with import_to_sales = 0 and "Invalid sales rep"
     * These are records that were processed before our fix and are stuck in pending status
     */
    private function processExistingStuckRecords(): void
    {
        $this->info('🔍 Checking for existing stuck records with import_to_sales = 0...');

        // Find all records with import_to_sales = 0 AND "Invalid sales rep" reason
        $stuckRecords = \App\Models\LegacyApiRawDataHistory::where('import_to_sales', 0)
            ->where('import_status_reason', 'Invalid sales rep')
            ->get();

        if ($stuckRecords->isEmpty()) {
            $this->info('✅ No existing stuck records found');

            return;
        }

        $this->info('📊 Found '.$stuckRecords->count().' existing stuck records to process');

        $moved = 0;
        $observer = new \App\Observers\LegacyApiRawDataHistoryObserver;

        foreach ($stuckRecords as $record) {
            try {
                // Use reflection to access the private moveToFilterTable method
                $reflection = new \ReflectionClass($observer);
                $method = $reflection->getMethod('moveToFilterTable');
                $method->setAccessible(true);
                $method->invoke($observer, $record);

                // Delete the record from legacy table after moving to excluded
                $record->delete();
                $moved++;

                if ($moved % 50 == 0) {
                    $this->info("🔄 Processed {$moved} stuck records...");
                }
            } catch (\Exception $e) {
                $this->error("❌ Failed to move stuck record PID {$record->pid}: ".$e->getMessage());
                Log::error('Failed to move stuck record to excluded table', [
                    'pid' => $record->pid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("🎉 Successfully moved {$moved} existing stuck records to sale_masters_excluded table");
    }

    /**
     * Validate required fields in a record
     *
     * @return array Missing required fields
     */
    private function validateRequiredFields(array $record): array
    {
        $requiredFields = [
            'pid',
            'customer_id',
            'customer_name',
            // 'customer_address',
            // 'customer_city',
            // 'customer_state',
            // 'customer_zip',
            // 'customer_email',
            // 'customer_phone',
            // 'sales_rep_name',
            // 'sales_rep_email',
            'product',
            'gross_account_value',
        ];

        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (! isset($record[$field]) ||
                (is_string($record[$field]) && trim($record[$field]) === '') ||
                $record[$field] === null) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

    /**
     * Log synchronization statistics to the database
     */
    protected function logSyncStats(array $stats, Carbon $startTime, ?string $lastRunTimestamp, ?string $currentTimestamp): void
    {
        try {
            $endTime = now();
            $durationSeconds = $startTime->diffInSeconds($endTime);

            // Get office-wise processing data
            $officeData = $this->getOfficeWiseProcessingData($stats);

            // Create a summary of what was processed
            $commandParameters = [
                'command' => 'fieldroutes:sync-data',
                'last_run_timestamp' => $lastRunTimestamp,
                'current_timestamp' => $currentTimestamp,
                'duration_seconds' => $durationSeconds,
            ];

            // If we have office-wise data, log each office separately
            if (! empty($officeData)) {
                foreach ($officeData as $officeName => $officeStats) {
                    $this->logOfficeWiseSyncData(
                        $officeName,
                        $officeStats,
                        $startTime,
                        $lastRunTimestamp,
                        $currentTimestamp,
                        $commandParameters,
                        $durationSeconds
                    );
                }
            } else {
                // Fallback to system-wide logging if no office data available
                $this->logSystemWideSyncData(
                    $stats,
                    $startTime,
                    $lastRunTimestamp,
                    $currentTimestamp,
                    $commandParameters,
                    $durationSeconds
                );
            }

            $this->info('✅ Logged sync-data statistics to database');
            $this->info("📊 Summary: Processed {$stats['raw_data_processed']} records, saved {$stats['legacy_records_saved']} to legacy, dispatched {$stats['jobs_dispatched']} jobs");

        } catch (Exception $e) {
            $this->error('Failed to log sync statistics: '.$e->getMessage());
            Log::error('Failed to log FieldRoutes sync-data statistics', [
                'error' => $e->getMessage(),
                'stats' => $stats,
            ]);
        }
    }

    /**
     * Get office-wise processing data from the FieldRoutes tables
     */
    protected function getOfficeWiseProcessingData(array $stats): array
    {
        try {
            // Get office breakdown from FieldRoutes_Raw_Data
            $officeBreakdown = DB::table('FieldRoutes_Raw_Data')
                ->select('office_name', 'office_id')
                ->selectRaw('COUNT(*) as total_subscriptions')
                ->groupBy('office_name', 'office_id')
                ->get();

            $officeData = [];

            foreach ($officeBreakdown as $office) {
                // Map office_id back to integration table ID for foreign key constraint
                $integrationId = $this->mapOfficeIdToIntegrationId($office->office_id, $office->office_name);

                $officeData[$office->office_name] = [
                    'office_id' => $integrationId, // Use integration table ID for foreign key
                    'office_name' => $office->office_name,
                    'total_available' => $office->total_subscriptions,
                    'records_processed' => 0, // Will be calculated based on actual processing
                    'legacy_records_saved' => 0, // Will be calculated based on actual saves
                    'jobs_dispatched' => 0, // Will be calculated based on jobs
                    'data_source_types' => [],
                    'subscription_ids' => [], // Initialize empty array for subscription IDs
                    'errors' => 0,
                ];
            }

            // If we processed data, try to map it to offices
            if ($stats['raw_data_processed'] > 0 && ! empty($stats['subscription_ids'])) {
                // Get office mapping for the processed subscription IDs
                $subscriptionOfficeMapping = DB::table('FieldRoutes_Raw_Data')
                    ->whereIn('subscription_id', $stats['subscription_ids'])
                    ->select('subscription_id', 'office_name')
                    ->get()
                    ->groupBy('office_name');

                // Distribute subscription IDs and stats to their respective offices
                foreach ($subscriptionOfficeMapping as $officeName => $subscriptions) {
                    if (isset($officeData[$officeName])) {
                        $officeSubscriptionIds = $subscriptions->pluck('subscription_id')->toArray();
                        $recordCount = count($officeSubscriptionIds);

                        $officeData[$officeName]['subscription_ids'] = $officeSubscriptionIds;
                        $officeData[$officeName]['records_processed'] = $recordCount;
                        $officeData[$officeName]['legacy_records_saved'] = $recordCount;
                        $officeData[$officeName]['jobs_dispatched'] = $stats['jobs_dispatched'];
                        $officeData[$officeName]['data_source_types'] = $stats['data_source_types'];
                        $officeData[$officeName]['errors'] = 0; // Specific errors per office would need more tracking
                    }
                }
            }

            return $officeData;

        } catch (Exception $e) {
            $this->error('Error getting office-wise data: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Map office_id from FieldRoutes data back to integration table ID
     * This fixes the foreign key constraint issue where we store decrypted office_id
     * but the sync log needs the integration table primary key
     */
    protected function mapOfficeIdToIntegrationId(int $officeId, string $officeName): ?int
    {
        try {
            // First, try to find integration by office name (most reliable for edge cases)
            $integrationByName = DB::table('integrations')
                ->where('name', 'FieldRoutes')
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
                        // Verify the office_id matches (data consistency check)
                        if ($config['office_id'] == $officeId) {
                            return $integrationByName->id; // Perfect match
                        } else {
                            Log::info("Office name/ID mismatch for {$officeName}: expected office_id={$officeId}, config has office_id={$config['office_id']}");
                        }
                    }
                } catch (\Exception $e) {
                    // Fall through to office_id matching
                }
            }

            // Fallback: Find by office_id if office name didn't work or had mismatched data
            $integrations = DB::table('integrations')
                ->where('name', 'FieldRoutes')
                ->where('status', 1)
                ->get();

            foreach ($integrations as $integration) {
                try {
                    // Try Laravel's built-in decryption first
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

                    // Match by office_id as fallback
                    if ($config && isset($config['office_id']) && $config['office_id'] == $officeId) {
                        return $integration->id; // Found matching integration by office_id
                    }

                } catch (\Exception $e) {
                    continue;
                }
            }

            Log::warning("Could not map office_id {$officeId} ({$officeName}) to integration table ID");

            return null; // Return null if no mapping found

        } catch (\Exception $e) {
            Log::error('Error mapping office_id to integration ID', [
                'office_id' => $officeId,
                'office_name' => $officeName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Log office-wise sync-data statistics
     */
    protected function logOfficeWiseSyncData(string $officeName, array $officeStats, Carbon $startTime, ?string $lastRunTimestamp, ?string $currentTimestamp, array $commandParameters, float $durationSeconds): void
    {
        FieldRoutesSyncLog::create([
            'execution_timestamp' => $startTime,
            'start_date' => $lastRunTimestamp ? Carbon::parse($lastRunTimestamp)->toDateString() : $startTime->toDateString(),
            'end_date' => $currentTimestamp ? Carbon::parse($currentTimestamp)->toDateString() : $startTime->toDateString(),
            'command_parameters' => json_encode($commandParameters),
            'subscriptionIDs' => isset($officeStats['subscription_ids']) ? json_encode($officeStats['subscription_ids']) : null,
            'office_id' => $officeStats['office_id'],
            'office_name' => $officeName,
            'reps_processed' => 0, // N/A for sync-data
            'total_available' => $officeStats['total_available'],
            'subscriptions_fetched' => $officeStats['legacy_records_saved'], // Fix: Use actual saved count
            'records_not_fetched' => 0, // N/A for sync-data
            'subscriptions_created' => 0, // N/A for sync-data
            'customers_created' => 0, // N/A for sync-data
            'appointments_created' => 0, // N/A for sync-data
            'subscriptions_updated' => $officeStats['legacy_records_saved'], // Records saved to legacy
            'customers_updated' => 0, // N/A for sync-data
            'appointments_updated' => 0, // N/A for sync-data
            'customer_personal_changes' => 0, // N/A for sync-data
            'customer_address_changes' => 0, // N/A for sync-data
            'customer_status_changes' => 0, // N/A for sync-data
            'customer_financial_changes' => 0, // N/A for sync-data
            'appointment_status_changes' => 0, // N/A for sync-data
            'appointment_schedule_changes' => 0, // N/A for sync-data
            'appointment_identifier_changes' => 0, // N/A for sync-data
            'records_touched' => $officeStats['records_processed'],
            'records_skipped' => 0, // N/A for sync-data
            'customers_touched' => 0, // N/A for sync-data
            'customers_skipped' => 0, // N/A for sync-data
            'appointments_touched' => 0, // N/A for sync-data
            'appointments_skipped' => 0, // N/A for sync-data
            'errors' => $officeStats['errors'],
            'duration_seconds' => $durationSeconds,
            'is_dry_run' => false,
            'error_details' => $officeStats['errors'] > 0 ? 'Check application logs for error details' : null,
        ]);
    }

    /**
     * Log system-wide sync-data statistics (fallback)
     */
    protected function logSystemWideSyncData(array $stats, Carbon $startTime, ?string $lastRunTimestamp, ?string $currentTimestamp, array $commandParameters, float $durationSeconds): void
    {
        FieldRoutesSyncLog::create([
            'execution_timestamp' => $startTime,
            'start_date' => $lastRunTimestamp ? Carbon::parse($lastRunTimestamp)->toDateString() : $startTime->toDateString(),
            'end_date' => $currentTimestamp ? Carbon::parse($currentTimestamp)->toDateString() : $startTime->toDateString(),
            'command_parameters' => json_encode($commandParameters),
            'subscriptionIDs' => isset($stats['subscription_ids']) ? json_encode($stats['subscription_ids']) : null,
            'office_id' => null, // System-wide operation
            'office_name' => 'System-wide sync-data',
            'reps_processed' => 0, // N/A for sync-data
            'total_available' => $stats['raw_data_processed'],
            'subscriptions_fetched' => $stats['legacy_records_saved'], // Fix: Use actual saved count
            'records_not_fetched' => 0, // N/A for sync-data
            'subscriptions_created' => 0, // N/A for sync-data
            'customers_created' => 0, // N/A for sync-data
            'appointments_created' => 0, // N/A for sync-data
            'subscriptions_updated' => $stats['legacy_records_saved'], // Records saved to legacy
            'customers_updated' => 0, // N/A for sync-data
            'appointments_updated' => 0, // N/A for sync-data
            'customer_personal_changes' => 0, // N/A for sync-data
            'customer_address_changes' => 0, // N/A for sync-data
            'customer_status_changes' => 0, // N/A for sync-data
            'customer_financial_changes' => 0, // N/A for sync-data
            'appointment_status_changes' => 0, // N/A for sync-data
            'appointment_schedule_changes' => 0, // N/A for sync-data
            'appointment_identifier_changes' => 0, // N/A for sync-data
            'records_touched' => $stats['raw_data_processed'],
            'records_skipped' => 0, // N/A for sync-data
            'customers_touched' => 0, // N/A for sync-data
            'customers_skipped' => 0, // N/A for sync-data
            'appointments_touched' => 0, // N/A for sync-data
            'appointments_skipped' => 0, // N/A for sync-data
            'errors' => $stats['errors'],
            'duration_seconds' => $durationSeconds,
            'is_dry_run' => false,
            'error_details' => $stats['errors'] > 0 ? 'Check application logs for error details' : null,
        ]);
    }

    /**
     * Validate date parameters
     */
    protected function validateDateParameters(?string $startDate, ?string $endDate): array
    {
        // If only one date is provided, require both
        if (($startDate && ! $endDate) || (! $startDate && $endDate)) {
            return [
                'valid' => false,
                'message' => 'Both --start-date and --end-date must be provided when using date range filtering',
            ];
        }

        // Validate start date format
        if ($startDate && ! $this->isValidDateFormat($startDate)) {
            return [
                'valid' => false,
                'message' => 'Invalid start-date format. Use YYYY-MM-DD format (e.g., 2024-01-15)',
            ];
        }

        // Validate end date format
        if ($endDate && ! $this->isValidDateFormat($endDate)) {
            return [
                'valid' => false,
                'message' => 'Invalid end-date format. Use YYYY-MM-DD format (e.g., 2024-01-15)',
            ];
        }

        // Validate that start date is not after end date
        if ($startDate && $endDate && $startDate > $endDate) {
            return [
                'valid' => false,
                'message' => 'Start date cannot be after end date',
            ];
        }

        return ['valid' => true, 'message' => 'Dates are valid'];
    }

    /**
     * Check if date string is in valid YYYY-MM-DD format
     */
    protected function isValidDateFormat(string $date): bool
    {
        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date);

            return $parsedDate && $parsedDate->format('Y-m-d') === $date;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate office IDs parameter
     */
    protected function validateOfficeIds(string $officeIdsParam): array
    {
        // Parse comma-separated office IDs
        $officeIds = array_map('trim', explode(',', $officeIdsParam));

        // Remove empty values
        $officeIds = array_filter($officeIds, function ($id) {
            return ! empty($id);
        });

        if (empty($officeIds)) {
            return [
                'valid' => false,
                'message' => 'No valid office IDs provided. Use comma-separated format (e.g., 1,7,22)',
            ];
        }

        // Validate that all office IDs are numeric
        foreach ($officeIds as $officeId) {
            if (! is_numeric($officeId) || intval($officeId) <= 0) {
                return [
                    'valid' => false,
                    'message' => "Invalid office ID: {$officeId}. Office IDs must be positive integers.",
                ];
            }
        }

        // Convert to integers
        $officeIds = array_map('intval', $officeIds);

        // Remove duplicates
        $officeIds = array_unique($officeIds);

        return [
            'valid' => true,
            'office_ids' => $officeIds,
            'message' => 'Office IDs are valid',
        ];
    }

    /**
     * Validate user IDs parameter
     */
    protected function validateUserIds(string $userIdsParam): array
    {
        // Parse comma-separated user IDs
        $userIds = array_map('trim', explode(',', $userIdsParam));

        // Remove empty values
        $userIds = array_filter($userIds, function ($id) {
            return ! empty($id);
        });

        if (empty($userIds)) {
            return [
                'valid' => false,
                'message' => 'No valid user IDs provided. Use comma-separated format (e.g., 123,456,789)',
            ];
        }

        // Validate that all user IDs are numeric
        foreach ($userIds as $userId) {
            if (! is_numeric($userId) || intval($userId) <= 0) {
                return [
                    'valid' => false,
                    'message' => "Invalid user ID: {$userId}. User IDs must be positive integers.",
                ];
            }
        }

        // Convert to integers
        $userIds = array_map('intval', $userIds);

        // Remove duplicates
        $userIds = array_unique($userIds);

        // Verify user IDs exist in the users table
        $existingUserIds = \App\Models\User::whereIn('id', $userIds)->pluck('id')->toArray();
        $nonExistentUserIds = array_diff($userIds, $existingUserIds);

        if (! empty($nonExistentUserIds)) {
            return [
                'valid' => false,
                'message' => 'User IDs not found in database: '.implode(', ', $nonExistentUserIds),
            ];
        }

        return [
            'valid' => true,
            'user_ids' => $userIds,
            'message' => 'User IDs are valid',
        ];
    }
}
