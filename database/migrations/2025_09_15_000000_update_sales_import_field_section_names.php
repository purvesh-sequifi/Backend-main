<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update section names for all company types
        $this->updateSectionNamesForAllCompanies();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert section names back to original values
        $this->revertSectionNamesForAllCompanies();
    }

    /**
     * Update section names for all company types
     */
    private function updateSectionNamesForAllCompanies()
    {
        $companyTypes = ['solar', 'turf', 'roofing', 'pest', 'fiber', 'mortgage'];

        foreach ($companyTypes as $companyType) {
            $this->updateSectionNamesForCompany($companyType);
        }
    }

    /**
     * Update section names for a specific company type
     */
    private function updateSectionNamesForCompany($companyType)
    {
        $tableName = "{$companyType}_sales_import_fields";

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $sectionUpdates = $this->getSectionNameUpdatesForCompanyType($companyType);
        $labelUpdates = $this->getLabelUpdatesForCompanyType($companyType);

        // Update section names
        foreach ($sectionUpdates as $oldSectionName => $newSectionName) {
            DB::table($tableName)
                ->where('section_name', $oldSectionName)
                ->update([
                    'section_name' => $newSectionName,
                    'updated_at' => now(),
                ]);
        }

        // Update labels
        foreach ($labelUpdates as $oldLabel => $newLabel) {
            DB::table($tableName)
                ->where('label', $oldLabel)
                ->update([
                    'label' => $newLabel,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Get section name updates for each company type
     */
    private function getSectionNameUpdatesForCompanyType($companyType): array
    {
        // Handle Mortgage company with specific business terminology
        if ($companyType === 'mortgage') {
            return [
                'MLO email' => 'MLO Info',
                'Coordinator email' => 'Coordinator Info',
                'LOA email' => 'LOA Info',
                'Processor email' => 'Processor Info',
            ];
        }

        // Handle Pest and Fiber companies with Sales Rep terminology
        if (in_array($companyType, ['pest', 'fiber'])) {
            return [
                'Sales Rep 1 email' => 'Sales Rep 1 Info',
                'Sales Rep 2 email' => 'Sales Rep 2 Info',
            ];
        }

        // Standard updates for Solar, Turf, and Roofing companies
        if (in_array($companyType, ['solar', 'turf', 'roofing'])) {
            return [
                'Closer 1 email' => 'Closer 1 Info',
                'Closer 2 email' => 'Closer 2 Info',
                'Setter 1 email' => 'Setter 1 Info',
                'Setter 2 email' => 'Setter 2 Info',
            ];
        }

        return [];
    }

    /**
     * Get label updates for each company type
     */
    private function getLabelUpdatesForCompanyType($companyType): array
    {
        // Handle Mortgage company with specific business terminology
        if ($companyType === 'mortgage') {
            return [
                'MLO' => 'MLO Email',
                'Coordinator' => 'Coordinator Email',
                'LOA' => 'LOA Email',
                'Processor' => 'Processor Email',
            ];
        }

        // Handle Pest and Fiber companies with Sales Rep terminology
        if (in_array($companyType, ['pest', 'fiber'])) {
            return [
                'Sales Rep 1' => 'Sales Rep 1 Email',
                'Sales Rep 2' => 'Sales Rep 2 Email',
            ];
        }

        // Standard updates for Solar, Turf, and Roofing companies
        if (in_array($companyType, ['solar', 'turf', 'roofing'])) {
            return [
                'Closer 1' => 'Closer 1 Email',
                'Closer 2' => 'Closer 2 Email',
                'Setter 1' => 'Setter 1 Email',
                'Setter 2' => 'Setter 2 Email',
            ];
        }

        return [];
    }

    /**
     * Revert section names for all company types
     */
    private function revertSectionNamesForAllCompanies()
    {
        $companyTypes = ['solar', 'turf', 'roofing', 'pest', 'fiber', 'mortgage'];

        foreach ($companyTypes as $companyType) {
            $this->revertSectionNamesForCompany($companyType);
        }
    }

    /**
     * Revert section names for a specific company type
     */
    private function revertSectionNamesForCompany($companyType)
    {
        $tableName = "{$companyType}_sales_import_fields";

        if (! Schema::hasTable($tableName)) {
            return;
        }

        $sectionReverts = $this->getSectionNameRevertsForCompanyType($companyType);
        $labelReverts = $this->getLabelRevertsForCompanyType($companyType);

        // Revert section names
        foreach ($sectionReverts as $currentSectionName => $originalSectionName) {
            DB::table($tableName)
                ->where('section_name', $currentSectionName)
                ->update([
                    'section_name' => $originalSectionName,
                    'updated_at' => now(),
                ]);
        }

        // Revert labels
        foreach ($labelReverts as $currentLabel => $originalLabel) {
            DB::table($tableName)
                ->where('label', $currentLabel)
                ->update([
                    'label' => $originalLabel,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Get section name reverts for each company type
     */
    private function getSectionNameRevertsForCompanyType($companyType): array
    {
        // Handle Mortgage company with specific business terminology
        if ($companyType === 'mortgage') {
            return [
                'MLO Info' => 'MLO email',
                'Coordinator Info' => 'Coordinator email',
                'LOA Info' => 'LOA email',
                'Processor Info' => 'Processor email',
            ];
        }

        // Handle Pest and Fiber companies with Sales Rep terminology
        if (in_array($companyType, ['pest', 'fiber'])) {
            return [
                'Sales Rep 1 Info' => 'Sales Rep 1 email',
                'Sales Rep 2 Info' => 'Sales Rep 2 email',
            ];
        }

        // Standard reverts for Solar, Turf, and Roofing companies
        if (in_array($companyType, ['solar', 'turf', 'roofing'])) {
            return [
                'Closer 1 Info' => 'Closer 1 email',
                'Closer 2 Info' => 'Closer 2 email',
                'Setter 1 Info' => 'Setter 1 email',
                'Setter 2 Info' => 'Setter 2 email',
            ];
        }

        return [];
    }

    /**
     * Get label reverts for each company type
     */
    private function getLabelRevertsForCompanyType($companyType): array
    {
        // Handle Mortgage company with specific business terminology
        if ($companyType === 'mortgage') {
            return [
                'MLO Email' => 'MLO',
                'Coordinator Email' => 'Coordinator',
                'LOA Email' => 'LOA',
                'Processor Email' => 'Processor',
            ];
        }

        // Handle Pest and Fiber companies with Sales Rep terminology
        if (in_array($companyType, ['pest', 'fiber'])) {
            return [
                'Sales Rep 1 Email' => 'Sales Rep 1',
                'Sales Rep 2 Email' => 'Sales Rep 2',
            ];
        }

        // Standard reverts for Solar, Turf, and Roofing companies
        if (in_array($companyType, ['solar', 'turf', 'roofing'])) {
            return [
                'Closer 1 Email' => 'Closer 1',
                'Closer 2 Email' => 'Closer 2',
                'Setter 1 Email' => 'Setter 1',
                'Setter 2 Email' => 'Setter 2',
            ];
        }

        return [];
    }
};
