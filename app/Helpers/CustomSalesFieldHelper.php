<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Features\CustomSalesFieldsFeature;
use App\Models\CompanyProfile;
use App\Models\Crmsaleinfo;
use App\Services\SalesCalculationContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Pennant\Feature;

/**
 * CustomSalesFieldHelper
 * 
 * Provides standardized helpers for the Custom Sales Fields feature.
 * Centralizes company profile caching and feature flag checks to avoid:
 * - Multiple CompanyProfile::first() calls per request
 * - Inconsistent feature flag check patterns
 */
class CustomSalesFieldHelper
{
    /**
     * Cache key for request-scoped company profile
     */
    private const COMPANY_PROFILE_CACHE_KEY = 'custom_sales_field_company_profile';

    /**
     * Maximum depth for unwrapping nested arrays in parseCustomFieldValues()
     *
     * This prevents potential DOS attacks from maliciously crafted deeply nested JSON.
     * A depth > 2 is unusual and should be logged for investigation.
     */
    private const MAX_ARRAY_UNWRAP_DEPTH = 10;

    /**
     * Get the cached company profile for the current request.
     * Uses array cache driver for request-scoped caching (no persistence).
     *
     * @return CompanyProfile|null
     */
    public static function getCompanyProfile(): ?CompanyProfile
    {
        // First try to get from SalesCalculationContext if set
        $contextCompany = SalesCalculationContext::getCompanyProfile();
        if ($contextCompany) {
            return $contextCompany;
        }

        // Otherwise use request-scoped cache
        return Cache::store('array')->remember(
            self::COMPANY_PROFILE_CACHE_KEY,
            3600, // TTL doesn't matter for array driver
            fn () => CompanyProfile::first()
        );
    }

    /**
     * Check if the Custom Sales Fields feature is enabled.
     * Uses standardized pattern with request-scoped company profile caching.
     *
     * @param CompanyProfile|null $companyProfile Optional company profile (uses cached if not provided)
     * @return bool
     */
    public static function isFeatureEnabled(?CompanyProfile $companyProfile = null): bool
    {
        // Use SalesCalculationContext if in a calculation context
        if (SalesCalculationContext::hasContext()) {
            return SalesCalculationContext::isCustomFieldsEnabled();
        }

        $company = $companyProfile ?? self::getCompanyProfile();

        if (!$company) {
            return false;
        }

        return Feature::for($company)->active(CustomSalesFieldsFeature::NAME);
    }

    /**
     * Execute a callback only if the Custom Sales Fields feature is enabled.
     * This is a convenience method to guard code blocks.
     *
     * @param callable $callback The callback to execute if feature is enabled
     * @param callable|null $fallback Optional fallback to execute if feature is disabled
     * @param CompanyProfile|null $companyProfile Optional company profile
     * @return mixed The result of the callback or fallback
     */
    public static function whenEnabled(callable $callback, ?callable $fallback = null, ?CompanyProfile $companyProfile = null)
    {
        if (self::isFeatureEnabled($companyProfile)) {
            return $callback();
        }

        if ($fallback !== null) {
            return $fallback();
        }

        return null;
    }

    /**
     * Parse a custom field type string and extract the type and custom field ID.
     * 
     * Converts 'custom_field_X' format to ['custom field', X]
     * For non-custom field types, returns [type, null]
     *
     * @param string|null $type The type string (e.g., 'custom_field_123', 'per sale', 'per kw')
     * @return array{0: string|null, 1: int|null} [type, customFieldId]
     */
    public static function parseCustomFieldType(?string $type): array
    {
        if ($type === null) {
            return [null, null];
        }

        if (str_starts_with($type, 'custom_field_')) {
            $customFieldId = (int) str_replace('custom_field_', '', $type);
            return ['custom field', $customFieldId > 0 ? $customFieldId : null];
        }

        return [$type, null];
    }

    /**
     * Convert a custom field ID to the frontend format.
     * 
     * @param string $type The type (e.g., 'custom field')
     * @param int|null $customFieldId The custom field ID
     * @return string The formatted type (e.g., 'custom_field_123' or original type)
     */
    public static function formatCustomFieldType(string $type, ?int $customFieldId): string
    {
        if ($type === 'custom field' && $customFieldId !== null) {
            return 'custom_field_' . $customFieldId;
        }

        return $type;
    }

    /**
     * Parse and sanitize custom field values from various input formats.
     * 
     * Handles:
     * - null/empty values
     * - JSON strings
     * - Arrays (associative or indexed)
     * - stdClass objects (converts to array)
     * 
     * Validates:
     * - Only keeps keys that are positive integers (valid custom field IDs)
     * - Only keeps scalar values (strings, numbers, booleans) - drops arrays/objects
     *
     * @param mixed $rawValues The raw input (string, array, object, or null)
     * @return array Sanitized associative array with int keys and scalar values
     */
    public static function parseCustomFieldValues(mixed $rawValues): array
    {
        // Handle null/empty
        if ($rawValues === null || $rawValues === '' || $rawValues === []) {
            return [];
        }

        // Convert stdClass/object to array
        if (is_object($rawValues)) {
            $rawValues = (array) $rawValues;
        }

        // Handle JSON string
        if (is_string($rawValues)) {
            $decoded = json_decode($rawValues, true);
            $rawValues = is_array($decoded) ? $decoded : [];
        }

        // Final check - must be array at this point
        if (!is_array($rawValues)) {
            return [];
        }

        // Handle nested array format that can occur when Eloquent casts JSON with numeric keys
        // e.g., [[2 => "0"]] or [["2" => "0"]] should become [2 => "0"]
        // This happens because MySQL JSON columns with numeric keys get wrapped in an array
        // Limit depth to prevent potential DOS from maliciously crafted data
        $depth = 0;

        while (
            $depth < self::MAX_ARRAY_UNWRAP_DEPTH &&
            count($rawValues) === 1 &&
            isset($rawValues[0]) &&
            is_array($rawValues[0])
        ) {
            $rawValues = $rawValues[0];
            $depth++;
        }

        // Log unusual nesting depth for investigation (depth > 2 is suspicious)
        if ($depth > 2) {
            Log::warning('[CustomSalesFields] Unusual array nesting depth detected', [
                'depth' => $depth,
                'max_depth' => self::MAX_ARRAY_UNWRAP_DEPTH,
            ]);
        }

        // Sanitize: only keep valid custom field ID keys (positive integers) with scalar values
        $sanitized = [];
        foreach ($rawValues as $key => $value) {
            // Key must be a positive integer (custom field ID)
            // Handle both integer keys (2) and string keys ("2")
            $intKey = filter_var($key, FILTER_VALIDATE_INT);
            if ($intKey === false || $intKey <= 0) {
                continue;
            }

            // Value must be scalar (string, int, float, bool) - not arrays or objects
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $sanitized[$intKey] = $value;
        }

        return $sanitized;
    }

    /**
     * Merge imported custom field values with existing values.
     * 
     * Uses the + operator to preserve numeric keys (custom field IDs).
     * Imported values take precedence over existing values.
     *
     * @param array $importedValues New values to merge (takes precedence)
     * @param array $existingValues Existing values to preserve
     * @return array Merged values with preserved keys
     */
    public static function mergeCustomFieldValues(array $importedValues, array $existingValues): array
    {
        // Use + operator: imported values first (take precedence), then existing
        // This preserves numeric keys unlike array_merge() which reindexes them
        return $importedValues + $existingValues;
    }

    /**
     * Save custom field values to Crmsaleinfo for a new sale.
     * 
     * Uses parseCustomFieldValues() for parsing and validation.
     * Only saves if values are non-empty after sanitization.
     * Errors are logged but do not block the main import operation.
     *
     * @param string $pid The sale PID
     * @param mixed $rawCustomFieldValues The raw custom field values from import
     * @param int|null $companyId Optional company ID to associate with the Crmsaleinfo record
     * @param array $logContext Additional context for logging (e.g., excel_import_id, raw_row_id)
     * @return void
     */
    public static function saveCustomFieldValuesForNewSale(
        string $pid,
        mixed $rawCustomFieldValues,
        ?int $companyId = null,
        array $logContext = []
    ): void {
        try {
            // Use centralized parsing and validation
            // This sanitizes keys (positive integers only) and values (scalars only)
            $importedValues = self::parseCustomFieldValues($rawCustomFieldValues);
            
            if (!empty($importedValues)) {
                $data = ['custom_field_values' => $importedValues];
                
                // Include company_id if provided
                if ($companyId !== null) {
                    $data['company_id'] = $companyId;
                }
                
                Crmsaleinfo::updateOrCreate(
                    ['pid' => $pid],
                    $data
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[CustomSalesFields] Failed to save custom field values for new sale', array_merge([
                'pid' => $pid,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ], $logContext));
        }
    }

    /**
     * Save custom field values to Crmsaleinfo for an existing sale (merge with existing).
     * 
     * Uses parseCustomFieldValues() for parsing and validation, and mergeCustomFieldValues()
     * for merging imported values with existing values to preserve non-imported fields.
     * Uses the + operator to preserve numeric keys (custom field IDs).
     * Errors are logged but do not block the main import operation.
     *
     * @param string $pid The sale PID
     * @param mixed $rawCustomFieldValues The raw custom field values from import
     * @param int|null $companyId Optional company ID to associate with the Crmsaleinfo record
     * @param array $logContext Additional context for logging (e.g., excel_import_id, raw_row_id)
     * @return void
     */
    public static function saveCustomFieldValuesForExistingSale(
        string $pid,
        mixed $rawCustomFieldValues,
        ?int $companyId = null,
        array $logContext = []
    ): void {
        try {
            // Use centralized parsing and validation
            // This sanitizes keys (positive integers only) and values (scalars only)
            $importedValues = self::parseCustomFieldValues($rawCustomFieldValues);
            
            if (!empty($importedValues)) {
                // Merge with existing values (don't overwrite non-imported fields)
                $existingInfo = Crmsaleinfo::where('pid', $pid)->first();
                $existingValues = self::parseCustomFieldValues(
                    $existingInfo?->custom_field_values ?? []
                );
                
                // Use centralized merge (uses + operator to preserve numeric keys)
                $mergedValues = self::mergeCustomFieldValues($importedValues, $existingValues);
                
                $data = ['custom_field_values' => $mergedValues];
                
                // Include company_id if provided
                if ($companyId !== null) {
                    $data['company_id'] = $companyId;
                }
                
                Crmsaleinfo::updateOrCreate(
                    ['pid' => $pid],
                    $data
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[CustomSalesFields] Failed to save custom field values for updated sale', array_merge([
                'pid' => $pid,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
            ], $logContext));
        }
    }
}
