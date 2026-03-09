<?php

namespace App\Services\Subscription;

use App\Models\User;
use Carbon\Carbon;

class FieldMappingService
{
    private $mappingConfig;

    public function __construct()
    {
        $this->loadMappingConfig();
    }

    /**
     * Load the mapping configuration from JSON file
     */
    private function loadMappingConfig()
    {
        $configPath = config_path('field-mappings/subscription.json');
        if (! file_exists($configPath)) {
            throw new \Exception('Mapping configuration file not found');
        }

        $this->mappingConfig = json_decode(file_get_contents($configPath), true);

        // Ensure all required sections exist
        $this->mappingConfig['field_mappings'] = $this->mappingConfig['field_mappings'] ?? [];
        $this->mappingConfig['field_mappings']['api_to_db'] = $this->mappingConfig['field_mappings']['api_to_db'] ?? [];
        $this->mappingConfig['field_mappings']['customer_api_fields'] = $this->mappingConfig['field_mappings']['customer_api_fields'] ?? [];
        $this->mappingConfig['field_mappings']['computed_fields'] = $this->mappingConfig['field_mappings']['computed_fields'] ?? [];
        $this->mappingConfig['transformers'] = $this->mappingConfig['transformers'] ?? [];
    }

    /**
     * Merge arrays recursively
     */
    private function arrayMergeRecursiveDistinct(array $array1, array $array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Update mapping configuration
     */
    public function updateConfiguration(array $newConfig): array
    {
        // Read existing configuration
        $existingConfig = $this->mappingConfig;

        // Merge new configuration with existing one
        $this->mappingConfig = $this->arrayMergeRecursiveDistinct($existingConfig, $newConfig);

        // Update metadata
        $this->mappingConfig['metadata'] = [
            'version' => $this->incrementVersion($existingConfig['metadata']['version'] ?? '1.0'),
            'last_updated' => now()->format('Y-m-d'),
            'description' => 'Field mapping configuration between subscription API and database',
        ];

        // Save the configuration
        $configPath = config_path('field-mappings/subscription.json');
        file_put_contents($configPath, json_encode($this->mappingConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $this->mappingConfig;
    }

    /**
     * Increment version number
     */
    private function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[count($parts) - 1]++;

        return implode('.', $parts);
    }

    /**
     * Map subscription and customer data to database fields
     */
    public function mapToDatabase(array $subscriptionData, array $customerData = []): array
    {
        $dbData = [];

        // Map subscription API fields
        foreach ($this->mappingConfig['field_mappings']['api_to_db'] as $apiField => $mapping) {
            if (isset($subscriptionData[$apiField])) {
                $value = $this->processField($subscriptionData[$apiField], $mapping, $subscriptionData);

                if (isset($mapping['db_fields'])) {
                    foreach ($mapping['db_fields'] as $dbField) {
                        $dbData[$dbField] = $value;
                    }
                } else {
                    $dbData[$mapping['db_field']] = $value;
                }
            } elseif (! empty($mapping['required'])) {
                throw new \Exception("Required field {$apiField} is missing from API data");
            }
        }

        // Map customer API fields
        if (! empty($customerData)) {
            foreach ($this->mappingConfig['field_mappings']['customer_api_fields'] as $apiField => $mapping) {
                $value = $this->getNestedValue($customerData, $apiField);
                if ($value !== null) {
                    $value = $this->processField($value, $mapping, $customerData);
                    $dbData[$mapping['db_field']] = $value;
                }
            }
        }

        // Handle computed fields
        foreach ($this->mappingConfig['field_mappings']['computed_fields'] as $field => $config) {
            $dbData[$field] = $this->computeField($field, $subscriptionData, $customerData);
        }

        // Add default fields
        $dbData['created_at'] = Carbon::now();

        return $dbData;
    }

    /**
     * Process a field value based on its configuration
     */
    private function processField($value, array $mapping, array $context = [])
    {
        // Handle null values
        if ($value === null) {
            return null;
        }

        // Apply transformation if specified
        if (isset($mapping['transform'])) {
            $value = $this->transformValue($value, $mapping['transform'], $context);
        }

        // If the value is an array of transformed values, return it as is
        if (is_array($value)) {
            return $value;
        }

        return $this->castValue($value, $mapping['type']);
    }

    /**
     * Transform value using specified transformer
     */
    private function transformValue($value, string $transformer, array $context = [])
    {
        switch ($transformer) {
            case 'sales_rep_transform':
                return $this->transformSalesRep($value, $context);

            case 'date_transform':
                $nullValues = $this->mappingConfig['transformers']['date_transform']['null_values'] ?? ['0000-00-00', '0000-00-00 00:00:00'];
                if (in_array($value, $nullValues)) {
                    return null;
                }
                $format = $this->mappingConfig['transformers']['date_transform']['format'] ?? 'Y-m-d H:i:s';

                return Carbon::parse($value)->format($format);

            case 'balance_status_transform':
                return $this->transformBalanceStatus($value);

            case 'completed_appointments_count':
                return ! empty($value) ? count(explode(',', $value)) : 0;
        }

        return $value;
    }

    /**
     * Transform balance status
     */
    private function transformBalanceStatus($value): string
    {
        if ($value === '0.00' || $value === 0 || $value === 0.0 || $value === '0') {
            return 'cleared';
        }

        return 'Pending';
    }

    /**
     * Transform sales rep data
     */
    private function transformSalesRep($soldBy, array $context)
    {
        $salesRepData = User::where('id', $soldBy)->first();

        return [
            'sales_rep_email' => $salesRepData ? $salesRepData->email : null,
            'closer1_id' => $salesRepData ? ($salesRepData->sequifi_id ?? $salesRepData->id) : null,
            'sales_rep_name' => $salesRepData ? trim($this->formatSalesRepName($salesRepData)) : null,
            'sales_setter_name' => "sales_rep_sold_by - {$soldBy} email- ".($salesRepData->email ?? ''),
        ];
    }

    /**
     * Format sales rep name
     */
    private function formatSalesRepName($salesRepData): string
    {
        $firstName = property_exists($salesRepData, 'fname') ? $salesRepData->fname : ($salesRepData->first_name ?? '');
        $lastName = property_exists($salesRepData, 'lname') ? $salesRepData->lname : ($salesRepData->last_name ?? '');

        return trim($firstName.' '.$lastName);
    }

    /**
     * Compute field value based on business logic
     */
    private function computeField(string $field, array $subscription, array $customer = [])
    {
        switch ($field) {
            case 'job_status':
                return $this->computeJobStatus($subscription);

            case 'm1_date':
                return $this->computeM1Date($subscription);

            case 'initial_service_date':
                return $this->computeInitialServiceDate($subscription);
        }

        return null;
    }

    /**
     * Get value from nested array using dot notation
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
     * Cast value to specified type
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
                if ($value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                    return null;
                }

                return $value instanceof Carbon ? $value : Carbon::parse($value);
            default:
                return (string) $value;
        }
    }

    // Placeholder methods for computed fields
    private function computeJobStatus(array $subscription)
    {
        // TODO: Implement job status computation
        return 'Pending';
    }

    private function computeM1Date(array $subscription)
    {
        // TODO: Implement M1 date computation
        return null;
    }

    private function computeInitialServiceDate(array $subscription)
    {
        // TODO: Implement initial service date computation
        return null;
    }

    /**
     * Get all required API fields
     */
    public function getRequiredFields(): array
    {
        $required = [];
        foreach ($this->mappingConfig['field_mappings']['api_to_db'] as $field => $config) {
            if ($config['required']) {
                $required[] = $field;
            }
        }

        return $required;
    }

    /**
     * Get all unmapped database fields
     */
    public function getUnmappedFields(): array
    {
        return array_keys($this->mappingConfig['field_mappings']['unmapped_db_fields']);
    }

    /**
     * Get mapping configuration
     */
    public function getMappingConfig(): array
    {
        return $this->mappingConfig;
    }

    /**
     * Validate if all required fields are present
     *
     * @return array Returns array of missing fields
     */
    public function validateRequiredFields(array $data): array
    {
        $missing = [];
        foreach ($this->getRequiredFields() as $field) {
            if (! isset($data[$field]) ||
                (is_string($data[$field]) && trim($data[$field]) === '') ||
                $data[$field] === null) {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}
