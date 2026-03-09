<?php

declare(strict_types=1);

namespace App\Core\Traits;

/**
 * CustomFieldTypeTrait
 * 
 * Provides helper methods for parsing custom field type strings.
 * Centralizes the logic for converting between 'custom_field_X' format
 * and separate type/ID values.
 * 
 * Usage:
 *   [$type, $customFieldId] = $this->parseCustomFieldType('custom_field_123');
 *   // Returns: ['custom field', 123]
 *   
 *   [$type, $customFieldId] = $this->parseCustomFieldType('per sale');
 *   // Returns: ['per sale', null]
 */
trait CustomFieldTypeTrait
{
    /**
     * Parse a custom field type string and extract the type and custom field ID.
     * 
     * Converts 'custom_field_X' format to ['custom field', X]
     * For non-custom field types, returns [type, null]
     *
     * @param string|null $type The type string (e.g., 'custom_field_123', 'per sale', 'per kw')
     * @return array{0: string|null, 1: int|null} [type, customFieldId]
     */
    protected function parseCustomFieldType(?string $type): array
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
    protected function formatCustomFieldType(string $type, ?int $customFieldId): string
    {
        if ($type === 'custom field' && $customFieldId !== null) {
            return 'custom_field_' . $customFieldId;
        }

        return $type;
    }

    /**
     * Parse multiple custom field type fields at once.
     * Useful for override types which have direct, indirect, and office.
     *
     * @param array $data The data array with type fields
     * @param array $typeFields The field names to parse (e.g., ['commission_type', 'direct_override_type'])
     * @param array $idFields The corresponding ID field names (e.g., ['custom_sales_field_id', 'direct_custom_sales_field_id'])
     * @return array The data array with parsed types and IDs
     */
    protected function parseCustomFieldTypes(array $data, array $typeFields, array $idFields): array
    {
        foreach ($typeFields as $index => $typeField) {
            if (!isset($data[$typeField])) {
                continue;
            }

            [$parsedType, $customFieldId] = $this->parseCustomFieldType($data[$typeField]);
            
            $data[$typeField] = $parsedType;
            
            if (isset($idFields[$index]) && $customFieldId !== null) {
                $data[$idFields[$index]] = $customFieldId;
            }
        }

        return $data;
    }
}
