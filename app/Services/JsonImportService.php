<?php

namespace App\Services;

use App\Models\LegacyApiRawDataHistory;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JsonImportService
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
     *
     * @param  array  $json  The sale data to import
     * @param  bool  $save  Whether to immediately save the record or return the prepared object
     * @return LegacyApiRawDataHistory The created sale record
     *
     * @throws \InvalidArgumentException When required fields are missing
     */
    public function processSaleData(array $json, bool $save = true): ?LegacyApiRawDataHistory
    {
        // Data from HometeamJsonRawData is already structured
        $now = Carbon::now();

        // Check if this is an update of an existing record by PID
        $existingRecord = null;
        if (isset($json['pid'])) {
            $existingRecord = LegacyApiRawDataHistory::where('pid', $json['pid'])->first();
        }

        // Cache user lookups to reduce database queries
        static $userCache = [];
        $salesEmail = $this->getJsonValue($json, 'sales_rep_email');

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

        $closer1_id = isset($user->id) ? $user->id : null;

        if (! $existingRecord) {
            // Create a new record
            $saleData = new LegacyApiRawDataHistory;
            $saleData->fill([
                // Product/Partner Identification
                'pid' => $this->getJsonValue($json, 'pid'),
                'closer1_id' => $closer1_id,

                // Customer Information
                'customer_name' => $this->getJsonValue($json, 'customer_name', ''),
                'customer_address' => $this->getJsonValue($json, 'customer_address', ''),
                'customer_city' => $this->getJsonValue($json, 'customer_city', ''),
                'customer_state' => $this->getJsonValue($json, 'customer_state', ''),
                'customer_zip' => $this->getJsonValue($json, 'customer_zip', ''),
                'customer_email' => $this->getJsonValue($json, 'customer_email', ''),
                'customer_phone' => $this->getJsonValue($json, 'customer_phone', ''),

                // Sales Information
                'sales_rep_name' => $this->getJsonValue($json, 'sales_rep_name', ''),
                'sales_rep_email' => $this->getJsonValue($json, 'sales_rep_email', ''),
                'install_partner' => $this->getJsonValue($json, 'install_partner', ''),
                'customer_signoff' => $this->getJsonValue($json, 'customer_signoff', ''),
                'data_source_type' => 'HomeTeam',

                // Milestone Dates
                'm1_date' => $this->getJsonValue($json, 'InitialServiceCompleted'),
                'date_cancelled' => $this->getJsonValue($json, 'CancelDate'),
                'last_service_date' => $this->getJsonValue($json, 'LastServiceDate'),
                'trigger_date' => $this->getJsonValue($json, 'trigger_date', []),

                // Service Information
                'product' => $this->getJsonValue($json, 'product', ''),
                'product_id' => $this->getJsonValue($json, 'product_id', ''),
                'gross_account_value' => $this->getJsonValue($json, 'gross_account_value', 0),
                'service_schedule' => $this->getJsonValue($json, 'service_schedule', ''),
                'initial_service_cost' => $this->getJsonValue($json, 'initial_service_cost', 0),
                'service_completed' => $this->getJsonValue($json, 'service_completed', 0),
                'length_of_agreement' => $this->getJsonValue($json, 'length_of_agreement', ''),

                // Payment Information
                'auto_pay' => $this->getJsonValue($json, 'auto_pay', false),

                // Status Information
                'job_status' => $this->getJsonValue($json, 'job_status', ''),
                'bill_status' => $this->getBillStatus($this->getJsonValue($json, 'bill_status', 0)),

                // Additional Fields
                'source_created_at' => $now,
                'source_updated_at' => $now,
                'import_to_sales' => $import_to_sales,
                'subscription_payment' => $this->getJsonValue($json, 'subscription_payment', 0),
                'adders_description' => $this->getJsonValue($json, 'adders_description', ''),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // print_r($saleData);  die;
            if ($save) {
                $saleData->save();
            }

            return $saleData;
        } else {
            return null;
        }

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
        $errors = [];
        $recordCount = 0;
        $salesToInsert = [];
        $sourceTypes = [];

        DB::beginTransaction();
        try {
            foreach ($batch as $index => $finalRecord) {
                try {
                    // Check if the record is valid and has required fields
                    if (! is_array($finalRecord)) {
                        throw new \InvalidArgumentException('Record is not a valid array');
                    }

                    // Track source types for later processing
                    if (isset($finalRecord['data_source_type'])) {
                        $sourceTypes[$finalRecord['data_source_type']] = $finalRecord['data_source_type'];
                    } elseif (isset($finalRecord['Source'])) {
                        $sourceTypes[$finalRecord['Source']] = $finalRecord['Source'];
                    }

                    // Process the sale but don't save yet (for bulk insertion)
                    $saleData = $this->processSaleData($finalRecord, false);
                    if ($saleData) {
                        $salesToInsert[] = $saleData->attributesToArray();
                    }
                    $recordCount++;

                    // If we've hit our chunk size, insert the batch and reset
                    if (count($salesToInsert) >= $chunkSize) {
                        $this->insertSalesBatch($salesToInsert);
                        $salesToInsert = [];
                    }

                    // Call progress callback if provided
                    if ($progressCallback && is_callable($progressCallback)) {
                        $progressCallback($index);
                    }
                } catch (\Exception $e) {
                    // Log specific record failure but continue with batch
                    Log::error("Failed to import record at index $index", [
                        'error' => $e->getMessage(),
                        'data' => $finalRecord,
                    ]);
                    $errors[] = "Record $index: {$e->getMessage()}";

                    // Still call progress callback even for failed records
                    if ($progressCallback && is_callable($progressCallback)) {
                        $progressCallback($index);
                    }
                }
            }

            // Insert any remaining records
            if (! empty($salesToInsert)) {
                $this->insertSalesBatch($salesToInsert);
            }

            // Commit the current batch
            DB::commit();

            return [
                'success' => true,
                'errors' => $errors,
                'recordCount' => $recordCount,
                'sourceTypes' => $sourceTypes,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process batch', [
                'error' => $e->getMessage(),
            ]);
            $errors[] = "Batch processing error: {$e->getMessage()}";

            return [
                'success' => false,
                'errors' => $errors,
                'recordCount' => 0,
                'sourceTypes' => [],
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
                DB::table((new LegacyApiRawDataHistory)->getTable())->insert($chunk);
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
