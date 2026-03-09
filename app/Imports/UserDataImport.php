<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class UserDataImport implements ToCollection, WithStartRow
{
    /**
     * @var array Stores all errors encountered during import
     */
    protected $errors = [];

    /**
     * @var array Stores all successfully processed rows
     */
    protected $successItems = [];

    /**
     * @var array List of mandatory fields that must be present in the import
     */
    protected $mandatoryFields = [];

    /**
     * @var array All field names that can be present in the import (both mandatory and optional)
     */
    protected $allFields = [];

    /**
     * @var array Mapping of field names to their column indexes
     */
    protected $fieldMappings = [];

    /**
     * @var int Current row being processed
     */
    protected $currentRow = 0;

    /**
     * @var array Field validations with custom validation functions
     */
    protected $validations = [];

    /**
     * @var int Count of successfully processed rows
     */
    protected $successCount = 0;

    /**
     * @var int Count of skipped rows
     */
    protected $skippedCount = 0;

    /**
     * @var int Total count of data rows (excluding header and empty rows)
     */
    protected $totalCount = 0;

    /**
     * Constructor to initialize the import class
     *
     * @param  array  $allFields  Array of all possible field names (including mandatory ones)
     */
    public function __construct(array $allFields = [], array $mandatoryFields = [])
    {
        $this->allFields = $allFields;
        $this->mandatoryFields = $mandatoryFields;
    }

    /**
     * Start processing from row 1 (which contains column headers)
     */
    public function startRow(): int
    {
        return 1;
    }

    /**
     * Process the imported collection
     */
    public function collection(Collection $collection)
    {
        // Skip if collection is empty
        if ($collection->isEmpty()) {
            $this->errors[] = 'Import file appears to be empty';

            return;
        }

        // First row contains headers
        $headers = $collection->first();

        // Map column names to their indexes
        if (! $this->mapHeaders($headers)) {
            return; // Stop if headers couldn't be mapped correctly
        }

        // Skip the header row and process each data row
        $dataRowIndex = 1; // Start with row 2 (1-based indexing, after header)
        $collection->skip(1)->each(function ($row) use (&$dataRowIndex) {
            // Convert to array if it's a collection
            $rowArray = $row instanceof Collection ? $row->toArray() : $row;

            // Check if the row has any data before processing
            $hasData = false;
            foreach ($rowArray as $value) {
                if (is_string($value)) {
                    $value = trim($value);
                }
                if (! empty($value) || $value === 0 || $value === '0') {
                    $hasData = true;
                    break;
                }
            }

            // Only process rows that have data
            if ($hasData) {
                $dataRowIndex++;
                $this->currentRow = $dataRowIndex;
                $this->totalCount++;

                $data = $this->processRow($row);
                if (! empty($data)) {
                    $this->successItems[] = $data;
                    $this->successCount++;
                } else {
                    $this->skippedCount++;
                }
            }
            // We don't increment skippedCount for completely empty rows
        });
    }

    /**
     * Map the headers to their column indexes
     *
     * @param  Collection|array  $headers
     * @return bool True if mapping was successful, false if mandatory fields are missing
     */
    protected function mapHeaders($headers): bool
    {
        // Convert to array if it's a collection
        $headerArray = $headers instanceof Collection ? $headers->toArray() : $headers;

        // Reset field mappings
        $this->fieldMappings = [];

        // Map column names to their indexes
        foreach ($headerArray as $index => $headerName) {
            if (in_array($headerName, $this->allFields)) {
                $this->fieldMappings[$headerName] = $index;
            }
        }

        // Check if all mandatory fields are present
        $missingMandatoryFields = [];
        foreach ($this->mandatoryFields as $field) {
            if (! isset($this->fieldMappings[$field])) {
                $missingMandatoryFields[] = $field;
            }
        }

        // If mandatory fields are missing, add to errors and return false
        if (! empty($missingMandatoryFields)) {
            $this->errors[] = 'Missing mandatory column(s): '.implode(', ', $missingMandatoryFields);

            return false;
        }

        return true;
    }

    /**
     * Process a single row of data
     *
     * @param  Collection|array  $row
     * @return array|null Processed data or null if there were errors
     */
    protected function processRow($row): ?array
    {
        // Apply any custom validations that were added
        $this->applyDefaultValidations();
        // Convert to array if it's a collection
        $rowArray = $row instanceof Collection ? $row->toArray() : $row;

        // Check if the row is empty (all values are null, empty string, or whitespace)
        $hasData = false;
        foreach ($rowArray as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }
            if (! empty($value) || $value === 0 || $value === '0') {
                $hasData = true;
                break;
            }
        }

        // Skip processing if the row has no data
        if (! $hasData) {
            return null;
        }

        // Prepare data array with null values for all fields
        $data = array_fill_keys($this->allFields, null);
        $rowErrors = [];

        // For each mapped field, extract the value from the correct column
        foreach ($this->fieldMappings as $fieldName => $columnIndex) {
            // Skip fields that don't exist in this row (this shouldn't happen in most cases)
            if (! isset($rowArray[$columnIndex])) {
                continue;
            }

            $value = $rowArray[$columnIndex];

            // Trim string values
            if (is_string($value)) {
                $value = trim($value);
            }

            // Store the value in data before validation
            $data[$fieldName] = $value;

            // We'll apply validations after collecting all field values
        }

        // Check mandatory fields
        foreach ($this->mandatoryFields as $field) {
            if (
                isset($this->fieldMappings[$field]) &&
                (empty($data[$field]) && $data[$field] !== 0 && $data[$field] !== '0')
            ) {
                $rowErrors[] = "Field '{$field}' is mandatory but has no value";
            }
        }

        // Apply all validations
        foreach ($this->validations as $key => $validation) {
            $fieldName = $validation['field'];
            // Always run validation, even for empty fields, to catch required fields
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            $validationResult = call_user_func($validation['function'], $value, $data);
            if ($validationResult !== true) {
                // If validationResult is a string, use it as a custom error message
                if (is_string($validationResult)) {
                    $rowErrors[] = str_replace('{value}', $value ? $value : '(empty)', $validationResult);
                } else {
                    // Otherwise use the default message
                    $rowErrors[] = str_replace('{value}', $value ? $value : '(empty)', $validation['message']);
                }
            }
        }

        // If there are errors for this row, add them and return null
        if (! empty($rowErrors)) {
            $this->errors[] = [
                'row' => $this->currentRow,
                'errors' => $rowErrors,
            ];

            return null;
        }

        // Return the processed data
        return $data;
    }

    /**
     * Get all errors encountered during import
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all successfully processed items
     */
    public function getSuccessItems(): array
    {
        return $this->successItems;
    }

    /**
     * Get the count of successfully processed items
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get the count of skipped items
     */
    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * Get the total count of data rows processed (excluding header and empty rows)
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Check if any errors occurred during import
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get the field mappings (columns to indexes)
     */
    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    /**
     * Add custom validation rule for a specific field
     *
     * @param  string  $key  Unique key for this validation
     * @param  string  $fieldName  Name of the field to validate
     * @param  callable  $validationFunction  Function that takes a value and returns true if valid, false otherwise
     * @param  string  $errorMessage  Error message to display if validation fails
     */
    public function addFieldValidation(string $key, string $fieldName, callable $validationFunction, string $errorMessage): static
    {
        // Store validation info for the field with a unique key
        $this->validations[$key] = [
            'field' => $fieldName,
            'function' => $validationFunction,
            'message' => $errorMessage,
        ];

        return $this;
    }

    /**
     * Apply default validations for common fields
     */
    protected function applyDefaultValidations()
    {
        // Add email format validation if not already added
        if (in_array('email', $this->allFields) && ! isset($this->validations['email_format'])) {
            $this->addFieldValidation('email_format', 'email', function ($value) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            }, 'Email "{value}" is not in a valid format');
        }

        // Add email uniqueness validation if not already added
        if (in_array('email', $this->allFields) && ! isset($this->validations['email_unique'])) {
            $this->addFieldValidation('email_unique', 'email', function ($value) {
                return User::where('email', $value)->doesntExist();
            }, 'Email "{value}" already exists in the database');
        }
    }
}
