<?php

namespace App\Services\FieldRoutes;

use App\Models\FieldRoutesFailedRecord;
use App\Models\FrEmployeeData;
use App\Models\ProductCode;
use App\Models\Products;
use App\Models\User;
use App\Services\FieldRoutes\Traits\HandlesBatchProcessing;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataTransformationService
{
    use HandlesBatchProcessing;

    private $mappingConfig;

    private $employeeCache = [];

    private $domainName;

    /**
     * Cache for M2 milestone dates to avoid N+1 queries
     *
     * @var array
     */
    private $cachedM2Dates = [];

    private const EXEMPT_DOMAINS = ['primepestandlawn', 'solvepestorlando', 'solvepestraleigh', 'insightpestfl', 'insightpestmt', 'insightpestal'];

    /**
     * Set pre-loaded employee data to avoid N+1 queries
     */
    public function setEmployeeCache(array $employeeData): void
    {
        $this->employeeCache = $employeeData;
        Log::info('Employee cache set with ' . count($employeeData) . ' records');
    }

    /**
     * Batch fetch M2 milestone dates for multiple PIDs to avoid N+1 queries
     *
     * @param array $pids Array of PIDs to fetch M2 dates for
     * @return array Associative array [pid => milestone_date]
     */
    private function batchFetchM2Dates(array $pids): array
    {
        if (empty($pids)) {
            return [];
        }

        try {
            // Single query to fetch all M2 dates at once
            $m2Dates = DB::table('sale_product_master')
                ->whereIn('pid', $pids)
                ->where('type', 'm2')
                ->whereNotNull('milestone_date')
                ->select('pid', 'milestone_date')
                ->get()
                ->pluck('milestone_date', 'pid')
                ->toArray();

            Log::info('Batch fetched M2 milestone dates', [
                'total_pids' => count($pids),
                'found_m2_dates' => count($m2Dates),
            ]);

            return $m2Dates;
        } catch (Exception $e) {
            Log::error('Failed to batch fetch M2 milestone dates', [
                'error' => $e->getMessage(),
                'pids_count' => count($pids),
            ]);

            return [];
        }
    }

    /**
     * Transform the raw data according to mapping configuration
     */
    public function transformData(array $rawData, array $mappingConfig): array
    {
        $this->mappingConfig = $mappingConfig;
        $this->domainName = config('app.domain_name'); // Cache domain name to avoid repeated env() calls
        $transformedData = [];
        $errors = [];
        $processedCount = 0;
        $totalRecords = count($rawData);

        Log::info('Starting data transformation', [
            'total_records' => $totalRecords,
        ]);

        // Pre-fetch M2 milestone dates to avoid N+1 queries
        $pids = collect($rawData)
            ->pluck('pid')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($pids)) {
            $this->cachedM2Dates = $this->batchFetchM2Dates($pids);
            Log::info('Pre-fetched M2 dates for performance optimization', [
                'pids_count' => count($pids),
                'cached_dates_count' => count($this->cachedM2Dates),
            ]);
        }

        foreach ($rawData as $record) {
            try {
                $processedCount++;
                $recordId = $record->subscriptionID ?? 'unknown';

                // Log frozen/cancelled records for debugging
                $activeText = $record->active_text ?? '';
                if (strtolower(trim($activeText)) === 'frozen' || strtolower(trim($activeText)) === 'cancelled') {
                    Log::info('Processing frozen/cancelled record', [
                        'subscription_id' => $recordId,
                        'active_text' => $activeText,
                        'initial_status' => $record->initialStatusText ?? 'unknown',
                    ]);
                }

                // Only log progress every 100 records to reduce noise
                if ($processedCount % 100 === 0 || $processedCount === $totalRecords) {
                    Log::info("Processing record {$processedCount}/{$totalRecords}");
                }

                // Transform the data according to mapping rules
                $transformedRecord = [];

                // Process API to DB mappings
                foreach ($mappingConfig['field_mappings']['api_to_db'] as $apiField => $mapping) {
                    try {
                        if (isset($record->$apiField)) {
                            $value = $this->processField($record->$apiField, $mapping, (array) $record);

                            if (is_array($value) && isset($mapping['db_fields'])) {
                                foreach ($mapping['db_fields'] as $dbField) {
                                    $transformedRecord[$dbField] = $value[$dbField] ?? null;
                                }
                            } elseif (isset($mapping['db_fields'])) {
                                foreach ($mapping['db_fields'] as $dbField) {
                                    $transformedRecord[$dbField] = $value;
                                }
                            } else {
                                $transformedRecord[$mapping['db_field']] = $value;
                            }
                        } elseif (! empty($mapping['required'])) {
                            if (isset($mapping['default'])) {
                                $transformedRecord[$mapping['db_field']] = $mapping['default'];
                            } else {
                                throw new \Exception("Required field {$apiField} is missing from API data");
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error processing field', [
                            'subscription_id' => $recordId,
                            'api_field' => $apiField,
                            'error' => $e->getMessage(),
                        ]);
                        $this->recordFailure(
                            $record,
                            FieldRoutesFailedRecord::FAILURE_TYPE_MISSING_DATA,
                            'Required field missing',
                            "Required field {$apiField} is missing from API data"
                        );
                        throw $e;
                    }
                }

                // Process customer API fields
                foreach ($mappingConfig['field_mappings']['customer_api_fields'] as $apiField => $mapping) {
                    try {
                        // For customer name fields, we need special handling
                        if ($apiField === 'fname' || $apiField === 'lname') {
                            // Only set the customer name once when processing fname
                            if ($apiField === 'fname') {
                                $transformedRecord['customer_name'] = trim(($record->fname ?? '') . ' ' . ($record->lname ?? ''));
                                if (empty(trim($transformedRecord['customer_name']))) {
                                    $transformedRecord['customer_name'] = null;
                                }
                            }

                            continue;
                        }

                        // Handle nested fields (e.g., recurringTicket.balance)
                        $value = $this->getNestedValue((array) $record, $apiField);

                        if ($value !== null) {
                            $value = $this->processField($value, $mapping, (array) $record);
                            if (is_array($value)) {
                                foreach ($value as $key => $val) {
                                    if (isset($mapping['db_field'])) {
                                        $transformedRecord[$mapping['db_field']] = $val;
                                    }
                                }
                            } else {
                                $transformedRecord[$mapping['db_field']] = $value;
                            }
                        } elseif (! empty($mapping['required'])) {
                            if (isset($mapping['default'])) {
                                $transformedRecord[$mapping['db_field']] = $mapping['default'];
                            } else {
                                throw new \Exception("Required customer field {$apiField} is missing from API data");
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error processing customer field', [
                            'subscription_id' => $recordId,
                            'api_field' => $apiField,
                            'error' => $e->getMessage(),
                        ]);
                        $this->recordFailure(
                            $record,
                            FieldRoutesFailedRecord::FAILURE_TYPE_TRANSFORMATION,
                            'Customer field transformation failed',
                            "Failed to transform field {$apiField}: " . $e->getMessage()
                        );
                        throw $e;
                    }
                }

                // Process computed fields
                foreach ($mappingConfig['field_mappings']['computed_fields'] as $field => $config) {
                    try {
                        $value = null;
                        if (isset($config['transform'])) {
                            $value = $this->transformValue(null, $config['transform'], (array) $record);
                            if (is_array($value)) {
                                foreach ($value as $key => $val) {
                                    $transformedRecord[$key] = $val;
                                }
                            } else {
                                $transformedRecord[$field] = $value;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error processing computed field', [
                            'subscription_id' => $recordId,
                            'field' => $field,
                            'error' => $e->getMessage(),
                        ]);
                        $this->recordFailure(
                            $record,
                            FieldRoutesFailedRecord::FAILURE_TYPE_TRANSFORMATION,
                            'Computed field transformation failed',
                            "Failed to compute field {$field}: " . $e->getMessage()
                        );
                        throw $e;
                    }
                }

                // Validate required fields before adding to transformed data
                $missingFields = $this->validateRequiredFields($transformedRecord);
                if (! empty($missingFields)) {
                    $this->recordFailure(
                        $record,
                        FieldRoutesFailedRecord::FAILURE_TYPE_MISSING_DATA,
                        'Missing required fields',
                        'Missing required fields: ' . implode(', ', $missingFields)
                    );
                    throw new \Exception('Missing required fields: ' . implode(', ', $missingFields));
                }

                // Add metadata
                $transformedRecord['created_at'] = Carbon::now();
                $transformedRecord['updated_at'] = Carbon::now();

                // Store original status for trigger_date logic before status override
                $originalInitialStatus = $record->initialStatusText ?? '';

                // Override initialStatusText based on cancellation status with enhanced Frozen logic
                if ($this->shouldBeMarkedAsCancelled($record)) {
                    $transformedRecord['initialStatusText'] = 'Cancelled';
                    $cancelReason = $this->getCancellationReason($record);
                    Log::info('Overriding initialStatusText to Cancelled', [
                        'subscription_id' => $recordId,
                        'original_status' => $originalInitialStatus,
                        'reason' => $cancelReason,
                    ]);
                } else {
                    // Only keep "Completed" status, everything else should be "Pending"
                    $transformedRecord['initialStatusText'] = ($originalInitialStatus === 'Completed') ? 'Completed' : 'Pending';
                }

                // Pass original status to processSpecialFields for trigger_date logic
                $transformedRecord['_original_initial_status'] = $originalInitialStatus;

                // Then process special fields
                $transformedRecord = $this->processSpecialFields($transformedRecord);

                // Validate business rules BEFORE adding to final data
                if (! $this->validateBusinessRules($record, $transformedRecord)) {
                    Log::warning('Record failed business rule validation, skipping', [
                        'subscription_id' => $recordId,
                        'active_text' => $record->active_text ?? 'unknown',
                        'initial_status' => $record->initialStatusText ?? 'unknown',
                    ]);

                    continue; // Skip this record but continue processing others
                }

                // Log the transformed record details
                Log::info('Successfully transformed record', [
                    'subscription_id' => $recordId,
                    'customer_id' => $transformedRecord['customer_id'] ?? 'unknown',
                    'sold_by' => $transformedRecord['soldBy'] ?? 'unknown',
                    'closer1_id' => $transformedRecord['closer1_id'] ?? 'unknown',
                    'sales_rep_name' => $transformedRecord['sales_rep_name'] ?? 'unknown',
                    'final_initial_status' => $transformedRecord['initialStatusText'] ?? 'unknown',
                ]);

                $transformedData[] = $transformedRecord;
            } catch (\Exception $e) {
                $errors[] = [
                    'record_id' => $record->subscriptionID ?? 'unknown',
                    'error' => $e->getMessage(),
                ];

                // Enhanced logging for frozen/cancelled records
                $activeText = $record->active_text ?? 'unknown';
                $initialStatus = $record->initialStatusText ?? 'unknown';

                Log::error('Failed to transform record', [
                    'record_id' => $record->subscriptionID ?? 'unknown',
                    'active_text' => $activeText,
                    'initial_status' => $initialStatus,
                    'is_frozen_cancelled' => in_array(strtolower(trim($activeText)), ['frozen', 'cancelled']),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Record the failure if it hasn't been recorded by a more specific handler
                $this->recordFailure(
                    $record,
                    FieldRoutesFailedRecord::FAILURE_TYPE_TRANSFORMATION,
                    'General transformation error',
                    $e->getMessage()
                );
            }
        }

        Log::info('Transformation completed', [
            'total_records' => $totalRecords,
            'successful' => count($transformedData),
            'errors' => count($errors),
            'error_details' => $errors,
        ]);

        return $transformedData;
    }

    /**
     * Record a failed record
     */
    protected function recordFailure(object $record, string $failureType, string $reason, string $description): void
    {
        try {
            DB::transaction(function () use ($record, $failureType, $reason, $description) {
                FieldRoutesFailedRecord::updateOrCreate(
                    [
                        'subscription_id' => $record->subscriptionID ?? null,
                        'failure_type' => $failureType,
                    ],
                    [
                        'customer_id' => $record->customerID ?? null,
                        'raw_data' => (array) $record,
                        'failure_reason' => $reason,
                        'failure_description' => $description,
                        'failed_at' => Carbon::now(),
                    ]
                );
            });

            Log::info('Failure record created/updated', [
                'subscription_id' => $record->subscriptionID ?? null,
                'failure_type' => $failureType,
            ]);
        } catch (\Exception $e) {
            Log::error('Error recording failure', [
                'error' => $e->getMessage(),
                'record' => $record,
                'failure_type' => $failureType,
            ]);
            throw $e;
        }
    }

    /**
     * Validate business rules for a record
     */
    protected function validateBusinessRules(object $record, array $transformedRecord): bool
    {
        // Always return true to allow all records to be processed
        // The import_to_sales field (determined in determineImportToSales) will mark them as failed
        // Failed records will be automatically moved to sale_masters_excluded by the observer

        Log::info('Business rules validation - allowing record for processing', [
            'subscription_id' => $record->subscriptionID ?? 'unknown',
            'sold_by' => $record->soldBy ?? 'unknown',
        ]);

        return true;
    }

    /**
     * Extract customer data from the record
     */
    protected function extractCustomerData(object $record): array
    {
        $customerFields = [
            'customer_id',
            'fname',
            'lname',
            'address',
            'city',
            'state',
            'zip',
            'email',
            'phone1',
            'mostRecentCreditCardLastFour',
            'aPay',
            'balanceAge',
        ];

        $customerData = [];
        foreach ($customerFields as $field) {
            $customerData[$field] = $record->$field ?? null;
        }

        return $customerData;
    }

    /**
     * Extract appointment data from the record
     */
    protected function extractAppointmentData(object $record): array
    {
        $appointmentFields = [
            'appointment_id',
            'date',
            'status',
            'type',
            'completed_date',
            'cancelled_date',
        ];

        $appointmentData = [];
        foreach ($appointmentFields as $field) {
            $appointmentData[$field] = $record->$field ?? null;
        }

        return $appointmentData;
    }

    /**
     * Process a single record according to mapping rules
     */
    protected function processRecord(
        array $subscriptionDetail,
        array $customerDetail,
        array $appointmentDetail,
        array $mappingConfig
    ): ?array {
        try {
            // Process product information
            $productInfo = $this->processProductInfo($subscriptionDetail);

            // Process sales rep information
            $salesRepInfo = $this->processSalesRepInfo($subscriptionDetail);

            // Process dates
            $dates = $this->processDates($subscriptionDetail, $appointmentDetail);

            // Build the transformed record
            $transformedRecord = [
                'api_type' => 'subscriptions',
                'pid' => $subscriptionDetail['subscriptionID'] ?? null,
                'customer_id' => $customerDetail['customer_id'] ?? null,
                'customer_name' => trim(($customerDetail['fname'] ?? '') . ' ' . ($customerDetail['lname'] ?? '')),
                'customer_address' => $customerDetail['address'] ?? null,
                'customer_city' => $customerDetail['city'] ?? null,
                'customer_state' => $customerDetail['state'] ?? null,
                'customer_zip' => $customerDetail['zip'] ?? null,
                'customer_email' => $customerDetail['email'] ?? null,
                'customer_phone' => $customerDetail['phone1'] ?? null,
                'card_on_file' => $customerDetail['mostRecentCreditCardLastFour'] ?? null,
                'auto_pay' => $customerDetail['aPay'] ?? null,
                'balance_age' => $customerDetail['balanceAge'] ?? 0,

                // Office information
                'office_name' => $subscriptionDetail['office_name'] ?? null,

                // Product information
                'product_id' => $productInfo['product_id'] ?? null,
                'product_code' => $productInfo['product_code'] ?? null,
                'product' => $subscriptionDetail['serviceType'] ?? null,
                'sale_product_name' => $productInfo['product_code'] ?? null,

                // Sales rep information
                'soldBy' => $subscriptionDetail['soldBy'] ?? null,
                'soldBy2' => $subscriptionDetail['soldBy2'] ?? null,
                'sales_rep_email' => $salesRepInfo['sales_rep_email'] ?? null,
                'closer1_id' => $salesRepInfo['closer1_id'] ?? null,
                'closer2_id' => $salesRepInfo['closer2_id'] ?? null,
                'sales_rep_name' => $salesRepInfo['sales_rep_name'] ?? null,
                'sales_setter_name' => $salesRepInfo['sales_setter_name'] ?? null,

                // Dates
                'date_cancelled' => $dates['date_cancelled'] ?? null,
                'customer_signoff' => $dates['customer_signoff'] ?? null,
                'm1_date' => $dates['m1_date'] ?? null,
                'initial_service_date' => $dates['initial_service_date'] ?? null,

                // Additional fields
                'gross_account_value' => $subscriptionDetail['contractValue'] ?? null,
                'length_of_agreement' => $subscriptionDetail['agreementLength'] ?? null,
                'service_schedule' => $subscriptionDetail['frequency'] ?? null,
                'initial_service_cost' => $subscriptionDetail['initialServiceTotal'] ?? null,
                'subscription_payment' => $subscriptionDetail['recurringCharge'] ?? null,
                'service_completed' => $this->calculateServiceCompleted($subscriptionDetail),
                'job_status' => $this->computeJobStatus($subscriptionDetail, $appointmentDetail),
            ];

            // Log the customer name construction
            Log::debug('Customer name construction', [
                'fname' => $customerDetail['fname'] ?? 'null',
                'lname' => $customerDetail['lname'] ?? 'null',
                'result' => $transformedRecord['customer_name'],
            ]);

            return $transformedRecord;
        } catch (Exception $e) {
            Log::error('Error processing record', [
                'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process product information
     */
    protected function processProductInfo(array $subscriptionDetail): array
    {
        $productCode = null;
        if (! empty($subscriptionDetail['serviceType'])) {
            $productCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $subscriptionDetail['serviceType']));
        }

        $productCodeRecord = ProductCode::where('product_code', $productCode)->first();

        if (! $productCodeRecord) {
            $defaultProductId = config('global_vars.DEFAULT_PRODUCT_ID');
            $product = Products::where('product_id', $defaultProductId)->first();
            if ($product) {
                $productCodeRecord = $product->productCodes()->first();
            }
        }

        return [
            'product_id' => $productCodeRecord ? $productCodeRecord->product_id : null,
            'product_code' => $productCodeRecord ? $productCodeRecord->product_code : null,
        ];
    }

    /**
     * Process sales rep information
     */
    protected function processSalesRepInfo(array $subscriptionDetail): array
    {
        $soldBy = $subscriptionDetail['soldBy'] ?? null;
        $officeId = $subscriptionDetail['officeId'] ?? null;
        $salesRepData = null;

        if ($soldBy) {
            // Strict mode: office_id is required for accurate lookups
            if (!$officeId) {
                Log::warning("Missing office_id for employee lookup in processSalesRepInfo", [
                    'employee_id' => $soldBy,
                    'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                    'message' => 'office_id is required to prevent cross-office employee mismatches'
                ]);
            } else {
                // Use composite key with office context
                $compositeKey = $soldBy . '_' . $officeId;
                if (isset($this->employeeCache[$compositeKey])) {
                    $cachedData = $this->employeeCache[$compositeKey];
                    if (! empty($cachedData['sequifi_id'])) {
                        $salesRepData = (object) $cachedData;
                    }
                } else {
                    // Fallback to database query with office context
                    $salesRepData = FrEmployeeData::where('employee_id', $soldBy)
                        ->where('office_id', $officeId)
                        ->whereNotNull('sequifi_id')
                        ->first();
                }
            }
        }

        $salesRep2Data = null;
        if (! empty($subscriptionDetail['soldBy2']) && $subscriptionDetail['soldBy2'] != '0') {
            $salesRep2Data = User::where('employee_id', $subscriptionDetail['soldBy2'])
                ->select('id', 'email', 'first_name', 'last_name')
                ->first();
        }

        return [
            'sales_rep_email' => $salesRepData ? $salesRepData->email : null,
            'closer1_id' => $salesRepData ? $salesRepData->sequifi_id : null,
            'closer2_id' => $salesRep2Data ? $salesRep2Data->id : null,
            'sales_rep_name' => $salesRepData ? $this->formatSalesRepName($salesRepData) : null,
            'sales_setter_name' => 'sales_rep_sold_by - ' . ($subscriptionDetail['soldBy'] ?? '') .
                ' email- ' . ($salesRepData->email ?? ''),
        ];
    }

    /**
     * Process dates from subscription and appointment data
     */
    protected function processDates(array $subscriptionDetail, array $appointmentDetail): array
    {
        $dates = [
            'date_cancelled' => null,
            'customer_signoff' => null,
            'm1_date' => null,
            'initial_service_date' => null,
        ];

        // Handle date cancelled with enhanced logic including Frozen status
        // Priority 1: Check if subscription is Frozen (effectively cancelled)
        if (
            ! empty($subscriptionDetail['dateCancelled']) &&
            $subscriptionDetail['dateCancelled'] != '0000-00-00 00:00:00' &&
            isset($subscriptionDetail['active_text']) &&
            strtolower(trim($subscriptionDetail['active_text'])) === 'frozen'
        ) {

            // Frozen subscriptions with dateCancelled are treated as cancelled
            try {
                $carbonDate = Carbon::parse($subscriptionDetail['dateCancelled']);
                if ($carbonDate->year < 1000) {
                    Log::warning('Invalid dateCancelled for Frozen subscription', [
                        'subscription_id' => $subscriptionDetail['subscriptionID'],
                        'value' => $subscriptionDetail['dateCancelled'],
                    ]);
                    $dates['date_cancelled'] = null;
                } else {
                    $dates['date_cancelled'] = $carbonDate->toDateString();
                }
            } catch (\Exception $e) {
                Log::warning('Failed to parse dateCancelled for Frozen subscription', [
                    'subscription_id' => $subscriptionDetail['subscriptionID'],
                    'value' => $subscriptionDetail['dateCancelled'],
                    'error' => $e->getMessage(),
                ]);
                $dates['date_cancelled'] = null;
            }

            Log::info('Setting date_cancelled for Frozen subscription', [
                'subscription_id' => $subscriptionDetail['subscriptionID'],
                'active_text' => $subscriptionDetail['active_text'],
                'date_cancelled' => $dates['date_cancelled'],
            ]);
        }
        // Priority 2: Check appointment-level cancellation for non-Frozen subscriptions
        elseif ($this->isLastAppointmentCancelled($subscriptionDetail['subscriptionID'])) {
            // Use subscription dateCancelled if available
            if (
                ! empty($subscriptionDetail['dateCancelled']) &&
                $subscriptionDetail['dateCancelled'] != '0000-00-00 00:00:00'
            ) {
                try {
                    $carbonDate = Carbon::parse($subscriptionDetail['dateCancelled']);
                    if ($carbonDate->year < 1000) {
                        Log::warning('Invalid dateCancelled for appointment cancellation', [
                            'subscription_id' => $subscriptionDetail['subscriptionID'],
                            'value' => $subscriptionDetail['dateCancelled'],
                        ]);
                        $dates['date_cancelled'] = null;
                    } else {
                        $dates['date_cancelled'] = $carbonDate->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse dateCancelled for appointment cancellation', [
                        'subscription_id' => $subscriptionDetail['subscriptionID'],
                        'value' => $subscriptionDetail['dateCancelled'],
                        'error' => $e->getMessage(),
                    ]);
                    $dates['date_cancelled'] = null;
                }
            } else {
                // Try to get the actual appointment cancellation date
                $appointmentCancelDate = $this->getAppointmentCancellationDate($subscriptionDetail['subscriptionID']);
                if ($appointmentCancelDate) {
                    $dates['date_cancelled'] = $appointmentCancelDate;
                } else {
                    // If we can't find a reliable cancellation date, leave it null
                    $dates['date_cancelled'] = null;
                }
            }

            Log::info('Setting date_cancelled based on appointment status', [
                'subscription_id' => $subscriptionDetail['subscriptionID'],
                'date_cancelled' => $dates['date_cancelled'],
            ]);
        } else {
            // No cancellation at appointment level and not Frozen - ignore subscription dateCancelled
            $dates['date_cancelled'] = null;

            if (
                ! empty($subscriptionDetail['dateCancelled']) &&
                $subscriptionDetail['dateCancelled'] != '0000-00-00 00:00:00'
            ) {
                Log::info('Ignoring subscription dateCancelled - not Frozen and appointments not cancelled', [
                    'subscription_id' => $subscriptionDetail['subscriptionID'],
                    'active_text' => $subscriptionDetail['active_text'] ?? 'null',
                    'subscription_date_cancelled' => $subscriptionDetail['dateCancelled'],
                ]);
            }
        }

        // Handle customer signoff with improved logic and fallback
        $customerSignoffDate = null;

        // Try dateAdded first
        if (
            ! empty($subscriptionDetail['dateAdded']) &&
            $subscriptionDetail['dateAdded'] != '0000-00-00 00:00:00'
        ) {
            try {
                $customerSignoffDate = Carbon::parse($subscriptionDetail['dateAdded'])->toDateString();
            } catch (Exception $e) {
                Log::warning('Failed to parse dateAdded for customer_signoff', [
                    'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                    'dateAdded' => $subscriptionDetail['dateAdded'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to last_modified if no valid dateAdded found
        if (
            ! $customerSignoffDate && ! empty($subscriptionDetail['last_modified']) &&
            $subscriptionDetail['last_modified'] != '0000-00-00 00:00:00'
        ) {
            try {
                $customerSignoffDate = Carbon::parse($subscriptionDetail['last_modified'])->toDateString();
                Log::info('Using last_modified as fallback for customer_signoff', [
                    'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to parse last_modified for customer_signoff', [
                    'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                    'last_modified' => $subscriptionDetail['last_modified'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Final fallback to current date if no valid date found
        if (! $customerSignoffDate) {
            $customerSignoffDate = Carbon::now()->toDateString();
            Log::info('Using current date as fallback for customer_signoff', [
                'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                'reason' => 'No valid dateAdded or last_modified found',
            ]);
        }

        $dates['customer_signoff'] = $customerSignoffDate;

        // Handle initial service date and M1 date
        $initial_service_date = null;

        // First try to use initialAppointmentDate if available
        if (! empty($appointmentDetail['date']) && $appointmentDetail['date'] != '0000-00-00 00:00:00') {
            $initial_service_date = Carbon::parse($appointmentDetail['date'])->toDateString();
        }
        // If not available and status is Completed, try fallback dates
        elseif ($subscriptionDetail['initialStatusText'] == 'Completed') {
            // Try dateAdded first
            if (! empty($subscriptionDetail['dateAdded']) && $subscriptionDetail['dateAdded'] != '0000-00-00 00:00:00') {
                $initial_service_date = Carbon::parse($subscriptionDetail['dateAdded'])->toDateString();
                Log::info("Using dateAdded as fallback for initial_service_date on completed subscription: {$subscriptionDetail['subscriptionID']}");
            }
            // Then try dateUpdated
            elseif (! empty($subscriptionDetail['dateUpdated']) && $subscriptionDetail['dateUpdated'] != '0000-00-00 00:00:00') {
                $initial_service_date = Carbon::parse($subscriptionDetail['dateUpdated'])->toDateString();
                Log::info("Using dateUpdated as fallback for initial_service_date on completed subscription: {$subscriptionDetail['subscriptionID']}");
            }
        }

        if ($initial_service_date) {
            $dates['initial_service_date'] = $initial_service_date;
            // Only set m1_date if initial status is Completed
            if ($subscriptionDetail['initialStatusText'] == 'Completed') {
                $dates['m1_date'] = $initial_service_date;
            }
        }

        return $dates;
    }

    /**
     * Format sales rep name
     */
    protected function formatSalesRepName(object $salesRepData): ?string
    {
        $firstName = property_exists($salesRepData, 'fname') ?
            $salesRepData->fname : ($salesRepData->first_name ?? null);

        $lastName = property_exists($salesRepData, 'lname') ?
            $salesRepData->lname : ($salesRepData->last_name ?? null);

        if (empty($firstName) && empty($lastName)) {
            return null;
        }

        return trim($firstName . ' ' . $lastName);
    }

    /**
     * Calculate number of completed services
     */
    protected function calculateServiceCompleted(array $subscriptionDetail): int
    {
        if (empty($subscriptionDetail['completedAppointmentIDs'])) {
            return 0;
        }

        return count(explode(',', $subscriptionDetail['completedAppointmentIDs']));
    }

    /**
     * Determine job status based on subscription and appointment data
     * Only trust appointment-level cancellation status
     */
    protected function computeJobStatus(array $subscriptionDetail, array $appointmentDetail): string
    {
        $subscriptionId = $subscriptionDetail['subscriptionID'] ?? null;

        // Enhanced Frozen status logic is now working correctly

        // Use the centralized cancellation logic - both initialStatusText and job_status should be consistent
        if ($this->shouldBeMarkedAsCancelled($subscriptionDetail)) {
            $reason = $this->getCancellationReason($subscriptionDetail);

            // Log only for debugging if needed
            // Log::info("Setting job_status to Cancelled in computeJobStatus", ['subscription_id' => $subscriptionId, 'reason' => $reason]);
            return 'Cancelled';
        }

        if ($subscriptionDetail['initialStatusText'] == 'Completed') {
            return 'Completed';
        }

        return 'Pending';
    }

    /**
     * Determine import status reason and description
     */
    protected function determineImportStatusFields(array $subscriptionDetail, ?string $customerSignoff): array
    {
        // First check if we have a valid sales rep (closer1_id)
        $soldBy = $subscriptionDetail['soldBy'] ?? null;
        $officeId = $subscriptionDetail['officeId'] ?? null;

        // Strict mode: office_id is required for accurate lookups
        if (!$officeId) {
            Log::warning("Missing office_id for employee lookup in determineImportStatusFields", [
                'employee_id' => $soldBy,
                'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                'message' => 'office_id is required to prevent cross-office employee mismatches'
            ]);

            return [
                'import_status_reason' => 'Invalid Sales Rep',
                'import_status_description' => 'Missing office_id for employee lookup - employee_id: ' . ($soldBy ?? 'unknown'),
            ];
        }

        // Query with office context
        $salesRepData = FrEmployeeData::where('employee_id', $soldBy)
            ->where('office_id', $officeId)
            ->whereNotNull('sequifi_id')
            ->first();

        if (! $salesRepData) {
            $description = 'No valid sales rep found with sequifi_id for employee_id: ' . ($soldBy ?? 'unknown') . ' and office_id: ' . $officeId;

            return [
                'import_status_reason' => 'Invalid Sales Rep',
                'import_status_description' => $description,
            ];
        }

        // If sales rep is valid, check date criteria
        $domain = config('app.domain_name');

        $shouldBeImportable = false;
        $dateRestrictionMessage = '';

        if ($domain == 'whiteknight') {
            $shouldBeImportable = $customerSignoff && Carbon::parse($customerSignoff) >= '2025-03-01';
            $dateRestrictionMessage = 'Sales before March 1st, 2025 are not eligible for import in WhiteKnight domain';
        } elseif ($domain == 'moxie') {
            $shouldBeImportable = $customerSignoff && Carbon::parse($customerSignoff) >= '2024-11-01';
            $dateRestrictionMessage = 'Sales before November 1st, 2024 are not eligible for import in Moxie domain';
        } else {
            $shouldBeImportable = $customerSignoff && Carbon::parse($customerSignoff) > '2024-12-31';
            $dateRestrictionMessage = 'Sales before December 31st, 2024 are not eligible for import';
        }

        if (! $shouldBeImportable) {
            return [
                'import_status_reason' => 'Date Restriction',
                'import_status_description' => $dateRestrictionMessage,
            ];
        }

        // Record is valid for import
        return [
            'import_status_reason' => null,
            'import_status_description' => null,
        ];
    }

    /**
     * Process a field value based on its configuration
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function processField($value, array $mapping, array $context = [])
    {
        try {
            // Handle null values
            if ($value === null) {
                if (! empty($mapping['required']) && ! isset($mapping['default'])) {
                    throw new \Exception('Required field is null and no default value provided');
                }

                return $mapping['default'] ?? null;
            }

            // Apply transformation if specified
            if (isset($mapping['transform'])) {
                try {
                    $value = $this->transformValue($value, $mapping['transform'], $context);

                    // If transformation returns null and allow_null_transform is true, use default value
                    if ($value === null) {
                        if (isset($mapping['default'])) {
                            return $mapping['default'];
                        }
                        if (! isset($mapping['allow_null_transform']) || ! $mapping['allow_null_transform']) {
                            throw new \Exception('Transformation returned null for required field');
                        }
                    }
                } catch (\Exception $e) {
                    if (! isset($mapping['allow_null_transform']) || ! $mapping['allow_null_transform']) {
                        throw $e;
                    }

                    // If allow_null_transform is true, use default value
                    return $mapping['default'] ?? null;
                }
            }

            // If the value is an array of transformed values, return it as is
            if (is_array($value)) {
                return $value;
            }

            return $this->castValue($value, $mapping['type']);
        } catch (\Exception $e) {
            Log::error('Error processing field', [
                'value' => $value,
                'mapping' => $mapping,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cast value to specified type
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                if (is_string($value)) {
                    return in_array(strtolower($value), ['1', 'true', 'yes', 'on']);
                }

                return (bool) $value;
            case 'datetime':
                if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00' || empty($value) || is_null($value)) {
                    return null;
                }
                try {
                    $carbonDate = $value instanceof Carbon ? $value : Carbon::parse($value);
                    // Check if the date is valid (not before year 1000)
                    if ($carbonDate->year < 1000) {
                        Log::warning('Invalid datetime value detected and converted to null', [
                            'original_value' => $value,
                            'parsed_date' => $carbonDate->toDateTimeString(),
                        ]);

                        return null;
                    }

                    return $carbonDate;
                } catch (\Exception $e) {
                    Log::warning('Failed to parse datetime value, returning null', [
                        'value' => $value,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            default:
                return (string) $value;
        }
    }

    /**
     * Transform value using specified transformer
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function transformValue($value, string $transformer, array $context = [])
    {
        Log::debug('Transforming value', [
            'value' => $value,
            'transformer' => $transformer,
            'context' => $context,
        ]);

        switch ($transformer) {
            case 'sales_rep_transform':
                return $this->transformSalesRep($value, $context);

            case 'date_transform':
                return $this->transformDate($value);

            case 'balance_status_transform':
                return $this->transformBalanceStatus($value);

            case 'completed_appointments_count':
                return $this->transformCompletedAppointments($value);

            case 'compute_job_status':
                return $this->computeJobStatus($context, []);

            case 'compute_m1_date':
                return $this->computeM1Date($context);

            case 'compute_initial_service_date':
                return $this->computeInitialServiceDate($context);

            case 'customer_name_transform':
                return $this->transformCustomerName($value, $context);

            case 'product_transform':
                return $this->transformProduct($value);

            case 'conditional_cancellation_transform':
                return $this->transformConditionalCancellation($value, $context);

            case 'determine_import_to_sales':
                return $this->determineImportToSales($context, $context['customer_signoff'] ?? null);

            case 'determine_import_status_reason':
                return $this->determineImportStatusReason($context, $context['customer_signoff'] ?? null);

            case 'determine_import_status_description':
                return $this->determineImportStatusDescription($context, $context['customer_signoff'] ?? null);

            default:
                Log::warning('Unknown transformer', ['transformer' => $transformer]);

                return $value;
        }
    }

    /**
     * Transform date value
     */
    private function transformDate(string $value): ?string
    {
        $nullValues = ['0000-00-00', '0000-00-00 00:00:00'];
        if (in_array($value, $nullValues) || empty($value) || is_null($value)) {
            return null;
        }
        try {
            $carbonDate = Carbon::parse($value);
            // Check if the date is valid (not before year 1000)
            if ($carbonDate->year < 1000) {
                Log::warning('Invalid date value detected and converted to null in transformDate', [
                    'original_value' => $value,
                    'parsed_date' => $carbonDate->toDateTimeString(),
                ]);

                return null;
            }

            return $carbonDate->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Log::warning('Failed to parse date value in transformDate, returning null', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Transform balance status
     *
     * @param  mixed  $value
     */
    private function transformBalanceStatus($value): string
    {
        if ($value === '0.00' || $value === 0 || $value === 0.0 || $value === '0') {
            return 'cleared';
        }

        return 'Pending';
    }

    /**
     * Transform completed appointments count
     */
    private function transformCompletedAppointments(string $value): int
    {
        if (empty($value)) {
            return 0;
        }

        return count(explode(',', $value));
    }

    /**
     * Transform sales rep data
     *
     * @param  mixed  $soldBy
     */
    private function transformSalesRep($soldBy, array $context): array
    {
        if (empty($soldBy)) {
            Log::info('Empty soldBy value, returning nulls', ['context' => $context]);

            return [
                'soldBy' => null,
                'sales_rep_email' => null,
                'closer1_id' => null,
                'sales_rep_name' => null,
                'sales_setter_name' => null,
            ];
        }

        try {
            // Get office_id from context
            $officeId = $context['officeId'] ?? null;

            // Strict mode: office_id is required for accurate lookups
            if (!$officeId) {
                Log::warning("Missing office_id for employee lookup", [
                    'employee_id' => $soldBy,
                    'subscription_id' => $context['subscriptionID'] ?? 'unknown',
                    'message' => 'office_id is required to prevent cross-office employee mismatches'
                ]);

                return [
                    'soldBy' => $soldBy,
                    'sales_rep_email' => null,
                    'closer1_id' => null,
                    'sales_rep_name' => null,
                    'sales_setter_name' => null,
                ];
            }

            // Look up employee data from cache first, then database
            $salesRepData = null;
            $compositeKey = $soldBy . '_' . $officeId;

            if (isset($this->employeeCache[$compositeKey])) {
                // Convert array back to object-like structure for compatibility
                $salesRepData = (object) $this->employeeCache[$compositeKey];
                // Using cached employee data with office context
            } else {
                // Fallback to database query with office context
                $salesRepData = FrEmployeeData::where('employee_id', $soldBy)
                    ->where('office_id', $officeId)
                    ->first();

                Log::info('Cache miss - queried database with office context', [
                    'employee_id' => $soldBy,
                    'office_id' => $officeId,
                ]);
            }

            // If no sales rep data found, log and return nulls
            if (! $salesRepData) {
                Log::warning('No FrEmployeeData found for soldBy', [
                    'employee_id' => $soldBy,
                    'context' => $context,
                ]);

                return [
                    'soldBy' => $soldBy,
                    'sales_rep_email' => null,
                    'closer1_id' => null,
                    'sales_rep_name' => null,
                    'sales_setter_name' => null,
                ];
            }

            // Get closer1_id from sequifi_id
            $closer1_id = null;
            if (property_exists($salesRepData, 'sequifi_id') && $salesRepData->sequifi_id) {
                $closer1_id = $salesRepData->sequifi_id;
                Log::info('Found sequifi_id for employee', [
                    'employee_id' => $soldBy,
                    'sequifi_id' => $closer1_id,
                ]);
            } else {
                Log::warning('FrEmployeeData found but sequifi_id is null', [
                    'employee_id' => $soldBy,
                    'fr_employee_data_id' => $salesRepData->id ?? null,
                    'email' => $salesRepData->email ?? 'no_email',
                ]);
            }

            // Format sales rep name
            $firstName = property_exists($salesRepData, 'fname') ?
                $salesRepData->fname : ($salesRepData->first_name ?? null);

            $lastName = property_exists($salesRepData, 'lname') ?
                $salesRepData->lname : ($salesRepData->last_name ?? null);

            $salesRepName = empty($firstName) && empty($lastName) ? null : trim($firstName . ' ' . $lastName);

            $result = [
                'soldBy' => $soldBy,
                'sales_rep_email' => $salesRepData->email ?? null,
                'closer1_id' => $closer1_id,
                'sales_rep_name' => $salesRepName,
                'sales_setter_name' => $salesRepName ? "sales_rep_sold_by - {$soldBy} email- " . ($salesRepData->email ?? '') : null,
            ];

            // Reduced logging for performance - sales rep transformation successful

            return $result;
        } catch (\Exception $e) {
            Log::error('Error transforming sales rep data', [
                'soldBy' => $soldBy,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'soldBy' => $soldBy,
                'sales_rep_email' => null,
                'closer1_id' => null,
                'sales_rep_name' => null,
                'sales_setter_name' => null,
            ];
        }
    }

    /**
     * Compute M1 date
     */
    private function computeM1Date(array $context): ?string
    {
        if (isset($context['initialStatusText']) && $context['initialStatusText'] == 'Completed') {
            // First check if scheduled_date is already in context
            if (! empty($context['scheduled_date'])) {
                return $context['scheduled_date'];
            }

            // If not, try to lookup appointment data using initialAppointmentID
            if (isset($context['initialAppointmentID'])) {
                $appointment = DB::table('FieldRoutes_Appointment_Data')
                    ->where('appointment_id', $context['initialAppointmentID'])
                    ->first(['scheduled_date', 'date_completed', 'date_added']);

                if ($appointment) {
                    // Try scheduled_date first
                    if (! empty($appointment->scheduled_date) && $appointment->scheduled_date !== '0000-00-00 00:00:00') {
                        return Carbon::parse($appointment->scheduled_date)->toDateString();
                    }
                    // If scheduled_date is NULL or empty, try date_completed
                    elseif (! empty($appointment->date_completed) && $appointment->date_completed !== '0000-00-00 00:00:00') {
                        return Carbon::parse($appointment->date_completed)->toDateString();
                    }
                    // If both are NULL, try date_added
                    elseif (! empty($appointment->date_added) && $appointment->date_added !== '0000-00-00 00:00:00') {
                        return Carbon::parse($appointment->date_added)->toDateString();
                    }
                }
            }
        }

        return null;
    }

    /**
     * Compute initial service date
     */
    private function computeInitialServiceDate(array $context): ?string
    {
        // First check if scheduled_date is already in context
        if (! empty($context['scheduled_date'])) {
            return $context['scheduled_date'];
        }

        // If not, try to lookup appointment data using initialAppointmentID
        if (isset($context['initialAppointmentID'])) {
            $appointment = DB::table('FieldRoutes_Appointment_Data')
                ->where('appointment_id', $context['initialAppointmentID'])
                ->first(['scheduled_date', 'date_completed', 'date_added']);

            if ($appointment) {
                // Try scheduled_date first
                if (! empty($appointment->scheduled_date) && $appointment->scheduled_date !== '0000-00-00 00:00:00') {
                    return Carbon::parse($appointment->scheduled_date)->toDateString();
                }
                // If scheduled_date is NULL or empty, try date_completed
                elseif (! empty($appointment->date_completed) && $appointment->date_completed !== '0000-00-00 00:00:00') {
                    return Carbon::parse($appointment->date_completed)->toDateString();
                }
                // If both are NULL, try date_added
                elseif (! empty($appointment->date_added) && $appointment->date_added !== '0000-00-00 00:00:00') {
                    return Carbon::parse($appointment->date_added)->toDateString();
                }
            }
        }

        return null;
    }

    /**
     * Transform customer name by combining first and last name
     *
     * @param  mixed  $value
     */
    private function transformCustomerName($value, array $context): string
    {
        // For fname, use the current value as fname
        if (isset($context['fname'])) {
            return trim($context['fname'] . ' ' . ($context['lname'] ?? ''));
        }

        // For lname, use the current value as lname
        if (isset($context['lname'])) {
            return trim(($context['fname'] ?? '') . ' ' . $context['lname']);
        }

        // If neither fname nor lname is in context, use the current value
        return trim($value);
    }

    /**
     * Get value from nested array using dot notation
     *
     * @return mixed
     */
    private function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $nestedKey) {
            if (! isset($value[$nestedKey])) {
                return null;
            }
            $value = $value[$nestedKey];
        }

        return $value;
    }

    /**
     * Save the processed data to the legacy system
     *
     * @return int Number of records saved
     */
    protected function saveProcessedData(array $processedData): int
    {
        $savedCount = 0;
        $errors = [];
        $batchSize = 100;

        // Process in batches
        foreach (array_chunk($processedData, $batchSize) as $batch) {
            try {
                DB::beginTransaction();

                foreach ($batch as $record) {
                    try {
                        // Removed excessive logging - only batch progress is logged

                        // Validate required fields
                        $missingFields = $this->validateRequiredFields($record);
                        if (! empty($missingFields)) {
                            $this->recordFailure(
                                $record,
                                FieldRoutesFailedRecord::FAILURE_TYPE_MISSING_DATA,
                                'Missing required fields',
                                'Missing required fields: ' . implode(', ', $missingFields)
                            );

                            continue;
                        }

                        // Convert any array values to strings
                        $recordToSave = [];
                        foreach ($record as $key => $value) {
                            if (is_array($value)) {
                                $recordToSave[$key] = implode(' ', $value);
                            } else {
                                $recordToSave[$key] = $value;
                            }
                        }

                        // Insert the record using Eloquent to trigger observer
                        \App\Models\LegacyApiRawDataHistory::create($recordToSave);
                        $savedCount++;

                        // Removed excessive individual record logging
                    } catch (Exception $e) {
                        $errors[] = [
                            'error' => $e->getMessage(),
                            'record' => $record,
                        ];
                        Log::error('Error saving record: ' . $e->getMessage());

                        $this->recordFailure(
                            $record,
                            FieldRoutesFailedRecord::FAILURE_TYPE_VALIDATION,
                            'Failed to save record',
                            $e->getMessage()
                        );
                    }
                }

                DB::commit();
                Log::info('Successfully saved batch of ' . count($batch) . " records (total: {$savedCount})");
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Error saving batch: ' . $e->getMessage());

                foreach ($batch as $record) {
                    $this->recordFailure(
                        $record,
                        FieldRoutesFailedRecord::FAILURE_TYPE_VALIDATION,
                        'Batch save failed',
                        $e->getMessage()
                    );
                }
            }
        }

        if (! empty($errors)) {
            Log::warning('Encountered ' . count($errors) . ' errors while saving');
            Log::error('Errors saving records', ['errors' => $errors]);
        }

        return $savedCount;
    }

    /**
     * Validate required fields in a record
     *
     * @return array Missing required fields
     */
    private function validateRequiredFields(array $record): array
    {
        // Get required fields from mapping config
        $requiredFields = [];

        // API to DB mappings
        foreach ($this->mappingConfig['field_mappings']['api_to_db'] as $apiField => $mapping) {
            if (! empty($mapping['required'])) {
                if (isset($mapping['db_field'])) {
                    $requiredFields[] = $mapping['db_field'];
                }
                // Only include db_fields if the parent field is required
                if (isset($mapping['db_fields'])) {
                    foreach ($mapping['db_fields'] as $dbField) {
                        $requiredFields[] = $dbField;
                    }
                }
            }
        }

        // Customer API fields
        foreach ($this->mappingConfig['field_mappings']['customer_api_fields'] as $apiField => $mapping) {
            if (! empty($mapping['required'])) {
                $requiredFields[] = $mapping['db_field'];
            }
        }

        // Computed fields
        foreach ($this->mappingConfig['field_mappings']['computed_fields'] as $field => $config) {
            if (! empty($config['required'])) {
                $requiredFields[] = $field;
            }
        }

        // Log the required fields for debugging
        Log::info('Required fields from config', [
            'fields' => array_unique($requiredFields),
        ]);

        $missingFields = [];
        foreach (array_unique($requiredFields) as $field) {
            if (! isset($record[$field]) || $record[$field] === null || $record[$field] === '') {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            Log::warning('Missing required fields', [
                'missing' => $missingFields,
                'record_fields' => array_keys($record),
            ]);
        }

        return $missingFields;
    }

    /**
     * Process special fields that require complex logic
     *
     * @param  array  $record  The record to process
     * @return array The processed record
     */
    protected function processSpecialFields(array $record): array
    {
        try {
            // Process product_id and product_code
            if (isset($record['product'])) {
                $productCode = isset($record['product']) && $record['product'] != ''
                    ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $record['product']))
                    : null;
                $product = ProductCode::where('product_code', $productCode)->first();
                if ($product) {
                    $record['product_id'] = $product->product_id;
                    $record['product_code'] = $product->product_code;
                } else {
                    $defaultProductId = config('global_vars.DEFAULT_PRODUCT_ID') ?? 'DBP';
                    $product = Products::where('product_id', $defaultProductId)->first();
                    if ($product) {
                        $record['product_id'] = $product->id;
                        $record['product_code'] = $product->product_id;
                    }
                }
            }

            // Get original status FIRST to determine if we should fetch service dates
            // Use original status to determine if service was actually completed
            $originalStatus = $record['_original_initial_status'] ?? $record['initialStatusText'] ?? '';

            // Get initial_service_date from FieldRoutes_Appointment_Data
            // Only fetch if service status is "Completed" to maintain data consistency
            $initial_service_date = null;

            if ($originalStatus == 'Completed') {
                if (isset($record['initialAppointmentID'])) {
                    $appointment = DB::table('FieldRoutes_Appointment_Data')
                        ->where('appointment_id', $record['initialAppointmentID'])
                        ->first();

                    if ($appointment) {
                        // Try scheduled_date first
                        if (! empty($appointment->scheduled_date) && $appointment->scheduled_date !== '0000-00-00 00:00:00') {
                            $initial_service_date = Carbon::parse($appointment->scheduled_date)->toDateString();
                        }
                        // If scheduled_date is NULL or empty, try date_completed
                        elseif (! empty($appointment->date_completed) && $appointment->date_completed !== '0000-00-00 00:00:00') {
                            $initial_service_date = Carbon::parse($appointment->date_completed)->toDateString();
                        }
                        // If both are NULL, try date_added
                        elseif (! empty($appointment->date_added) && $appointment->date_added !== '0000-00-00 00:00:00') {
                            $initial_service_date = Carbon::parse($appointment->date_added)->toDateString();
                        }
                    }
                }

                // If no initial_service_date from appointment, try to get it from other sources
                if (! $initial_service_date) {
                    // Try customer_signoff first (this is the subscription date_added)
                    if (! empty($record['customer_signoff']) && $record['customer_signoff'] != '0000-00-00 00:00:00') {
                        $initial_service_date = Carbon::parse($record['customer_signoff'])->toDateString();
                    }
                    // Then try dateAdded
                    elseif (! empty($record['date_added']) && $record['date_added'] != '0000-00-00 00:00:00') {
                        $initial_service_date = Carbon::parse($record['date_added'])->toDateString();
                    }
                    // Then try dateUpdated
                    elseif (! empty($record['date_updated']) && $record['date_updated'] != '0000-00-00 00:00:00') {
                        $initial_service_date = Carbon::parse($record['date_updated'])->toDateString();
                    }
                }
            }

            $record['initial_service_date'] = $initial_service_date;

            // NOTE: job_status is now handled by the compute_job_status transformer in field mappings
            // No need to override it here - the computeJobStatus method handles all logic including Frozen status

            // Process trigger_date - Set it based on ORIGINAL status before cancellation override
            $trigger_date = [
                [
                    'date' => null,
                ],
            ];

            // Set trigger_date if service was originally completed and we have a valid service date
            if ($originalStatus == 'Completed' && ! empty($initial_service_date)) {
                $trigger_date = [
                    [
                        'date' => $initial_service_date,
                    ],
                ];

                // Use cached M2 milestone date (pre-fetched to avoid N+1 queries)
                // NOTE: At this stage, field might be 'subscriptionID' or 'pid'
                $pidValue = $record['pid'] ?? $record['subscriptionID'] ?? null;

                if (!empty($pidValue)) {
                    // Check if M2 date exists in cached data
                    $m2Milestone = $this->cachedM2Dates[$pidValue] ?? null;

                    // If not in cache, try direct query (fallback for newly created sales)
                    if (empty($m2Milestone)) {
                        try {
                            $m2Milestone = DB::table('sale_product_master')
                                ->where('pid', $pidValue)
                                ->where('type', 'm2')
                                ->whereNotNull('milestone_date')
                                ->value('milestone_date');

                            if (!empty($m2Milestone)) {
                                Log::info('M2 milestone found via fallback query (not in cache)', [
                                    'pid' => $pidValue,
                                    'm2_date' => $m2Milestone,
                                ]);
                            }
                        } catch (Exception $e) {
                            Log::error('Failed to fetch M2 milestone date for PID', [
                                'pid' => $pidValue,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    if (!empty($m2Milestone)) {
                        // Validate m2 milestone date before adding to trigger_date
                        if ($this->isValidDate($m2Milestone)) {
                            $trigger_date[] = [
                                'date' => $m2Milestone,
                            ];

                            Log::debug('Added M2 milestone to trigger_date', [
                                'pid' => $pidValue,
                                'm2_date' => $m2Milestone,
                                'source' => isset($this->cachedM2Dates[$pidValue]) ? 'cache' : 'fallback_query',
                            ]);
                        } else {
                            Log::warning('Invalid m2 milestone date found', [
                                'pid' => $pidValue,
                                'm2_milestone_date' => $m2Milestone,
                            ]);
                        }
                    }
                } else {
                    Log::warning('PID not available for m2 milestone lookup', [
                        'record_keys' => array_keys($record),
                    ]);
                }
            }
            $record['trigger_date'] = json_encode($trigger_date);

            // Clean up the temporary field
            unset($record['_original_initial_status']);

            return $record;
        } catch (Exception $e) {
            Log::error('Error in processSpecialFields', [
                'error' => $e->getMessage(),
                'record' => $record,
            ]);
            throw $e;
        }
    }

    /**
     * Transform product information
     */
    private function transformProduct(string $serviceType): array
    {
        $productCode = null;
        if (! empty($serviceType)) {
            $productCode = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $serviceType));
        }

        $productCodeRecord = ProductCode::where('product_code', $productCode)->first();

        if (! $productCodeRecord) {
            $defaultProductId = config('global_vars.DEFAULT_PRODUCT_ID');
            $product = Products::where('product_id', $defaultProductId)->first();
            if ($product) {
                $productCodeRecord = $product->productCodes()->first();
            }
        }

        return [
            'product' => $serviceType,
            'product_id' => $productCodeRecord ? $productCodeRecord->product_id : null,
            'product_code' => $productCodeRecord ? $productCodeRecord->product_code : null,
        ];
    }

    /**
     * Check if the last appointment for a subscription is cancelled
     */
    private function isLastAppointmentCancelled(int $subscriptionId): bool
    {
        try {
            // Get the last appointment for this subscription (by date_added)
            $lastAppointment = DB::table('FieldRoutes_Appointment_Data')
                ->where('subscription_id', $subscriptionId)
                ->orderBy('date_added', 'desc')
                ->first(['status_text']);

            if (! $lastAppointment) {
                // No appointments found - do NOT treat as cancelled, return false
                // Only return cancelled when there's an actual cancelled appointment
                return false;
            }

            // Check if the last appointment's status is "Cancelled"
            return $lastAppointment->status_text === 'Cancelled';
        } catch (\Exception $e) {
            // Log error and return false to be conservative
            // Only return cancelled when we can confirm appointment is cancelled
            if (Log::getDefaultDriver() !== 'null') {
                Log::error("Error checking appointment status for subscription {$subscriptionId}: " . $e->getMessage());
            }

            return false;
        }
    }

    /**
     * Get the actual cancellation date from the appointment data
     */
    private function getAppointmentCancellationDate(int $subscriptionId): ?string
    {
        try {
            // Get the last cancelled appointment for this subscription
            $lastCancelledAppointment = DB::table('FieldRoutes_Appointment_Data')
                ->where('subscription_id', $subscriptionId)
                ->where('status_text', 'Cancelled')
                ->orderBy('date_added', 'desc')
                ->first(['date_cancelled']);

            if (
                $lastCancelledAppointment &&
                ! empty($lastCancelledAppointment->date_cancelled) &&
                $lastCancelledAppointment->date_cancelled != '0000-00-00 00:00:00'
            ) {
                try {
                    $carbonDate = Carbon::parse($lastCancelledAppointment->date_cancelled);
                    if ($carbonDate->year < 1000) {
                        Log::warning('Invalid appointment cancellation date', [
                            'subscription_id' => $subscriptionId,
                            'value' => $lastCancelledAppointment->date_cancelled,
                        ]);

                        return null;
                    }

                    return $carbonDate->toDateString();
                } catch (\Exception $e) {
                    Log::warning('Failed to parse appointment cancellation date', [
                        'subscription_id' => $subscriptionId,
                        'value' => $lastCancelledAppointment->date_cancelled,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error getting appointment cancellation date for subscription {$subscriptionId}: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Transform dateCancelled with conditional cancellation logic
     * Only set date_cancelled if the last appointment is actually cancelled
     *
     * @param  mixed  $value  The dateCancelled value from API
     * @param  array  $context  The full record context
     */
    private function transformConditionalCancellation($value, array $context): ?string
    {

        // Get subscription ID from context
        $subscriptionId = $context['subscriptionID'] ?? null;
        if (! $subscriptionId) {
            Log::warning('No subscriptionID in context for conditional cancellation transform');

            return null;
        }

        // Enhanced context checking - look for active_text in multiple possible locations
        $activeText = $context['active_text'] ?? $context['activeText'] ?? null;

        // Debug logging to understand what's in context
        if (! $activeText) {
            Log::debug('No active_text found in direct context', [
                'subscription_id' => $subscriptionId,
                'context_keys' => array_keys($context),
                'has_active_text' => isset($context['active_text']),
                'has_activeText' => isset($context['activeText']),
            ]);

            // Try to get active_text directly from FieldRoutes_Raw_Data
            try {
                $rawData = DB::table('FieldRoutes_Raw_Data')
                    ->where('subscription_id', $subscriptionId)
                    ->select('active_text')
                    ->first();

                if ($rawData && ! empty($rawData->active_text)) {
                    $activeText = $rawData->active_text;
                    Log::info('Retrieved active_text from database', [
                        'subscription_id' => $subscriptionId,
                        'active_text' => $activeText,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to retrieve active_text from database', [
                    'subscription_id' => $subscriptionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // NEW LOGIC: If status is Frozen and there's no cancel date, set it to today
        if (! empty($activeText) && strtolower(trim($activeText)) === 'frozen') {
            // Check if dateCancelled is empty or invalid
            if ($this->isInvalidDate($value)) {
                $todayDate = Carbon::now()->format('Y-m-d H:i:s');
                Log::info('Setting cancel date to today for Frozen job with no cancel date', [
                    'subscription_id' => $subscriptionId,
                    'active_text' => $activeText,
                    'date_cancelled' => $todayDate,
                ]);

                return $todayDate;
            }
        }

        // If no dateCancelled value or it's invalid, return null
        if ($this->isInvalidDate($value)) {
            return null;
        }

        // Priority 1: Check if subscription is Frozen or Cancelled (effectively cancelled)
        if (
            ! empty($activeText) &&
            (strtolower(trim($activeText)) == 'frozen' ||
                strtolower(trim($activeText)) == 'cancelled')
        ) {

            // Frozen or Cancelled subscriptions with dateCancelled are treated as cancelled
            try {
                // Return full datetime format for consistency with datetime type casting
                $dateResult = Carbon::parse($value)->format('Y-m-d H:i:s');
                Log::info('Setting date_cancelled for Frozen/Cancelled subscription via transform', [
                    'subscription_id' => $subscriptionId,
                    'active_text' => $activeText,
                    'date_cancelled' => $dateResult,
                    'original_value' => $value,
                ]);

                return $dateResult;
            } catch (\Exception $e) {
                Log::error('Error parsing dateCancelled value for Frozen/Cancelled subscription', [
                    'subscription_id' => $subscriptionId,
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        // Priority 2: Check if the last appointment for this subscription is cancelled
        if ($this->isLastAppointmentCancelled($subscriptionId)) {
            // Last appointment is cancelled, so use the subscription dateCancelled
            try {
                $dateResult = Carbon::parse($value)->format('Y-m-d H:i:s');
                Log::info('Setting date_cancelled for appointment cancellation', [
                    'subscription_id' => $subscriptionId,
                    'date_cancelled' => $dateResult,
                    'original_value' => $value,
                ]);

                return $dateResult;
            } catch (\Exception $e) {
                Log::error('Error parsing dateCancelled value', [
                    'subscription_id' => $subscriptionId,
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        } else {
            // Last appointment is NOT cancelled and not Frozen/Cancelled, ignore subscription dateCancelled
            Log::info('Ignoring subscription dateCancelled - not Frozen/Cancelled and appointments not cancelled', [
                'subscription_id' => $subscriptionId,
                'active_text' => $activeText ?? 'null',
                'subscription_date_cancelled' => $value,
            ]);

            return null;
        }
    }

    /**
     * Check if a subscription should be marked as cancelled based on Frozen status or appointment cancellation
     *
     * @param  object|array  $record  The subscription record (object in main transform, array in processSpecialFields)
     */
    private function shouldBeMarkedAsCancelled($record): bool
    {
        // Handle both object and array formats - check both possible subscription ID fields
        $subscriptionId = null;
        if (is_object($record)) {
            $subscriptionId = $record->subscriptionID ?? $record->pid ?? null;
        } else {
            $subscriptionId = $record['subscriptionID'] ?? $record['pid'] ?? null;  // Fixed: check subscriptionID first
        }

        if (! $subscriptionId) {
            return false;
        }

        // Get values from record (handle both object and array formats)
        $activeText = is_object($record) ? ($record->active_text ?? null) : ($record['active_text'] ?? null);
        $dateCancelled = is_object($record) ? ($record->dateCancelled ?? null) : ($record['dateCancelled'] ?? null);

        // Priority 1: Check if subscription is Frozen or Cancelled with dateCancelled
        if (
            ! empty($activeText) &&
            (strtolower(trim($activeText)) === 'frozen' ||
                strtolower(trim($activeText)) === 'cancelled') &&
            ! empty($dateCancelled) &&
            $dateCancelled != '0000-00-00 00:00:00'
        ) {
            return true;
        }

        // Priority 2: Check if last appointment is cancelled
        return $this->isLastAppointmentCancelled($subscriptionId);
    }

    /**
     * Get the reason why a subscription is being marked as cancelled
     *
     * @param  object|array  $record  The subscription record (object in main transform, array in processSpecialFields)
     */
    private function getCancellationReason($record): string
    {
        // Handle both object and array formats - check both possible subscription ID fields
        $subscriptionId = null;
        if (is_object($record)) {
            $subscriptionId = $record->subscriptionID ?? $record->pid ?? null;
        } else {
            $subscriptionId = $record['subscriptionID'] ?? $record['pid'] ?? null;  // Fixed: check subscriptionID first
        }

        $activeText = is_object($record) ? ($record->active_text ?? null) : ($record['active_text'] ?? null);
        $dateCancelled = is_object($record) ? ($record->dateCancelled ?? null) : ($record['dateCancelled'] ?? null);

        // Check if it's because of Frozen or Cancelled status
        if (
            ! empty($activeText) &&
            (strtolower(trim($activeText)) === 'frozen' ||
                strtolower(trim($activeText)) === 'cancelled') &&
            ! empty($dateCancelled) &&
            $dateCancelled != '0000-00-00 00:00:00'
        ) {
            return 'Frozen/Cancelled subscription with dateCancelled';
        }

        // Check if it's because of appointment cancellation
        if ($subscriptionId && $this->isLastAppointmentCancelled($subscriptionId)) {
            return 'Last appointment is cancelled';
        }

        return 'Unknown cancellation reason';
    }

    /**
     * Determine import_to_sales status based on sales rep validity and date criteria
     */
    protected function determineImportToSales(array $subscriptionDetail, ?string $customerSignoff): int
    {
        $domain = $this->domainName ?? config('app.domain_name');
        // PRIORITY 1: Special handling for frozen/cancelled records - they should ALWAYS be processed
        // This must come BEFORE sales rep validation to ensure frozen records are processed regardless of sales rep status
        $activeText = $subscriptionDetail['active_text'] ?? '';

        if ($domain != 'homeguard') {
            if (strtolower(trim($activeText)) === 'frozen' || strtolower(trim($activeText)) === 'cancelled') {
                // Frozen/cancelled records should be processed as pending (0) to update their status in legacy system
                Log::info('Processing frozen/cancelled record (bypassing sales rep validation)', [
                    'subscription_id' => $subscriptionDetail['subscriptionID'] ?? $subscriptionDetail['pid'] ?? 'unknown',
                    'active_text' => $activeText,
                ]);

                return 0;
            }
        } else {
            if (
                empty($customerSignoff) ||
                $customerSignoff === '0000-00-00' ||
                $customerSignoff === '0000-00-00 00:00:00' ||
                trim($customerSignoff) === ''
            ) {

                // Try to get valid date from subscription data sources
                $fallbackDate = null;

                // Try dateAdded first
                if (
                    ! empty($subscriptionDetail['dateAdded']) &&
                    $subscriptionDetail['dateAdded'] != '0000-00-00 00:00:00'
                ) {
                    try {
                        $fallbackDate = Carbon::parse($subscriptionDetail['dateAdded'])->toDateString();
                    } catch (Exception $e) {
                        // Continue to next fallback
                    }
                }

                // Try last_modified if dateAdded failed
                if (
                    ! $fallbackDate && ! empty($subscriptionDetail['last_modified']) &&
                    $subscriptionDetail['last_modified'] != '0000-00-00 00:00:00'
                ) {
                    try {
                        $fallbackDate = Carbon::parse($subscriptionDetail['last_modified'])->toDateString();
                    } catch (Exception $e) {
                        // Continue to validation failure
                    }
                }

                // If we found a valid fallback date, use it for validation
                if ($fallbackDate) {
                    $customerSignoff = $fallbackDate;
                } else {
                    $customerSignoff = null;
                }

                if (! $customerSignoff) {
                    return 0; // No customer signoff date, mark as pending
                }
                $signoffDate = Carbon::parse($customerSignoff);
                return $signoffDate >= Carbon::parse('2025-12-11') ? 0 : 2;
            }
        }

        // PRIORITY 1.5: Special handling for exempt domains - NEVER mark as failed
        // All records should be processed as pending (0) regardless of sales rep or date

        if (in_array($domain, self::EXEMPT_DOMAINS, true)) {
            return 0;
        }

        // PRIORITY 2: Check if we have a valid sales rep (closer1_id) for non-frozen records
        // Use employee cache to avoid N+1 queries
        $soldBy = $subscriptionDetail['soldBy'] ?? null;
        $officeId = $subscriptionDetail['officeId'] ?? null;
        $salesRepData = null;

        if ($soldBy) {
            // Strict mode: office_id is required for accurate lookups
            if (!$officeId) {
                Log::warning("Missing office_id for employee lookup in determineImportToSales", [
                    'employee_id' => $soldBy,
                    'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                    'message' => 'office_id is required to prevent cross-office employee mismatches'
                ]);
                // Set salesRepData to null to trigger failed import
                $salesRepData = null;
            } else {
                // Use composite key with office context
                $compositeKey = $soldBy . '_' . $officeId;
                if (isset($this->employeeCache[$compositeKey])) {
                    $cachedData = $this->employeeCache[$compositeKey];
                    if (! empty($cachedData['sequifi_id'])) {
                        $salesRepData = (object) $cachedData;
                    }
                } else {
                    // Fallback to database query with office context
                    $salesRepData = FrEmployeeData::where('employee_id', $soldBy)
                        ->where('office_id', $officeId)
                        ->whereNotNull('sequifi_id')
                        ->first();

                    Log::warning('Employee cache miss in determineImportToSales', [
                        'employee_id' => $soldBy,
                        'office_id' => $officeId,
                        'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                    ]);
                }
            }
        }

        if (! $salesRepData) {
            // No valid sales rep found, mark as failed but log the reason
            Log::info('Invalid sales rep - marking as failed import', [
                'subscription_id' => $subscriptionDetail['subscriptionID'] ?? $subscriptionDetail['pid'] ?? 'unknown',
                'employee_id' => $soldBy,
                'reason' => 'No valid sales rep found with sequifi_id',
            ]);

            return 2;
        }

        // We have a valid sales rep, now check date criteria based on domain
        // Enhanced validation for customer_signoff field - check multiple sources same as determineImportStatusReason and determineImportStatusDescription
        if ($domain === 'homeguard') {
            if (
                empty($customerSignoff) ||
                $customerSignoff === '0000-00-00' ||
                $customerSignoff === '0000-00-00 00:00:00' ||
                trim($customerSignoff) === ''
            ) {

                // Try to get valid date from subscription data sources
                $fallbackDate = null;

                // Try dateAdded first
                if (
                    ! empty($subscriptionDetail['dateAdded']) &&
                    $subscriptionDetail['dateAdded'] != '0000-00-00 00:00:00'
                ) {
                    try {
                        $fallbackDate = Carbon::parse($subscriptionDetail['dateAdded'])->toDateString();
                    } catch (Exception $e) {
                        // Continue to next fallback
                    }
                }

                // Try last_modified if dateAdded failed
                if (
                    ! $fallbackDate && ! empty($subscriptionDetail['last_modified']) &&
                    $subscriptionDetail['last_modified'] != '0000-00-00 00:00:00'
                ) {
                    try {
                        $fallbackDate = Carbon::parse($subscriptionDetail['last_modified'])->toDateString();
                    } catch (Exception $e) {
                        // Continue to validation failure
                    }
                }

                // If we found a valid fallback date, use it for validation
                if ($fallbackDate) {
                    $customerSignoff = $fallbackDate;
                } else {
                    $customerSignoff = null;
                }
            }
        }

        if (! $customerSignoff) {
            return 0; // No customer signoff date, mark as pending
        }

        $signoffDate = Carbon::parse($customerSignoff);

        switch ($domain) {
            case 'whiteknight':
                // For WhiteKnight: if signoff date >= 2025-03-01, mark as pending (0), otherwise failed (2)
                return $signoffDate >= Carbon::parse('2025-03-01') ? 0 : 2;

            case 'moxie':
                // For Moxie: if signoff date >= 2024-07-01, mark as pending (0), otherwise failed (2)
                return $signoffDate >= Carbon::parse('2024-07-01') ? 0 : 2;

            case 'hawx':
                // For Hawx: if signoff date >= 2024-07-01, mark as pending (0), otherwise failed (2)
                return $signoffDate >= Carbon::parse('2024-07-01') ? 0 : 2;

            case 'homeguard':
                // For HomeGuard: if signoff date >= 2025-12-11, mark as pending (0), otherwise failed (2)
                return $signoffDate >= Carbon::parse('2025-12-11') ? 0 : 2;

            case 'insightpestfl':
            case 'insightpestmt':
            case 'insightpestal':
                // For Insight Pest PEN, AL & MT: if signoff date >= 2026-01-01, mark as pending (0), otherwise failed (2)
                return $signoffDate >= Carbon::parse('2026-01-01') ? 0 : 2;

            default:
                // For other domains: mark as pending by default
                return 0;
        }
    }

    /**
     * Determine import status reason for failed records
     */
    protected function determineImportStatusReason(array $subscriptionDetail, ?string $customerSignoff): ?string
    {
        // Special handling for exempt domains - no failure reasons
        $domain = $this->domainName ?? config('app.domain_name');

        if (in_array($domain, self::EXEMPT_DOMAINS, true)) {
            return null; // No failure reasons for these domains
        }

        // Check if we have a valid sales rep
        $soldBy = $subscriptionDetail['soldBy'] ?? null;
        $officeId = $subscriptionDetail['officeId'] ?? null;

        // Strict mode: office_id is required for accurate lookups
        if (!$officeId) {
            Log::warning("Missing office_id for employee lookup in determineImportStatusReason", [
                'employee_id' => $soldBy,
                'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                'message' => 'office_id is required to prevent cross-office employee mismatches'
            ]);
            return 'Invalid sales rep - missing office_id';
        }

        // Query with office context
        $salesRepData = FrEmployeeData::where('employee_id', $soldBy)
            ->where('office_id', $officeId)
            ->whereNotNull('sequifi_id')
            ->first();

        if (! $salesRepData) {
            return 'Invalid sales rep';
        }

        // Enhanced validation for customer_signoff field - check multiple sources
        if (
            empty($customerSignoff) ||
            $customerSignoff === '0000-00-00' ||
            $customerSignoff === '0000-00-00 00:00:00' ||
            trim($customerSignoff) === ''
        ) {

            // Try to get valid date from subscription data sources
            $fallbackDate = null;

            // Try dateAdded first
            if (
                ! empty($subscriptionDetail['dateAdded']) &&
                $subscriptionDetail['dateAdded'] != '0000-00-00 00:00:00'
            ) {
                try {
                    $fallbackDate = Carbon::parse($subscriptionDetail['dateAdded'])->toDateString();
                } catch (Exception $e) {
                    // Continue to next fallback
                }
            }

            // Try last_modified if dateAdded failed
            if (
                ! $fallbackDate && ! empty($subscriptionDetail['last_modified']) &&
                $subscriptionDetail['last_modified'] != '0000-00-00 00:00:00'
            ) {
                try {
                    $fallbackDate = Carbon::parse($subscriptionDetail['last_modified'])->toDateString();
                } catch (Exception $e) {
                    // Continue to validation failure
                }
            }

            // If we found a valid fallback date, use it for validation
            if ($fallbackDate) {
                $customerSignoff = $fallbackDate;
            } else {
                return 'Missing customer signoff date';
            }
        }

        // Check date criteria
        $domain = config('app.domain_name');
        $signoffDate = Carbon::parse($customerSignoff);

        switch ($domain) {
            case 'insightpestfl':
            case 'insightpestmt':
            case 'insightpestal':
                if ($signoffDate < Carbon::parse('2026-01-01')) {
                    return 'Date before 2026-01-01 cutoff for Insight Pest PEN, AL & MT';
                }
                break;
            case 'whiteknight':
                if ($signoffDate < Carbon::parse('2025-03-01')) {
                    return 'Date before 2025-03-01 cutoff for WhiteKnight';
                }
                break;
            case 'homeguard':
                if ($signoffDate < Carbon::parse('2025-12-11')) {
                    return 'Date before 2025-12-11 cutoff for HomeGuard';
                }
                break;

            case 'moxie':
            case 'hawx':
                if ($signoffDate < Carbon::parse('2024-07-01')) {
                    return 'Date before 2024-07-01 cutoff for ' . ucfirst($domain);
                }
                break;
        }

        return null; // No failure reason
    }

    /**
     * Determine import status description for failed records
     */
    protected function determineImportStatusDescription(array $subscriptionDetail, ?string $customerSignoff): ?string
    {
        // Special handling for exempt domains - no failure descriptions
        $domain = $this->domainName ?? config('app.domain_name');

        if (in_array($domain, self::EXEMPT_DOMAINS, true)) {
            return null; // No failure descriptions for these domains
        }

        // Check if we have a valid sales rep
        $soldBy = $subscriptionDetail['soldBy'] ?? null;
        $officeId = $subscriptionDetail['officeId'] ?? null;

        // Strict mode: office_id is required for accurate lookups
        if (!$officeId) {
            Log::warning("Missing office_id for employee lookup in determineImportStatusDescription", [
                'employee_id' => $soldBy,
                'subscription_id' => $subscriptionDetail['subscriptionID'] ?? 'unknown',
                'message' => 'office_id is required to prevent cross-office employee mismatches'
            ]);
            return "Sales rep ID {$soldBy} lookup failed - missing office_id for accurate employee matching";
        }

        // Query with office context
        $salesRepData = FrEmployeeData::where('employee_id', $soldBy)
            ->where('office_id', $officeId)
            ->whereNotNull('sequifi_id')
            ->first();

        if (! $salesRepData) {
            return "Sales rep ID {$soldBy} not found in FrEmployeeData or has null sequifi_id for office_id {$officeId}";
        }

        // Enhanced validation for customer_signoff field - check multiple sources
        if (
            empty($customerSignoff) ||
            $customerSignoff === '0000-00-00' ||
            $customerSignoff === '0000-00-00 00:00:00' ||
            trim($customerSignoff) === ''
        ) {

            // Try to get valid date from subscription data sources
            $fallbackDate = null;

            // Try dateAdded first
            if (
                ! empty($subscriptionDetail['dateAdded']) &&
                $subscriptionDetail['dateAdded'] != '0000-00-00 00:00:00'
            ) {
                try {
                    $fallbackDate = Carbon::parse($subscriptionDetail['dateAdded'])->toDateString();
                } catch (Exception $e) {
                    // Continue to next fallback
                }
            }

            // Try last_modified if dateAdded failed
            if (
                ! $fallbackDate && ! empty($subscriptionDetail['last_modified']) &&
                $subscriptionDetail['last_modified'] != '0000-00-00 00:00:00'
            ) {
                try {
                    $fallbackDate = Carbon::parse($subscriptionDetail['last_modified'])->toDateString();
                } catch (Exception $e) {
                    // Continue to validation failure
                }
            }

            // If we found a valid fallback date, use it for validation
            if ($fallbackDate) {
                $customerSignoff = $fallbackDate;
            } else {
                return 'Customer signoff date is required for import processing';
            }
        }

        // Check date criteria
        $domain = config('app.domain_name');
        $signoffDate = Carbon::parse($customerSignoff);

        switch ($domain) {
            case 'whiteknight':
                if ($signoffDate < Carbon::parse('2025-03-01')) {
                    return "Customer signoff date {$customerSignoff} is before the WhiteKnight cutoff date of 2025-03-01";
                }
                break;

            case 'moxie':
                if ($signoffDate < Carbon::parse('2024-07-01')) {
                    return "Customer signoff date {$customerSignoff} is before the Moxie cutoff date of 2024-07-01";
                }
                break;

            case 'hawx':
                if ($signoffDate < Carbon::parse('2024-07-01')) {
                    return "Customer signoff date {$customerSignoff} is before the Hawx cutoff date of 2024-07-01";
                }
                break;

            case 'homeguard':
                if ($signoffDate < Carbon::parse('2025-12-11')) {
                    return "Customer signoff date {$customerSignoff} is before the HomeGuard cutoff date of 2025-12-11";
                }
                break;

            case 'insightpestfl':
            case 'insightpestmt':
            case 'insightpestal':
                if ($signoffDate < Carbon::parse('2026-01-01')) {
                    return "Customer signoff date {$customerSignoff} is before the Insight Pest PEN, AL & MT cutoff date of 2026-01-01";
                }
                break;
        }

        return null; // No failure description
    }

    /**
     * Check if a date value is invalid or empty
     *
     * @param  mixed  $value
     */
    private function isInvalidDate($value): bool
    {
        if (empty($value)) {
            return true;
        }

        // Check for zero dates (common in MySQL)
        if (strpos($value, '0000-') === 0) {
            return true;
        }

        // Try to parse and validate the year
        try {
            $parsedDate = Carbon::parse($value);

            return $parsedDate->year < 1000;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Check if a date value is valid and not empty
     *
     * @param  mixed  $value
     */
    private function isValidDate($value): bool
    {
        return !$this->isInvalidDate($value);
    }
}
