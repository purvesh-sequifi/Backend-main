<?php

namespace App\Services;

use Exception;
use Google\ApiCore\ApiException;
use Google\ApiCore\ValidationException;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\Exception\JobException;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\ServiceException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * BigQueryService - Handles data operations with Google BigQuery
 *
 * This service provides methods for interacting with Google BigQuery, including:
 * - Inserting data into BigQuery tables
 * - Updating existing data
 * - Deleting data
 * - Fetching data with custom queries
 * - Checking if records exist
 *
 * The service respects the enabled/disabled status in the configuration
 * and can be turned off system-wide using the BIGQUERY_ENABLED environment variable.
 */
class BigQueryService
{
    /**
     * @var BigQueryClient|null Google BigQuery client instance
     */
    protected $bigQuery = null;

    /**
     * @var bool Whether the BigQuery integration is enabled
     */
    protected $enabled;

    /**
     * @var string Default dataset ID to use when none is specified
     */
    protected $defaultDataset;

    /**
     * @var string Path to the dedicated BigQuery log file
     */
    protected $logPath;

    /**
     * @var int Maximum number of retry attempts for operations
     */
    protected $maxRetries;

    /**
     * @var int Delay in seconds between retry attempts
     */
    protected $retryDelay;

    /**
     * @var string Google Cloud project ID
     */
    protected $projectId;

    /**
     * @var array Type mapping for fields to ensure proper data conversion
     */
    protected $fieldTypeMapping = [
        // Date/Time fields
        'created_at' => 'DATETIME',
        'updated_at' => 'DATETIME',
        'deleted_at' => 'DATETIME',
        'email_verified_at' => 'DATETIME',

        // Boolean fields
        'is_active' => 'BOOLEAN',
        'is_admin' => 'BOOLEAN',
        'active' => 'BOOLEAN',

        // Numeric fields
        'id' => 'INTEGER',
        'user_id' => 'INTEGER',
        'tenant_id' => 'INTEGER',
    ];

    /**
     * Constructor - Initialize the BigQuery service
     */
    public function __construct()
    {
        $dbEnabled = DB::table('integrations')->where('name', 'BigQuery')->where('status', 1)->exists();
        $configEnabled = Config::get('bigquery.enabled', false);
        $this->enabled = $dbEnabled && $configEnabled;
        $this->logPath = storage_path('logs/bigquery.log');
        $this->maxRetries = Config::get('bigquery.max_retries', 3);
        $this->retryDelay = Config::get('bigquery.retry_delay', 2);

        $this->projectId = Config::get('bigquery.project_id', '');
        $this->defaultDataset = Config::get('bigquery.default_dataset', '');

        $this->logBigQuery('BigQueryService initialized', [
            'enabled' => $this->enabled,
            'db_check' => $dbEnabled,
            'config_check' => $configEnabled,
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
            'project_id' => $this->projectId,
            'dataset' => $this->defaultDataset,
        ]);

        if ($this->enabled) {
            try {
                if (empty($this->projectId)) {
                    $this->logBigQuery('BigQuery project ID is not configured', [], 'error');
                    $this->enabled = false;

                    return;
                }
                if (empty($this->defaultDataset)) {
                    $this->logBigQuery('BigQuery default dataset is not configured', [], 'error');
                    $this->enabled = false;

                    return;
                }
                $this->bigQuery = new BigQueryClient([
                    'projectId' => $this->projectId,
                    'keyFilePath' => Config::get('bigquery.credentials_path'),
                ]);
                $dataset = $this->bigQuery->dataset($this->defaultDataset);
                if (! $dataset->exists()) {
                    $this->logBigQuery('BigQuery dataset does not exist', [
                        'dataset' => $this->defaultDataset,
                    ], 'error');
                    $this->enabled = false;
                }
            } catch (\Exception $e) {
                $this->logBigQuery('Failed to initialize BigQuery client: '.$e->getMessage(), [
                    'exception' => get_class($e),
                    'project_id' => $this->projectId,
                    'dataset' => $this->defaultDataset,
                ], 'error');
                $this->enabled = false;
            }
        }
    }

    /**
     * Log BigQuery operations to dedicated log file
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context data
     * @param  string  $level  Log level (info, error, warning, debug)
     */
    protected function logBigQuery(string $message, array $context = [], string $level = 'info')
    {
        $date = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        $logMessage = "[$date] [$levelUpper] $message $contextJson\n";

        // Write to the dedicated log file
        file_put_contents($this->logPath, $logMessage, FILE_APPEND);

        // Also log to Laravel's logger if it's an error or warning
        if ($level == 'error' || $level == 'warning') {
            Log::$level($message, $context);
        }
    }

    /**
     * Standardized error handling for BigQuery exceptions
     *
     * @param  Exception  $exception  The exception that occurred
     * @param  string  $operation  The operation that was being performed
     * @param  array  $context  Additional context for the error
     * @return array Error information with type, message, and remediation advice
     */
    protected function handleBigQueryException(Exception $exception, string $operation, array $context = []): array
    {
        $errorInfo = [
            'operation' => $operation,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context,
        ];

        // Determine error type based on exception class for more specific handling
        if ($exception instanceof ServiceException) {
            $errorInfo['type'] = 'service_error';
            $errorInfo['remediation'] = 'Check service configuration and credentials';
        } elseif ($exception instanceof JobException) {
            $errorInfo['type'] = 'job_error';
            $errorInfo['remediation'] = 'Check query syntax and parameters';
        } elseif ($exception instanceof ApiException) {
            $errorInfo['type'] = 'api_error';
            $errorInfo['remediation'] = 'Review API usage and rate limits';
        } elseif ($exception instanceof ValidationException) {
            $errorInfo['type'] = 'validation_error';
            $errorInfo['remediation'] = 'Verify data format and required fields';
        } elseif ($exception instanceof GoogleException) {
            $errorInfo['type'] = 'google_error';
            $errorInfo['remediation'] = 'Check Google Cloud API status and credentials';
        } else {
            $errorInfo['type'] = 'unknown_error';
            $errorInfo['remediation'] = 'Review logs for more details';
        }

        // Log the detailed error
        $this->logBigQuery("BigQuery {$operation} error: {$exception->getMessage()}", $errorInfo, 'error');

        return $errorInfo;
    }

    /**
     * Insert data into BigQuery.
     *
     * @param  string  $datasetId  The BigQuery dataset ID.
     * @param  string  $tableId  The BigQuery table ID.
     * @param  array  $data  The data to insert.
     * @return bool|array Returns true on success, error details array on failure
     */
    public function insertData(string $datasetId, string $tableId, array $data)
    {
        if (! $this->enabled) {
            Log::info('BigQuery integration is disabled. Skipping insert operation.');

            return true;
        }
        if (empty($datasetId)) {
            $datasetId = $this->defaultDataset ?: config('bigquery.default_dataset') ?: 'sequifi';
        }

        // Log the dataset being used to help debug the issue
        $this->logBigQuery('Dataset for insert operation', [
            'dataset_id' => $datasetId,
            'table_id' => $tableId,
            'default_dataset' => $this->defaultDataset,
            'config_dataset' => config('bigquery.default_dataset'),
            'env_dataset' => config('services.bigquery.default_dataset'),
        ]);

        try {
            // Get table for insert
            $dataset = $this->bigQuery->dataset($datasetId);
            $table = $dataset->table($tableId);

            // Preprocess data for BigQuery
            $processedData = $this->preprocessDataForBigQuery($data, $tableId);

            $maxRetries = $this->maxRetries;
            $retryDelay = $this->retryDelay;
            $success = false;
            $lastError = '';
            $attempt = 0;

            // Retry loop for insert
            while ($attempt < $maxRetries && ! $success) {
                try {
                    $this->logBigQuery("Inserting data into {$datasetId}.{$tableId} (attempt ".($attempt + 1).')', [
                        'dataset' => $datasetId,
                        'table' => $tableId,
                        'attempt' => $attempt + 1,
                    ]);

                    $insertResponse = $table->insertRows([[
                        'data' => $processedData,
                    ]]);

                    if ($insertResponse->isSuccessful()) {
                        $this->logBigQuery("Data successfully inserted into {$datasetId}.{$tableId}", [
                            'dataset' => $datasetId,
                            'table' => $tableId,
                        ]);

                        return true;
                    } else {
                        // Handle insertion errors
                        $responseInfo = $insertResponse->info();
                        $errors = isset($responseInfo['errors']) ? $responseInfo['errors'] : [];

                        // If errors array is empty but operation failed, create a generic error
                        if (empty($errors)) {
                            $errors = [[
                                'reason' => 'unknown',
                                'message' => 'Insert operation failed but no specific error was returned',
                                'location' => 'unknown',
                            ]];
                        }

                        $lastError = json_encode($errors);

                        // Log the error with the full response info for debugging
                        $this->logBigQuery('Insert error (attempt '.($attempt + 1).')', [
                            'errors' => $errors,
                            'response_info' => $responseInfo,
                            'dataset' => $datasetId,
                            'table' => $tableId,
                        ], 'error');

                        // Check if the error is retryable
                        if (! $this->isRetryableError($errors)) {
                            return ['error' => 'Non-retryable error occurred', 'details' => $lastError];
                        }
                    }
                } catch (ServiceException $e) {
                    $lastError = $e->getMessage();
                    $this->logBigQuery('ServiceException during insert (attempt '.($attempt + 1)."): {$lastError}", [
                        'exception' => get_class($e),
                        'code' => $e->getCode(),
                        'dataset' => $datasetId,
                        'table' => $tableId,
                    ], 'error');

                    // Check if the error is due to invalid schema or other non-retryable issues
                    if (strpos($lastError, 'no such field') !== false ||
                        strpos($lastError, 'schema mismatch') !== false) {
                        return ['error' => 'Schema mismatch error', 'details' => $lastError];
                    }
                }

                // Increment attempt counter and retry if not successful
                $attempt++;
                if ($attempt < $maxRetries && ! $success) {
                    $this->logBigQuery("Retrying insert in {$retryDelay} seconds...", [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                    ]);
                    sleep($retryDelay);
                }
            }

            // If we reached here, all retries failed
            $this->logBigQuery("Failed to insert data after {$maxRetries} attempts", [
                'last_error' => $lastError,
                'dataset' => $datasetId,
                'table' => $tableId,
            ], 'error');

            return ['error' => 'Failed to insert data after multiple attempts', 'details' => $lastError];
        } catch (Exception $e) {
            $this->logBigQuery('Fatal error during insert: '.$e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
                'dataset' => $datasetId,
                'table' => $tableId,
            ], 'error');

            return $this->handleBigQueryException($e, 'insert', [
                'dataset' => $datasetId,
                'table' => $tableId,
            ]);
        }
    }

    /**
     * Update data in BigQuery.
     *
     * @param  string  $datasetId  The BigQuery dataset ID.
     * @param  string  $tableId  The BigQuery table ID.
     * @param  string  $condition  The WHERE condition for the update.
     * @param  array  $updates  The columns and values to update.
     * @return bool|array Returns true on success, error details array on failure
     */
    public function updateData(string $datasetId, string $tableId, string $condition, array $updates)
    {
        // Skip if BigQuery integration is disabled
        if (! $this->enabled) {
            Log::info('BigQuery integration is disabled. Skipping update operation.');

            return true;
        }

        // CRITICAL FIX: Ensure we have a valid dataset ID
        if (empty($datasetId)) {
            // First try to use the default dataset from the class property
            $datasetId = $this->defaultDataset;

            // If still empty, try to get it directly from config
            if (empty($datasetId)) {
                $datasetId = config('bigquery.default_dataset');
            }

            // Last resort - hardcode the known dataset name
            if (empty($datasetId)) {
                $datasetId = 'sequifi';
            }
        }

        // Log the dataset being used
        $this->logBigQuery('Dataset for update operation', [
            'dataset_id' => $datasetId,
            'table_id' => $tableId,
            'condition' => $condition,
            'update_fields' => array_keys($updates),
        ]);

        // Prepare SET clause with proper escaping to prevent SQL injection
        $setClauses = [];
        foreach ($updates as $column => $value) {
            if (is_null($value)) {
                $setClauses[] = "`$column` = NULL";
            } elseif (is_bool($value)) {
                $setClauses[] = "`$column` = ".($value ? 'TRUE' : 'FALSE');
            } elseif (is_numeric($value)) {
                $setClauses[] = "`$column` = $value";
            } else {
                // Escape single quotes in string values
                $value = str_replace("'", "\\'", $value);
                $setClauses[] = "`$column` = '$value'";
            }
        }

        $setClause = implode(', ', $setClauses);

        // Skip update if there's nothing to update
        if (empty($setClauses)) {
            Log::info('BigQuery: No fields to update. Skipping.');

            return true;
        }

        try {
            $maxRetries = $this->maxRetries;
            $retryDelay = $this->retryDelay;
            $success = false;
            $lastError = '';
            $attempt = 0;

            while ($attempt < $maxRetries && ! $success) {
                try {
                    // We need to use a SQL UPDATE statement, but BigQuery doesn't support direct UPDATE
                    // So we need to use a temp table and replace approach

                    // Step 1: Create a temporary table with the current data
                    $tempTable = $tableId.'_temp_'.uniqid();
                    $exportQuery = "CREATE OR REPLACE TABLE `$datasetId.$tempTable` AS SELECT * FROM `$datasetId.$tableId`;";
                    $this->bigQuery->runQuery($this->bigQuery->query($exportQuery));

                    // Step 2: Update the temporary table
                    $updateQuery = "UPDATE `$datasetId.$tempTable` SET $setClause WHERE $condition;";
                    $this->bigQuery->runQuery($this->bigQuery->query($updateQuery));

                    // Step 3: Replace the original table with the updated data
                    $replaceQuery = "CREATE OR REPLACE TABLE `$datasetId.$tableId` AS SELECT * FROM `$datasetId.$tempTable`;";
                    $this->bigQuery->runQuery($this->bigQuery->query($replaceQuery));

                    // Log success and return true
                    if (Config::get('bigquery.debug', false)) {
                        Log::channel(Config::get('bigquery.log_channel', 'stack'))
                            ->info("BigQuery: Data successfully batch updated in {$datasetId}.{$tableId}");
                    }

                    $success = true;

                    return true;
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::error('BigQuery batch update error (attempt '.($attempt + 1).'): '.$lastError);

                    $attempt++;
                    if ($attempt < $maxRetries && ! $success) {
                        sleep($retryDelay);
                    }
                }
            }

            // If we reached here, all retries failed
            Log::error("Failed to batch update data in {$datasetId}.{$tableId} after $maxRetries attempts");

            return ['error' => 'Failed to batch update data after multiple attempts', 'details' => $lastError];
        } catch (Exception $e) {
            return $this->handleBigQueryException($e, 'update', [
                'dataset' => $datasetId,
                'table' => $tableId,
                'condition' => $condition,
            ]);
        }
    }

    /**
     * Preprocess data before sending to BigQuery to ensure compatibility
     * This method handles schema mismatches by adding missing fields with default values
     * and removing fields that don't exist in the BigQuery schema to prevent errors
     *
     * @param  array  $data  The data to preprocess
     * @param  string  $tableId  The table ID for schema validation
     * @return array The processed data
     */
    public function preprocessDataForBigQuery(array $data, string $tableId = 'users'): array
    {
        // Skip processing for empty data
        if (empty($data)) {
            return $data;
        }

        // Get the BigQuery table schema
        try {
            $tableSchema = $this->getTableSchema('', $tableId);
        } catch (\Exception $e) {
            // If schema can't be retrieved, log warning and use original data
            $this->logBigQuery("Failed to get schema for preprocessing: {$e->getMessage()}", [
                'table' => $tableId,
                'exception' => get_class($e),
            ], 'warning');
            $tableSchema = [];
        }

        $processedData = [];

        // CRITICAL FIX: Only include fields that exist in the BigQuery schema
        // to prevent the 'no such field' errors
        if (! empty($tableSchema)) {
            // First pass: Only include fields that exist in the schema
            foreach ($data as $key => $value) {
                // Skip sensitive fields that should never go to BigQuery
                if (in_array($key, ['password', 'remember_token'])) {
                    continue;
                }

                // Only include fields that exist in the schema
                if (isset($tableSchema[$key])) {
                    $fieldType = $tableSchema[$key];

                    // Process based on BigQuery data type
                    if (is_null($value)) {
                        $processedData[$key] = null;

                        continue;
                    }

                    switch ($fieldType) {
                        case 'TIMESTAMP':
                        case 'DATETIME':
                            if ($value && ! empty($value)) {
                                // Convert string timestamps to proper format
                                if (is_string($value)) {
                                    try {
                                        $datetime = new \DateTime($value);
                                        $processedData[$key] = $datetime->format('Y-m-d H:i:s');
                                    } catch (\Exception $e) {
                                        // If parsing fails, use null
                                        $processedData[$key] = null;
                                    }
                                } else {
                                    $processedData[$key] = $value;
                                }
                            } else {
                                $processedData[$key] = null;
                            }
                            break;

                        case 'DATE':
                            if ($value && ! empty($value)) {
                                // Convert string dates to proper format
                                if (is_string($value)) {
                                    try {
                                        $date = new \DateTime($value);
                                        $processedData[$key] = $date->format('Y-m-d');
                                    } catch (\Exception $e) {
                                        // If parsing fails, use null
                                        $processedData[$key] = null;
                                    }
                                } else {
                                    $processedData[$key] = $value;
                                }
                            } else {
                                $processedData[$key] = null;
                            }
                            break;

                        case 'BOOL':
                        case 'BOOLEAN':
                            // Ensure boolean values are properly formatted
                            if (is_string($value)) {
                                $processedData[$key] = in_array(strtolower($value), ['1', 'true', 'yes', 'y']) ? true : false;
                            } else {
                                $processedData[$key] = (bool) $value;
                            }
                            break;

                        case 'INT64':
                        case 'INTEGER':
                        case 'INT':
                            // Ensure numeric values are properly formatted
                            if (is_numeric($value)) {
                                $processedData[$key] = (int) $value;
                            } elseif (empty($value)) {
                                $processedData[$key] = 0;
                            } else {
                                // If non-numeric and non-empty, set to 0 to avoid errors
                                $processedData[$key] = 0;
                            }
                            break;

                        case 'FLOAT':
                        case 'NUMERIC':
                            // Handle floating point values
                            if (is_numeric($value)) {
                                $processedData[$key] = (float) $value;
                            } elseif (empty($value)) {
                                $processedData[$key] = 0.0;
                            } else {
                                // If non-numeric and non-empty, set to 0 to avoid errors
                                $processedData[$key] = 0.0;
                            }
                            break;

                        default:
                            // For all other types, treat as strings
                            if (is_array($value) || is_object($value)) {
                                $processedData[$key] = json_encode($value);
                            } else {
                                $processedData[$key] = (string) $value;
                            }
                    }
                }
            }
        }

        // Second pass: Add missing fields from the schema with NULL values
        if (! empty($tableSchema)) {
            foreach ($tableSchema as $field => $type) {
                if (! isset($processedData[$field]) && $field != 'id') { // Skip ID field if missing
                    $processedData[$field] = null; // Always use NULL for missing fields
                }
            }
        } else {
            // If no schema available, use the original data with basic type conversion
            foreach ($data as $key => $value) {
                // Skip sensitive fields
                if (in_array($key, ['password', 'remember_token'])) {
                    continue;
                }

                if (is_bool($value)) {
                    $processedData[$key] = $value;
                } elseif (is_numeric($value)) {
                    $processedData[$key] = $value;
                } elseif (is_array($value) || is_object($value)) {
                    $processedData[$key] = json_encode($value);
                } elseif (is_null($value)) {
                    $processedData[$key] = null;
                } else {
                    $processedData[$key] = (string) $value;
                }
            }
        }

        $this->logBigQuery('Preprocessed data for BigQuery', [
            'table' => $tableId,
            'field_count' => count($processedData),
            'has_id' => isset($processedData['id']),
            'schema_fields' => ! empty($tableSchema) ? count($tableSchema) : 0,
            'filtered_fields' => ! empty($tableSchema) ? count($data) - count(array_intersect_key($data, $tableSchema)) : 0,
        ]);

        return $processedData;
    }

    /**
     * Get default value for a BigQuery data type
     * Always returns NULL for missing fields as requested
     *
     * @param  string  $type  The BigQuery data type
     * @return mixed Default value for the type (now always null)
     */
    protected function getDefaultValueForType(string $type)
    {
        // Modified to always return NULL for missing fields regardless of data type
        return null;
    }

    /**
     * Get the schema for a BigQuery table
     *
     * @param  string  $datasetId  The dataset ID
     * @param  string  $tableId  The table ID
     * @return array Associative array of field => type
     */
    public function getTableSchema(string $datasetId = '', string $tableId = 'users'): array
    {
        if (! $this->enabled) {
            return [];
        }

        $datasetId = $datasetId ?: $this->defaultDataset;
        $schema = [];

        try {
            $table = $this->bigQuery->dataset($datasetId)->table($tableId);
            $tableInfo = $table->info();

            if (isset($tableInfo['schema']['fields']) && is_array($tableInfo['schema']['fields'])) {
                foreach ($tableInfo['schema']['fields'] as $field) {
                    if (isset($field['name']) && isset($field['type'])) {
                        $schema[$field['name']] = $field['type'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logBigQuery("Error getting table schema: {$e->getMessage()}", [
                'dataset' => $datasetId,
                'table' => $tableId,
                'exception' => get_class($e),
            ], 'error');
        }

        return $schema;
    }

    /**
     * Check if BigQuery errors are retryable
     *
     * @param  array  $errors  The errors returned from BigQuery
     * @return bool Whether the errors are retryable
     */
    protected function isRetryableError(array $errors): bool
    {
        foreach ($errors as $error) {
            if (isset($error['errors'])) {
                foreach ($error['errors'] as $innerError) {
                    // Check for common retryable error reasons
                    $reason = $innerError['reason'] ?? '';
                    $message = $innerError['message'] ?? '';

                    // Non-retryable errors
                    if (in_array($reason, ['invalid', 'invalidQuery', 'notFound'])) {
                        // Schema-related errors usually aren't retryable
                        return false;
                    }
                }
            }
        }

        // By default, assume the error is retryable
        return true;
    }

    /**
     * Check if a network error message indicates a retryable error
     *
     * @param  string  $errorMessage  The error message
     * @return bool Whether the error is retryable
     */
    protected function isRetryableNetworkError(string $errorMessage): bool
    {
        $retryablePatterns = [
            'internal server error',
            'deadline exceeded',
            'connection reset by peer',
            'connection timed out',
            'server unavailable',
            'too many requests',
            'resource exhausted',
            'backend error',
        ];

        // Check for retryable error messages
        foreach ($retryablePatterns as $pattern) {
            if (stripos($errorMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if BigQuery integration is enabled
     *
     * @return bool Whether the BigQuery integration is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get diagnostic information about the BigQuery integration
     *
     * @param  string  $datasetId  The dataset ID (optional)
     * @param  string  $tableId  The table ID (optional)
     * @return array Diagnostic information array
     */
    public function getDiagnosticInfo(string $datasetId = '', string $tableId = 'users'): array
    {
        $datasetId = $datasetId ?: $this->defaultDataset;
        $info = [
            'status' => $this->enabled ? 'enabled' : 'disabled',
            'project_id' => $this->projectId,
            'dataset_id' => $datasetId,
            'table_id' => $tableId,
            'record_count' => 0,
            'table_exists' => false,
            'schema_fields' => [],
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'environment' => config('app.env', 'unknown'),
        ];

        if (! $this->enabled) {
            $info['error'] = 'BigQuery integration is disabled';

            return $info;
        }

        try {
            // Check if dataset and table exist
            $dataset = $this->bigQuery->dataset($datasetId);
            if (! $dataset->exists()) {
                $info['error'] = "Dataset {$datasetId} does not exist";

                return $info;
            }

            $table = $dataset->table($tableId);
            if (! $table->exists()) {
                $info['error'] = "Table {$tableId} does not exist in dataset {$datasetId}";

                return $info;
            }

            $info['table_exists'] = true;

            // Get schema information
            $schema = $this->getTableSchema($datasetId, $tableId);
            $info['schema_fields'] = $schema;

            // Get record count
            $query = "SELECT COUNT(*) as count FROM `{$datasetId}.{$tableId}`";
            $queryJobConfig = $this->bigQuery->query($query);
            $queryResults = $this->bigQuery->runQuery($queryJobConfig);

            if ($queryResults->isComplete()) {
                foreach ($queryResults as $row) {
                    $info['record_count'] = (int) $row['count'];
                    break;
                }
            } else {
                $info['warning'] = 'Could not retrieve record count';
            }
        } catch (\Exception $e) {
            $info['error'] = $e->getMessage();
            $info['exception_class'] = get_class($e);
            $this->logBigQuery("Error getting diagnostic info: {$e->getMessage()}", [
                'dataset' => $datasetId,
                'table' => $tableId,
                'exception' => get_class($e),
            ], 'error');
        }

        return $info;
    }

    /**
     * Check if a record exists in BigQuery based on a field value
     *
     * @param  string  $datasetId  The dataset ID
     * @param  string  $tableId  The table ID
     * @param  string  $fieldName  The field name to check
     * @param  mixed  $fieldValue  The field value to check for
     * @param  string  $fieldType  The BigQuery field type
     * @return bool Whether the record exists
     */
    public function executeRawQuery(string $query, int $maxRetries = 3): ?array
    {
        if (! $this->enabled) {
            $this->logBigQuery('BigQuery integration is disabled. Cannot execute raw query.');

            return null;
        }

        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $this->logBigQuery('Executing raw query', [
                    'query' => $query,
                    'attempt' => $attempt + 1,
                ]);

                $queryJobConfig = $this->bigQuery->query($query);
                $queryResults = $this->bigQuery->runQuery($queryJobConfig);

                if ($queryResults->isComplete()) {
                    $results = [];
                    foreach ($queryResults as $row) {
                        $results[] = $row;
                    }

                    return $results;
                }

                return null;

            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $this->logBigQuery('Error executing raw query: '.$errorMessage, [
                    'exception' => get_class($e),
                    'attempt' => $attempt + 1,
                    'max_retries' => $maxRetries,
                ], 'error');

                // Only retry on transient errors
                if ($this->isRetryableNetworkError($errorMessage) && $attempt < $maxRetries - 1) {
                    $attempt++;
                    // Exponential backoff
                    $sleepTimeSeconds = pow(2, $attempt);
                    $this->logBigQuery("Retrying in {$sleepTimeSeconds} seconds...", [], 'info');
                    sleep($sleepTimeSeconds);
                } else {
                    return ['error' => $errorMessage];
                }
            }
        }

        return null;
    }

    /**
     * Check if a record exists in BigQuery based on a field value
     *
     * @param  string  $datasetId  The dataset ID
     * @param  string  $tableId  The table ID
     * @param  string  $fieldName  The field name to check
     * @param  mixed  $fieldValue  The field value to check for
     * @param  string  $fieldType  The BigQuery field type
     * @return bool Whether the record exists
     */
    public function checkRecordExists(string $datasetId = '', string $tableId = '', string $fieldName = 'id', $fieldValue = null, string $fieldType = 'INTEGER'): bool
    {
        if (! $this->enabled || $fieldValue === null) {
            return false;
        }

        $datasetId = $datasetId ?: $this->defaultDataset;

        try {
            // Format the field value based on type
            if ($fieldType == 'INTEGER' || $fieldType == 'INT64' || $fieldType == 'FLOAT' || $fieldType == 'NUMERIC') {
                // For numeric types, ensure it's a proper number without quotes
                $fieldValue = is_numeric($fieldValue) ? $fieldValue : intval($fieldValue);
                $formattedValue = $fieldValue; // No quotes for numbers
            } elseif ($fieldType == 'STRING') {
                $formattedValue = "'".str_replace("'", "\\'", $fieldValue)."'";
            } elseif ($fieldType == 'DATETIME' || $fieldType == 'TIMESTAMP') {
                // Format date/time values properly
                if ($fieldValue instanceof \DateTime) {
                    $formattedValue = "'".$fieldValue->format('Y-m-d H:i:s')."'";
                } else {
                    $formattedValue = "'".date('Y-m-d H:i:s', strtotime($fieldValue))."'";
                }
            } elseif ($fieldType == 'BOOL' || $fieldType == 'BOOLEAN') {
                $formattedValue = $fieldValue ? 'TRUE' : 'FALSE';
            } else {
                // Default to quoted string for unknown types
                $formattedValue = "'".str_replace("'", "\\'", $fieldValue)."'";
            }

            $this->logBigQuery('Checking if record exists in BigQuery', [
                'dataset' => $datasetId,
                'table' => $tableId,
                'field_name' => $fieldName,
                'field_value' => $fieldValue,
                'field_type' => $fieldType,
                'formatted_value' => $formattedValue,
            ]);

            // Build and run query to check if record exists
            $query = "SELECT COUNT(*) as count FROM `{$datasetId}.{$tableId}` WHERE {$fieldName} = {$formattedValue}";

            $queryJobConfig = $this->bigQuery->query($query);
            $queryResults = $this->bigQuery->runQuery($queryJobConfig);

            if ($queryResults->isComplete()) {
                foreach ($queryResults as $row) {
                    $exists = (int) $row['count'] > 0;
                    $this->logBigQuery('Record existence check result', [
                        'dataset' => $datasetId,
                        'table' => $tableId,
                        'field' => $fieldName,
                        'value' => $fieldValue,
                        'exists' => $exists,
                    ]);

                    return $exists;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->logBigQuery('Error checking if record exists: '.$e->getMessage(), [
                'dataset' => $datasetId,
                'table' => $tableId,
                'field_name' => $fieldName,
                'field_value' => $fieldValue,
                'field_type' => $fieldType,
                'exception' => get_class($e),
            ], 'error');

            return false;
        }
    }
}
