<?php

use App\Models\CompanyProfile;
use App\Models\FiberSalesImportField;
use App\Models\FiberSalesImportTemplate;
use App\Models\FiberSalesImportTemplateDetail;
use App\Models\ImportCategoryDetails;
use App\Models\ImportTemplate;
use App\Models\ImportTemplateDetail;
use App\Models\MortgageSalesImportField;
use App\Models\MortgageSalesImportTemplate;
use App\Models\MortgageSalesImportTemplateDetail;
use App\Models\PestSalesImportField;
use App\Models\PestSalesImportTemplate;
use App\Models\PestSalesImportTemplateDetail;
use App\Models\SolarSalesImportField;
use App\Models\SolarSalesImportTemplate;
use App\Models\SolarSalesImportTemplateDetail;
use App\Models\TurfSalesImportField;
use App\Models\TurfSalesImportTemplate;
use App\Models\TurfSalesImportTemplateDetail;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get company profile to determine company type
        $companyProfile = CompanyProfile::first();
        if (! $companyProfile) {
            return; // No company profile found, skip migration
        }

        // Get all Sale category templates from old table (category_id = 1)
        $oldTemplates = ImportTemplate::where('category_id', 1)->get();

        if ($oldTemplates->isEmpty()) {
            return; // No old templates to migrate
        }

        // Determine company type and migrate accordingly
        if ($this->isSolarCompany($companyProfile)) {
            $this->migrateToSolarTables($oldTemplates);
        } elseif ($this->isPestCompany($companyProfile)) {
            $this->migrateToPestTables($oldTemplates);
        } elseif ($this->isTurfCompany($companyProfile)) {
            $this->migrateToTurfTables($oldTemplates);
        } elseif ($this->isFiberCompany($companyProfile)) {
            $this->migrateToFiberTables($oldTemplates);
        } elseif ($this->isMortgageCompany($companyProfile)) {
            $this->migrateToMortgageTables($oldTemplates);
        }
    }

    /**
     * Check if company is Solar type
     */
    private function isSolarCompany($companyProfile)
    {
        return in_array($companyProfile->company_type, [
            CompanyProfile::SOLAR_COMPANY_TYPE,
            CompanyProfile::SOLAR2_COMPANY_TYPE,
        ]);
    }

    /**
     * Check if company is Pest type
     */
    private function isPestCompany($companyProfile)
    {
        return $companyProfile->company_type == 'Pest';
    }

    /**
     * Check if company is Turf type
     */
    private function isTurfCompany($companyProfile)
    {
        return $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE;
    }

    /**
     * Check if company is Fiber type
     */
    private function isFiberCompany($companyProfile)
    {
        return $companyProfile->company_type == CompanyProfile::FIBER_COMPANY_TYPE;
    }

    /**
     * Check if company is Mortgage type
     */
    private function isMortgageCompany($companyProfile)
    {
        return $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;
    }

    /**
     * Migrate templates to Solar tables
     */
    private function migrateToSolarTables($oldTemplates)
    {
        foreach ($oldTemplates as $oldTemplate) {
            // Create new template
            $newTemplate = SolarSalesImportTemplate::create([
                'name' => $oldTemplate->template_name,
                'created_at' => $oldTemplate->created_at,
                'updated_at' => $oldTemplate->updated_at,
            ]);

            // Migrate template details
            $this->migrateTemplateDetails(
                $oldTemplate->id,
                $newTemplate->id,
                SolarSalesImportTemplateDetail::class,
                SolarSalesImportField::class
            );
        }
    }

    /**
     * Migrate templates to Pest tables
     */
    private function migrateToPestTables($oldTemplates)
    {
        foreach ($oldTemplates as $oldTemplate) {
            // Create new template
            $newTemplate = PestSalesImportTemplate::create([
                'name' => $oldTemplate->template_name,
                'created_at' => $oldTemplate->created_at,
                'updated_at' => $oldTemplate->updated_at,
            ]);

            // Migrate template details
            $this->migrateTemplateDetails(
                $oldTemplate->id,
                $newTemplate->id,
                PestSalesImportTemplateDetail::class,
                PestSalesImportField::class
            );
        }
    }

    /**
     * Migrate templates to Turf tables
     */
    private function migrateToTurfTables($oldTemplates)
    {
        foreach ($oldTemplates as $oldTemplate) {
            // Create new template
            $newTemplate = TurfSalesImportTemplate::create([
                'name' => $oldTemplate->template_name,
                'created_at' => $oldTemplate->created_at,
                'updated_at' => $oldTemplate->updated_at,
            ]);

            // Migrate template details
            $this->migrateTemplateDetails(
                $oldTemplate->id,
                $newTemplate->id,
                TurfSalesImportTemplateDetail::class,
                TurfSalesImportField::class
            );
        }
    }

    /**
     * Migrate templates to Fiber tables
     */
    private function migrateToFiberTables($oldTemplates)
    {
        foreach ($oldTemplates as $oldTemplate) {
            // Create new template
            $newTemplate = FiberSalesImportTemplate::create([
                'name' => $oldTemplate->template_name,
                'created_at' => $oldTemplate->created_at,
                'updated_at' => $oldTemplate->updated_at,
            ]);

            // Migrate template details
            $this->migrateTemplateDetails(
                $oldTemplate->id,
                $newTemplate->id,
                FiberSalesImportTemplateDetail::class,
                FiberSalesImportField::class
            );
        }
    }

    /**
     * Migrate templates to Mortgage tables
     */
    private function migrateToMortgageTables($oldTemplates)
    {
        foreach ($oldTemplates as $oldTemplate) {
            // Create new template
            $newTemplate = MortgageSalesImportTemplate::create([
                'name' => $oldTemplate->template_name,
                'created_at' => $oldTemplate->created_at,
                'updated_at' => $oldTemplate->updated_at,
            ]);

            // Migrate template details
            $this->migrateTemplateDetails(
                $oldTemplate->id,
                $newTemplate->id,
                MortgageSalesImportTemplateDetail::class,
                MortgageSalesImportField::class
            );
        }
    }

    /**
     * Migrate template details with field ID mapping and complete field coverage
     */
    private function migrateTemplateDetails($oldTemplateId, $newTemplateId, $newTemplateDetailClass, $newFieldClass)
    {
        // Get old template details
        $oldTemplateDetails = ImportTemplateDetail::where('template_id', $oldTemplateId)->get();

        // Get all fields from the new company-type field table
        $allNewFields = $newFieldClass::all();

        $unmappedFields = [];
        $mappedCount = 0;
        $unmappedNewFields = [];

        // First, process all old template details and map them to new fields
        $mappedFieldIds = [];
        foreach ($oldTemplateDetails as $oldDetail) {
            // Get the old field details to find the field name
            $oldField = ImportCategoryDetails::find($oldDetail->category_detail_id);

            if (! $oldField) {
                $unmappedFields[] = [
                    'old_field_id' => $oldDetail->category_detail_id,
                    'reason' => 'Old field not found in import_category_details table',
                ];

                continue;
            }

            // Find the corresponding field in the new company-type table
            $newField = $this->findMappedField($oldField->name, $newFieldClass, $newTemplateDetailClass);

            if (! $newField) {
                $unmappedFields[] = [
                    'old_field_id' => $oldDetail->category_detail_id,
                    'old_field_name' => $oldField->name,
                    'old_field_label' => $oldField->label,
                    'reason' => 'Field name not found in new company-type table',
                ];

                continue;
            }

            // Create new template detail with mapped excel_field
            $newTemplateDetailClass::create([
                'template_id' => $newTemplateId,
                'field_id' => $newField->id,
                'excel_field' => $oldDetail->excel_field,
                'created_at' => $oldDetail->created_at,
                'updated_at' => $oldDetail->updated_at,
            ]);

            $mappedFieldIds[] = $newField->id;
            $mappedCount++;
        }

        // Now create template details for all remaining new fields that weren't mapped
        foreach ($allNewFields as $newField) {
            if (! in_array($newField->id, $mappedFieldIds)) {
                // Create template detail for unmapped new field with null excel_field
                $newTemplateDetailClass::create([
                    'template_id' => $newTemplateId,
                    'field_id' => $newField->id,
                    'excel_field' => null, // No mapping from old template
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $unmappedNewFields[] = [
                    'new_field_id' => $newField->id,
                    'new_field_name' => $newField->name,
                    'new_field_label' => $newField->label,
                    'reason' => 'Field exists in new system but not mapped from old template',
                ];
            }
        }

        // Log unmapped fields and newly added fields for this template
        if (! empty($unmappedFields) || ! empty($unmappedNewFields)) {
            Log::info("Template migration summary for {$newTemplateDetailClass}", [
                'template_id' => $newTemplateId,
                'mapped_count' => $mappedCount,
                'unmapped_old_fields' => $unmappedFields,
                'unmapped_new_fields' => $unmappedNewFields,
                'total_new_fields' => $allNewFields->count(),
            ]);
        }
    }

    /**
     * Find mapped field with fallback to field mapping
     */
    private function findMappedField($oldFieldName, $newFieldClass, $newTemplateDetailClass)
    {
        // First try exact name match
        $newField = $newFieldClass::where('name', $oldFieldName)->first();
        if ($newField) {
            return $newField;
        }

        // If no exact match, try field mapping based on template type
        $mappedFieldName = $this->getMappedFieldName($oldFieldName, $newTemplateDetailClass);
        if ($mappedFieldName) {
            return $newFieldClass::where('name', $mappedFieldName)->first();
        }

        return null;
    }

    /**
     * Get mapped field name based on template type
     */
    private function getMappedFieldName($oldFieldName, $newTemplateDetailClass)
    {
        // Field mapping for different company types
        $fieldMappings = [
            'solar' => [
                'setter_email' => 'setter1_id',
                'setter2_email' => 'setter2_id',
                'sales_rep_email' => 'closer1_id',
                'sales_rep2_email' => 'closer2_id',
                'notes' => 'adders_description',
                'sale_date' => 'customer_signoff',
            ],
            'pest' => [
                'sales_rep_email' => 'closer1_id',
                'sales_rep2_email' => 'closer2_id',
                'notes' => 'adders_description',
                'sale_date' => 'customer_signoff',
            ],
            'turf' => [
                'setter_email' => 'setter1_id',
                'setter2_email' => 'setter2_id',
                'sales_rep_email' => 'setter1_id',
                'sales_rep2_email' => 'closer2_id',
                'notes' => 'adders_description',
                'sale_date' => 'customer_signoff',
            ],
            'fiber' => [
                'sales_rep_email' => 'closer1_id',
                'sales_rep2_email' => 'closer2_id',
                'notes' => 'adders_description',
                'sale_date' => 'customer_signoff',
            ],
            'mortgage' => [
                'setter_email' => 'setter1_id',
                'setter2_email' => 'setter2_id',
                'sales_rep_email' => 'setter1_id',
                'sales_rep2_email' => 'closer2_id',
                'notes' => 'adders_description',
                'sale_date' => 'customer_signoff',
            ],
        ];

        // Get company type from class name
        $companyType = $this->getCompanyTypeFromClassName($newTemplateDetailClass);

        return $fieldMappings[$companyType][$oldFieldName] ?? null;
    }

    /**
     * Get company type from class name
     */
    private function getCompanyTypeFromClassName($className)
    {
        if (strpos($className, 'Solar') !== false) {
            return 'solar';
        }
        if (strpos($className, 'Pest') !== false) {
            return 'pest';
        }
        if (strpos($className, 'Turf') !== false) {
            return 'turf';
        }
        if (strpos($className, 'Fiber') !== false) {
            return 'fiber';
        }
        if (strpos($className, 'Mortgage') !== false) {
            return 'mortgage';
        }

        return 'solar'; // default
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
