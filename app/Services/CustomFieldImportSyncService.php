<?php

declare(strict_types=1);

namespace App\Services;

use App\Features\CustomSalesFieldsFeature;
use App\Models\CompanyProfile;
use App\Models\Crmcustomfields;
use App\Models\FiberSalesImportField;
use App\Models\MortgageSalesImportField;
use App\Models\PestSalesImportField;
use App\Models\RoofingSalesImportField;
use App\Models\SolarSalesImportField;
use App\Models\TurfSalesImportField;
use Illuminate\Support\Facades\Cache;
use Laravel\Pennant\Feature;

/**
 * CustomFieldImportSyncService
 * 
 * Synchronizes custom sales fields with the company-specific sales import fields table.
 * When a custom field is created, it's added to the import fields table.
 * When a custom field is archived/deleted, it's removed from the import fields table.
 * 
 * IMPORTANT: 
 * - This service ONLY operates when the Custom Sales Fields feature is ENABLED
 * - If feature is disabled, no custom fields appear in Excel import (previous behavior)
 * - Only non-calculated fields should appear in import
 * - Calculated fields are computed on-the-fly, so they cannot be imported
 */
class CustomFieldImportSyncService
{
    /**
     * Check if the Custom Sales Fields feature is enabled for the company
     * 
     * @return bool True if feature is enabled
     */
    public function isFeatureEnabled(): bool
    {
        $company = $this->getCompanyProfile();
        if (!$company) {
            return false;
        }

        return Feature::for($company)->active(CustomSalesFieldsFeature::NAME);
    }

    /**
     * Check if a custom field should be included in the import fields table.
     * 
     * A field should be included if:
     * - The Custom Sales Fields feature is ENABLED for the company
     * - It is NOT a calculated field (is_calculated = false)
     * - It is an active field (status = 1)
     * 
     * Note: Fields used in positions (is_available_in_position = true) ARE included
     * in Excel import, as they can have values imported from Excel.
     * 
     * @param Crmcustomfields $field The custom field to check
     * @return bool True if the field should be included in import
     */
    public function shouldIncludeInImport(Crmcustomfields $field): bool
    {
        // Feature must be enabled for custom fields to appear in import
        if (!$this->isFeatureEnabled()) {
            return false;
        }

        // Exclude calculated fields - they are computed, not imported
        if ($field->is_calculated) {
            return false;
        }

        // Only include active fields
        if ($field->status != 1) {
            return false;
        }

        return true;
    }

    /**
     * Get the company profile (cached)
     */
    private function getCompanyProfile(): ?CompanyProfile
    {
        return Cache::remember('company_profile', 3600, fn() => CompanyProfile::first());
    }

    /**
     * Get the import field model class for the current company type
     * 
     * @return class-string|null
     */
    private function getImportFieldModel(): ?string
    {
        $company = $this->getCompanyProfile();
        if (!$company || !$company->company_type) {
            return null;
        }

        $map = [
            CompanyProfile::SOLAR_COMPANY_TYPE => SolarSalesImportField::class,
            CompanyProfile::TURF_COMPANY_TYPE => TurfSalesImportField::class,
            CompanyProfile::MORTGAGE_COMPANY_TYPE => MortgageSalesImportField::class,
            'Pest' => PestSalesImportField::class,
            'Fiber' => FiberSalesImportField::class,
            CompanyProfile::ROOFING_COMPANY_TYPE => RoofingSalesImportField::class,
        ];

        return $map[$company->company_type] ?? null;
    }

    /**
     * Add a custom field to the sales import fields table
     * 
     * Only adds the field if it should be included in import (not calculated).
     * 
     * @param Crmcustomfields $field The custom field to add
     * @return bool True if added successfully, false if not eligible or error
     */
    public function addToImportFields(Crmcustomfields $field): bool
    {
        $modelClass = $this->getImportFieldModel();
        if (!$modelClass) {
            return false;
        }

        // Check if this field should be included in import
        if (!$this->shouldIncludeInImport($field)) {
            // Not eligible for import - ensure it's not in the table
            $this->removeFromImportFields($field);
            return false;
        }

        $fieldName = 'custom_field_' . $field->id;

        // Check if already exists
        if ($modelClass::where('name', $fieldName)->exists()) {
            return true; // Already exists, nothing to do
        }

        // Create the import field entry
        $modelClass::create([
            'name' => $fieldName,
            'label' => $field->name,
            'is_mandatory' => 0, // Custom fields are not mandatory
            'is_custom' => 1, // Mark as custom field
            'section_name' => 'Custom Fields',
            'field_type' => $this->mapFieldType($field->type),
        ]);

        return true;
    }

    /**
     * Remove a custom field from the sales import fields table
     * 
     * @param Crmcustomfields $field The custom field to remove
     * @return bool True if removed successfully
     */
    public function removeFromImportFields(Crmcustomfields $field): bool
    {
        $modelClass = $this->getImportFieldModel();
        if (!$modelClass) {
            return false;
        }

        $fieldName = 'custom_field_' . $field->id;

        // Delete the import field entry
        $modelClass::where('name', $fieldName)->delete();

        return true;
    }

    /**
     * Update a custom field in the sales import fields table (e.g., when name changes)
     * 
     * If the field is no longer eligible for import (became calculated),
     * it will be removed from the import table instead of updated.
     * 
     * @param Crmcustomfields $field The custom field to update
     * @return bool True if updated successfully
     */
    public function updateInImportFields(Crmcustomfields $field): bool
    {
        $modelClass = $this->getImportFieldModel();
        if (!$modelClass) {
            return false;
        }

        $fieldName = 'custom_field_' . $field->id;

        // Check if this field should be included in import
        if (!$this->shouldIncludeInImport($field)) {
            // No longer eligible for import - remove from table
            $this->removeFromImportFields($field);
            return false;
        }

        // Check if exists - if not, add it
        if (!$modelClass::where('name', $fieldName)->exists()) {
            return $this->addToImportFields($field);
        }

        // Update the label if the field exists
        $modelClass::where('name', $fieldName)->update([
            'label' => $field->name,
            'field_type' => $this->mapFieldType($field->type),
        ]);

        return true;
    }

    /**
     * Sync all active custom fields to the import fields table
     * This can be used for initial setup or repair
     * 
     * IMPORTANT: If Custom Sales Fields feature is DISABLED, this method will:
     * - Remove ALL custom field entries from the import table
     * - Return immediately (no custom fields in import when feature disabled)
     * 
     * Only includes fields that are:
     * - Active (status = 1)
     * - NOT calculated (is_calculated = false)
     * 
     * Note: Fields used in positions are now included in Excel import.
     * 
     * @return array Summary of sync operation
     */
    public function syncAllCustomFields(): array
    {
        $modelClass = $this->getImportFieldModel();
        if (!$modelClass) {
            return ['success' => false, 'message' => 'Could not determine import field model for company type'];
        }

        $companyId = $this->getCompanyProfile()?->id;
        if (!$companyId) {
            return ['success' => false, 'message' => 'Company profile not found'];
        }

        // If feature is DISABLED, remove all custom fields from import table
        // This ensures no custom fields appear in Excel import when feature is off
        if (!$this->isFeatureEnabled()) {
            $existingImportFields = $modelClass::where('name', 'like', 'custom_field_%')->get();
            $removed = 0;
            
            foreach ($existingImportFields as $importField) {
                $importField->delete();
                $removed++;
            }
            
            return [
                'success' => true,
                'message' => "Feature disabled: {$removed} custom field entries removed from import table",
                'added' => 0,
                'updated' => 0,
                'removed' => $removed,
                'feature_enabled' => false,
            ];
        }

        // Get all active custom fields with field_category = 'custom_sales'
        $allActiveCustomFields = Crmcustomfields::where('status', 1)
            ->where('field_category', 'custom_sales')
            ->get();

        // Filter to only include fields eligible for import (not calculated)
        $eligibleCustomFields = $allActiveCustomFields->filter(function ($field) {
            // Manually check eligibility without calling shouldIncludeInImport
            // (since that would re-check feature flag for each field)
            // Only exclude calculated fields - position fields are now allowed in import
            return !$field->is_calculated && $field->status == 1;
        });

        // Get existing custom field entries in import table
        $existingImportFields = $modelClass::where('name', 'like', 'custom_field_%')->get();
        $existingFieldNames = $existingImportFields->pluck('name')->toArray();

        $added = 0;
        $removed = 0;
        $updated = 0;

        // Add new eligible custom fields that don't exist in import table
        foreach ($eligibleCustomFields as $field) {
            $fieldName = 'custom_field_' . $field->id;
            
            if (!in_array($fieldName, $existingFieldNames)) {
                // Directly create instead of calling addToImportFields (skip redundant checks)
                $modelClass::create([
                    'name' => $fieldName,
                    'label' => $field->name,
                    'is_mandatory' => 0,
                    'is_custom' => 1,
                    'section_name' => 'Custom Fields',
                    'field_type' => $this->mapFieldType($field->type),
                ]);
                $added++;
            } else {
                // Update existing entry (in case name changed)
                $modelClass::where('name', $fieldName)->update([
                    'label' => $field->name,
                    'field_type' => $this->mapFieldType($field->type),
                ]);
                $updated++;
            }
        }

        // Remove orphaned entries:
        // - Custom fields that no longer exist
        // - Custom fields that are archived
        // - Custom fields that are now calculated
        $eligibleFieldNames = $eligibleCustomFields->map(fn($f) => 'custom_field_' . $f->id)->toArray();
        
        foreach ($existingImportFields as $importField) {
            if (!in_array($importField->name, $eligibleFieldNames)) {
                $importField->delete();
                $removed++;
            }
        }

        return [
            'success' => true,
            'message' => "Sync complete: {$added} added, {$updated} updated, {$removed} removed",
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'feature_enabled' => true,
        ];
    }

    /**
     * Map custom field type to import field type
     * 
     * @param string $type The custom field type
     * @return string The import field type
     */
    private function mapFieldType(string $type): string
    {
        return match ($type) {
            'number' => 'number',
            'date' => 'date',
            'text' => 'text',
            default => 'text',
        };
    }
}
