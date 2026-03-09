<?php

namespace App\Services;

use App\Models\HometeamJsonRawData;
use App\Models\Products;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JsonImportServiceDumpData
{
    /**
     * Validate and process JSON content
     *
     * @param  string  $jsonContent  Raw JSON content
     * @param  string  $filename  Filename for error reporting
     * @return array Validated and processed JSON array
     *
     * @throws \InvalidArgumentException When JSON is invalid
     */
    public function validateAndProcessJson(string $jsonContent, string $filename): array
    {
        // Sanitize JSON content before decoding
        $jsonContent = $this->sanitizeJsonContent($jsonContent);

        // Decode JSON content
        $jsonArray = json_decode($jsonContent, true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = json_last_error_msg();
            Log::error("JSON parse error in file {$filename}: {$errorMsg}");
            throw new \InvalidArgumentException("Invalid JSON format: {$errorMsg}");
        }

        // Validate that we have an array
        if (! is_array($jsonArray)) {
            throw new \InvalidArgumentException('JSON content is not an array');
        }

        // Check for empty array
        if (empty($jsonArray)) {
            Log::warning("Empty JSON array in file: {$filename}");

            return [];
        }

        // Apply sanitization to all elements in the array
        $jsonArray = $this->sanitizeJsonArray($jsonArray);

        return $jsonArray;
    }

    /**
     * Process and import a sale record from the JSON data
     * If PID exists, compare specific fields and update or skip as needed
     * If PID doesn't exist, insert directly
     *
     * @param  array  $json  The sale data to import
     * @param  bool  $save  Whether to immediately save the record or return the prepared object
     * @return HometeamJsonRawData The created or updated sale record
     *
     * @throws \InvalidArgumentException When required fields are missing
     */
    public function processSaleData(array $json, bool $save = true): HometeamJsonRawData
    {
        // Sanitize input data before processing
        $json = $this->sanitizeInputData($json);

        $now = Carbon::now();
        $requiredFields = [
            'LocationID', 'LocationCode', 'FName', 'LName', 'SoldDate', 'SalesEmail',
        ];

        // Validate required fields
        foreach ($requiredFields as $field) {
            if (! isset($json[$field]) || empty($json[$field])) {
                throw new \InvalidArgumentException("Required field missing from JSON data: {$field}");
            }
        }

        $descriptionValue = $this->getJsonValue($json, 'Description');
        $productCode = ($descriptionValue !== null && $descriptionValue != '') ? strtolower(str_replace(' ', '', $descriptionValue)) : null;

        // Use eager loading to reduce database queries
        static $productsCache = [];

        if (! isset($productsCache[$productCode])) {
            $product = Products::withTrashed()->where('product_id', $productCode)->first();
            if (! $product) {
                if (! isset($productsCache['default'])) {
                    $productsCache['default'] = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                }
                $product = $productsCache['default'];
            }
            $productsCache[$productCode] = $product;
        } else {
            $product = $productsCache[$productCode];
        }

        $product_id = $product->id;

        $m1_date = $this->parseDate($this->getJsonValue($json, 'InitialServiceCompleted'));
        $last_service_date = $this->parseDate($this->getJsonValue($json, 'LastServiceDate'));
        $trigger_date = [
            ['date' => $m1_date ? $m1_date->format('Y-m-d') : null],
            ['date' => null],
        ];

        // Cache user lookups to reduce database queries
        static $userCache = [];
        $salesEmail = $this->getJsonValue($json, 'SalesEmail');

        $user = User::where('email', $salesEmail)->first();
        if (! $user) {
            $user = null;
            $matchingUser = UsersAdditionalEmail::with('user')->where('email', $salesEmail)->first();
            if ($matchingUser) {
                $user = $matchingUser->user;
            }
        }

        if (! $user) {
            $import_to_sales = 2;
        } else {
            $import_to_sales = 0;
        }

        // Generate the PID from LocationID and LocationCode
        $pid = $this->getJsonValue($json, 'LocationID').$this->getJsonValue($json, 'LocationCode', '');

        // Prepare the data for the new or updated record
        $saleData = [
            // Product/Partner Identification
            'pid' => $pid,

            'closer1_id' => isset($user->id) ? $user->id : null,
            // Customer Information
            'customer_name' => $this->getJsonValue($json, 'FName', '').' '.$this->getJsonValue($json, 'LName', ''),
            'customer_address' => $this->getJsonValue($json, 'Address'),
            'customer_city' => $this->getJsonValue($json, 'City'),
            'customer_state' => $this->getJsonValue($json, 'State'),
            'customer_zip' => $this->getJsonValue($json, 'Zip'),
            'customer_email' => $this->getJsonValue($json, 'Email'),
            'customer_phone' => $this->getJsonValue($json, 'Phone'),

            // Sales Information
            'sales_rep_name' => $this->getJsonValue($json, 'SalesName'),
            'sales_rep_email' => $this->getJsonValue($json, 'SalesEmail'),
            'install_partner' => $this->getJsonValue($json, 'CompanyName'),
            'customer_signoff' => $this->parseDate($this->getJsonValue($json, 'SoldDate')),
            'data_source_type' => 'HomeTeam', // $this->getJsonValue($json, 'Source'),

            // Milestone Dates
            'm1_date' => $this->parseDate($this->getJsonValue($json, 'InitialServiceCompleted')),
            'date_cancelled' => $this->parseDate($this->getJsonValue($json, 'CancelDate')),
            'last_service_date' => $this->parseDate($this->getJsonValue($json, 'LastServiceDate')),

            // Service Information
            'product' => $this->getJsonValue($json, 'Description'),
            'product_id' => isset($product_id) ? $product_id : null,
            'gross_account_value' => $this->getJsonValue($json, 'Annual Value', 0),
            'service_schedule' => $this->getJsonValue($json, 'Schedule'),
            'initial_service_cost' => $this->getJsonValue($json, 'Initial Service Price', 0),
            'trigger_date' => json_encode($trigger_date),
            'service_completed' => $this->getJsonValue($json, 'TotalServices', 0),
            'length_of_agreement' => $this->getJsonValue($json, 'RecurringService'),

            // Payment Information
            'auto_pay' => $this->getJsonValue($json, 'Auto Pay') === 'YES',

            // Status Information
            'job_status' => $this->getJsonValue($json, 'Status of Account'),
            'bill_status' => $this->getBillStatus($this->getJsonValue($json, 'Balance', 0)),

            // Additional Fields
            'source_created_at' => $now,
            'source_updated_at' => $now,
            'import_to_sales' => $import_to_sales,
            // Other
            'subscription_payment' => $this->getJsonValue($json, 'RecurringSubtotal', 0),
            'adders_description' => $this->getJsonValue($json, 'CancelReason'),
        ];

        // Check if a record with this PID already exists
        $existingRecord = HometeamJsonRawData::where('pid', $pid)->first();

        // Define fields to compare if record exists
        $fieldsToCompare = [
            'customer_name',
            'sales_rep_name',
            'customer_signoff',
            'job_status',
            'gross_account_value',
            'date_cancelled',
            'm1_date', // initial_service_date in the model
            'product',  // mapped from Description in the JSON
        ];

        $sale = null;

        if ($existingRecord) {
            // Record exists - check if any of the specified fields have changed
            $hasChanges = false;

            foreach ($fieldsToCompare as $field) {
                // Special handling for date fields
                if (in_array($field, ['customer_signoff', 'date_cancelled', 'm1_date'])) {
                    // Safely get old value - handle both Carbon objects and string dates
                    $oldValue = null;
                    if ($existingRecord->{$field}) {
                        if (is_object($existingRecord->{$field}) && method_exists($existingRecord->{$field}, 'format')) {
                            $oldValue = $existingRecord->{$field}->format('Y-m-d');
                        } elseif (is_string($existingRecord->{$field})) {
                            // If it's already a string, use as is or convert from different format if needed
                            $oldValue = $existingRecord->{$field};
                        }
                    }

                    // Safely get new value - handle both Carbon objects and string dates
                    $newValue = null;
                    if (! empty($saleData[$field])) {
                        if (is_object($saleData[$field]) && method_exists($saleData[$field], 'format')) {
                            $newValue = $saleData[$field]->format('Y-m-d');
                        } elseif (is_string($saleData[$field])) {
                            $newValue = $saleData[$field];
                        }
                    }

                    if ($oldValue !== $newValue) {
                        Log::info("Field {$field} changed: '{$oldValue}' to '{$newValue}'");
                        $hasChanges = true;
                        break;
                    }
                } else {
                    // Non-date field comparison
                    if ($existingRecord->{$field} != $saleData[$field]) {
                        Log::info("Field {$field} changed: '{$existingRecord->{$field}}' to '{$saleData[$field]}'");
                        $hasChanges = true;
                        break;
                    }
                }
            }

            if ($hasChanges) {

                // Update the existing record with new data
                // Debug product field specifically before update
                Log::debug("Before update - product in saleData: '{$saleData['product']}'");
                Log::debug("Before update - product in existingRecord: '{$existingRecord->product}'");

                $existingRecord->fill($saleData);

                // Debug product field after fill
                Log::debug("After fill - product in existingRecord: '{$existingRecord->product}'");

                // Make sure both timestamp fields are updated
                $existingRecord->updated_at = $now;
                $existingRecord->last_updated_date = $now;

                // Explicitly set the product field to ensure it gets updated
                $existingRecord->product = $saleData['product'];
                Log::debug("After explicit assignment - product in existingRecord: '{$existingRecord->product}'");

                if ($save) {
                    $existingRecord->save();
                    // Check if the product was actually saved
                    $freshRecord = HometeamJsonRawData::find($existingRecord->id);
                    Log::debug("After save - product in database: '{$freshRecord->product}'");
                }

                $sale = $existingRecord;
                // Set exists flag to true to indicate this is an existing record
                $sale->exists = true;
                Log::info("Updated existing record with PID: {$pid}");
            } else {
                // No changes - return existing record without saving
                $sale = $existingRecord;
                // Set exists flag to true to indicate this is an existing record
                $sale->exists = true;
                Log::info("Skipped unchanged record with PID: {$pid}");
            }
        } else {
            // No existing record - create new one
            $sale = new HometeamJsonRawData($saleData);
            // Explicitly set exists flag to false to indicate this is a new record
            $sale->exists = false;

            if ($save) {
                $sale->save();
            }

            Log::info("Created new record with PID: {$pid}");
        }

        return $sale;
    }

    /**
     * Safely get a value from JSON array with a default fallback
     *
     * @param  array  $json  The JSON array
     * @param  string  $key  The key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The value or default
     */
    protected function getJsonValue(array $json, string $key, $default = null)
    {
        return isset($json[$key]) ? $json[$key] : $default;
    }

    /**
     * Process a batch of JSON records with transaction support
     *
     * @param  array  $batch  Batch of JSON records to process
     * @param  callable  $progressCallback  Callback for progress updates
     * @param  int  $chunkSize  Number of records to insert at once
     * @return array Array with success status and any errors
     */
    public function processBatch(array $batch, ?callable $progressCallback = null, int $chunkSize = 100): array
    {
        $recordCount = 0;
        $errors = [];
        $sourceTypes = [];
        $result = [
            'success' => false,
            'insertCount' => 0,
            'updateCount' => 0,
            'skipCount' => 0,
            'errorCount' => 0,
        ];

        DB::beginTransaction();

        try {
            $now = now()->toDateTimeString();
            $inserts = [];
            $preparedRecords = [];

            // Process all records in the batch
            foreach ($batch as $index => $record) {
                try {
                    // Each record can contain multiple sales
                    foreach ($record as $finalRecord) {
                        // Track source types
                        if (isset($finalRecord['Source']) && ! empty($finalRecord['Source'])) {
                            $sourceTypes[$finalRecord['Source']] = $finalRecord['Source'];
                        }

                        // Sanitize the record
                        $finalRecord = $this->sanitizeInputData($finalRecord);

                        // Generate PID from LocationID and LocationCode
                        $pid = $this->getJsonValue($finalRecord, 'LocationID').$this->getJsonValue($finalRecord, 'LocationCode', '');

                        // Use processSaleData to prepare the record but don't save yet
                        $sale = $this->processSaleData($finalRecord, false);
                        $preparedRecords[$pid] = $sale->attributesToArray();
                        $recordCount++;
                    }

                    // Call progress callback if provided
                    if ($progressCallback && is_callable($progressCallback)) {
                        $progressCallback($index);
                    }
                } catch (\Exception $e) {
                    Log::error('Error processing record: '.$e->getMessage(), [
                        'record' => $record,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $errors[] = "Record $index: {$e->getMessage()}";
                    $result['errorCount']++;

                    // Still call progress callback even for failed records
                    if ($progressCallback && is_callable($progressCallback)) {
                        $progressCallback($index);
                    }
                }
            }

            // Collect PIDs for batch lookup
            $pids = array_keys($preparedRecords);
            $existingRecords = HometeamJsonRawData::whereIn('pid', $pids)->get()->keyBy('pid');

            foreach ($preparedRecords as $pid => $record) {
                // Check if record exists
                if ($existingRecords->has($pid)) {
                    $existing = $existingRecords->get($pid);
                    $needsUpdate = false;

                    // Compare fields that trigger updates
                    $fieldsToCompare = [
                        'customer_name',
                        'sales_rep_name',
                        'customer_signoff',
                        'job_status',
                        'gross_account_value',
                        'date_cancelled',
                        'm1_date', // mapped from initial_service_date
                        'product',
                    ];

                    foreach ($fieldsToCompare as $field) {
                        // Special handling for dates to avoid format differences
                        if ($field == 'm1_date' || $field == 'date_cancelled' || $field == 'customer_signoff') {
                            // Ensure we're working with just the date part (Y-m-d) for both values
                            // Convert existing value to Y-m-d format if it's a date object
                            if ($existing->$field instanceof \DateTime || $existing->$field instanceof \Carbon\Carbon) {
                                $existingValue = $existing->$field->format('Y-m-d');
                            } else {
                                // Try to parse as date if it's a string that looks like a date
                                $existingValue = ! empty($existing->$field) && is_string($existing->$field) ?
                                    (preg_match('/\d{4}-\d{2}-\d{2}/', $existing->$field) ?
                                        substr($existing->$field, 0, 10) : $existing->$field) :
                                    $existing->$field;
                            }

                            // Format new value as Y-m-d if it's a date
                            if (! empty($record[$field])) {
                                if ($record[$field] instanceof \DateTime || $record[$field] instanceof \Carbon\Carbon) {
                                    $newValue = $record[$field]->format('Y-m-d');
                                } elseif (is_string($record[$field])) {
                                    // If it's already in Y-m-d format, keep it
                                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $record[$field])) {
                                        $newValue = $record[$field];
                                    } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $record[$field])) {
                                        // Extract just the date part from ISO format or similar
                                        $newValue = substr($record[$field], 0, 10);
                                    } else {
                                        // Try to convert using strtotime
                                        $newValue = date('Y-m-d', strtotime($record[$field]));
                                    }
                                } else {
                                    // For other types, try to convert using strtotime
                                    $newValue = date('Y-m-d', strtotime((string) $record[$field]));
                                }
                            } else {
                                $newValue = null;
                            }

                            if ($existingValue != $newValue) {
                                $needsUpdate = true;
                                Log::info("Field '{$field}' changed from '{$existingValue}' to '{$newValue}' for PID: {$pid}");
                                break;
                            }
                        } elseif (isset($record[$field]) && $record[$field] != $existing->$field) {
                            $needsUpdate = true;
                            Log::info("Field '{$field}' changed from '{$existing->$field}' to '{$record[$field]}' for PID: {$pid}");
                            break;
                        }
                    }

                    if ($needsUpdate) {
                        // Update only if there are changes
                        $record['updated_at'] = $now;
                        $record['last_updated_date'] = $now;

                        // Update record
                        HometeamJsonRawData::where('pid', $pid)->update($record);
                        $result['updateCount']++;

                        Log::info("Updated record with PID: {$pid}");
                    } else {
                        // Skip if no changes needed
                        $result['skipCount']++;
                        Log::info("Skipped unchanged record with PID: {$pid}");
                    }
                } else {
                    // Insert new record
                    $record['created_at'] = $now;
                    $record['updated_at'] = $now;
                    $record['last_updated_date'] = $now;
                    $inserts[] = $record;
                }
            }

            // Bulk insert new records
            if (! empty($inserts)) {
                // Insert in chunks for better performance
                foreach (array_chunk($inserts, $chunkSize) as $chunk) {
                    HometeamJsonRawData::insert($chunk);
                }
                $result['insertCount'] = count($inserts);

                Log::info("Inserted {$result['insertCount']} new records");
            }

            // Commit the transaction
            DB::commit();

            $result['success'] = true;
            $result['recordCount'] = $recordCount;
            $result['sourceTypes'] = array_values($sourceTypes);

            Log::info("Successfully processed batch: {$result['insertCount']} inserts, {$result['updateCount']} updates, {$result['skipCount']} skipped");

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process batch: '.$e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'errors' => array_merge($errors, ["Batch processing error: {$e->getMessage()}"]),
                'recordCount' => $recordCount,
                'sourceTypes' => array_values($sourceTypes ?? []),
                'insertCount' => 0,
                'updateCount' => 0,
                'skipCount' => 0,
                'errorCount' => $result['errorCount'],
            ];
        }
    }

    /**
     * Insert a batch of sales records efficiently
     *
     * @param  array  $salesData  Array of sales data to insert
     */
    protected function insertSalesBatch(array $salesData): void
    {
        if (empty($salesData)) {
            return;
        }

        // Use chunk insert for better performance
        $insertChunkSize = 100; // MySQL typically handles up to 1000, but 100 is safer
        $chunks = array_chunk($salesData, $insertChunkSize);

        foreach ($chunks as $chunk) {
            try {
                DB::table((new HometeamJsonRawData)->getTable())->insert($chunk);
            } catch (\Exception $e) {
                Log::error('Failed to bulk insert records', [
                    'error' => $e->getMessage(),
                    'count' => count($chunk),
                ]);
                throw $e; // Re-throw to be handled by the caller
            }
        }

        // Log successful bulk insert
        Log::info('Successfully inserted '.count($salesData).' records in bulk');
    }

    /**
     * Parse date from string
     *
     * @param  string|null  $date  Date string
     * @return Carbon|null Parsed date or null
     */
    protected function parseDate(?string $date): ?Carbon
    {
        return ! empty($date) ? Carbon::createFromFormat('m/d/Y', $date) : null;
    }

    /**
     * Process bill status
     *
     * @param  mixed  $balance  Balance amount
     * @return float Processed balance
     */
    protected function getBillStatus($balance): float
    {
        // Convert to float to ensure proper comparison
        $balance = (float) $balance;

        return $balance;
    }

    /**
     * Sanitize JSON content before parsing
     *
     * @param  string  $jsonContent  Raw JSON content
     * @return string Sanitized JSON content
     */
    protected function sanitizeJsonContent(string $jsonContent): string
    {
        // Remove UTF-8 BOM if present
        $bom = pack('H*', 'EFBBBF');
        $jsonContent = preg_replace("/^$bom/", '', $jsonContent);

        // Replace invalid UTF-8 character sequences
        $jsonContent = mb_convert_encoding($jsonContent, 'UTF-8', 'UTF-8');

        // Strip any control characters except tabs and newlines
        $jsonContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $jsonContent);

        return $jsonContent;
    }

    /**
     * Sanitize each element in a JSON array recursively
     *
     * @param  array  $jsonArray  The array to sanitize
     * @return array The sanitized array
     */
    protected function sanitizeJsonArray(array $jsonArray): array
    {
        foreach ($jsonArray as &$record) {
            if (is_array($record)) {
                foreach ($record as &$item) {
                    if (is_array($item)) {
                        // For nested arrays, sanitize each field based on its content type
                        foreach ($item as $key => &$value) {
                            if (is_string($value)) {
                                // Apply appropriate sanitization based on field name/type
                                if (strpos(strtolower($key), 'email') !== false) {
                                    $value = $this->sanitizeEmail($value);
                                } elseif (strpos(strtolower($key), 'phone') !== false) {
                                    $value = $this->sanitizePhone($value);
                                } elseif (in_array($key, ['Annual Value', 'Initial Service Price', 'RecurringSubtotal', 'Balance', 'TotalServices'])) {
                                    $value = $this->sanitizeNumeric($value);
                                } else {
                                    $value = $this->sanitizeText($value);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $jsonArray;
    }

    /**
     * Sanitize input data based on field names
     *
     * @param  array  $data  The data to sanitize
     * @return array Sanitized data
     */
    protected function sanitizeInputData(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_string($value)) {
                // Apply appropriate sanitization based on field name/type
                if (strpos(strtolower($key), 'email') !== false) {
                    $value = $this->sanitizeEmail($value);
                } elseif (strpos(strtolower($key), 'phone') !== false) {
                    $value = $this->sanitizePhone($value);
                } elseif (in_array($key, ['Annual Value', 'Initial Service Price', 'RecurringSubtotal', 'Balance', 'TotalServices'])) {
                    $value = $this->sanitizeNumeric($value);
                } else {
                    $value = $this->sanitizeText($value);
                }
            }
        }

        return $data;
    }

    /**
     * Sanitize email addresses
     *
     * @param  string  $email  The email to sanitize
     * @return string Sanitized email
     */
    protected function sanitizeEmail(string $email): string
    {
        if (! is_string($email)) {
            return '';
        }

        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize phone numbers
     *
     * @param  string  $phone  The phone number to sanitize
     * @return string Sanitized phone number
     */
    protected function sanitizePhone(string $phone): string
    {
        if (! is_string($phone)) {
            return '';
        }

        // Keep only digits and common phone number characters
        return preg_replace('/[^0-9+\-() ]/', '', $phone);
    }

    /**
     * Sanitize numeric values
     *
     * @param  mixed  $value  The value to sanitize
     * @return float|int Sanitized numeric value
     */
    protected function sanitizeNumeric($value)
    {
        if (is_numeric($value)) {
            return $value;
        }
        if (is_string($value)) {
            // Remove non-numeric characters except decimal point
            $value = preg_replace('/[^0-9.]/', '', $value);

            return is_numeric($value) ? $value : 0;
        }

        return 0;
    }

    /**
     * Sanitize text content
     *
     * @param  string  $text  The text to sanitize
     * @return string Sanitized text
     */
    protected function sanitizeText(string $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        // Only trim and handle basic sanitization without HTML encoding
        return trim($text);
    }
}
