<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CompanyProfile;
use App\Traits\CompanyDependentSeeder;

/**
 * Sales Import Templates Seeder
 *
 * This seeder ensures sales import templates are populated for ALL company types.
 * It safely calls the migration logic if tables are empty.
 *
 * This runs as part of: php artisan company:seed-dependent-data
 * (Called after CompanyProfile is created)
 */
class SalesImportTemplatesSeeder extends Seeder
{
    use CompanyDependentSeeder;

    private array $allCompanyTypes = ['pest', 'solar', 'fiber', 'turf', 'mortgage', 'roofing'];

    public function run(): void
    {
        // Validate prerequisites
        if (!$this->shouldRun()) {
            return;
        }

        try {
            // Seed templates for ALL company types
            foreach ($this->allCompanyTypes as $companyType) {
                $this->seedCompanyType($companyType);
            }

            // Log that seeder ran independently
            $this->logSeederRun('SalesImportTemplatesSeeder', true);

        } catch (\Exception $e) {
            // Log error
            log_exception('Sales import template seeding failed', $e);
            $this->logSeederRun('SalesImportTemplatesSeeder', false);
            throw $e;
        }
    }

    /**
     * Seed templates for a specific company type
     */
    private function seedCompanyType(string $companyType): void
    {
        // Check if this company type needs seeding
        if (!$this->checkIfNeedsSeeding($companyType)) {
            return;
        }

        // Seed fields and templates
        $this->seedFields($companyType);
        $this->seedTemplates($companyType);
    }

    /**
     * Check if a company type is missing fields or templates
     */
    private function checkIfNeedsSeeding(string $companyType): bool
    {
        $fieldsTable = "{$companyType}_sales_import_fields";
        $templatesTable = "{$companyType}_sales_import_templates";
        $detailsTable = "{$companyType}_sales_import_template_details";

        // Check if tables exist
        if (!DB::getSchemaBuilder()->hasTable($fieldsTable) ||
            !DB::getSchemaBuilder()->hasTable($templatesTable) ||
            !DB::getSchemaBuilder()->hasTable($detailsTable)) {
            return true;
        }

        $fields = DB::table($fieldsTable)->count();
        $templates = DB::table($templatesTable)->count();
        $details = DB::table($detailsTable)->count();

        // If any table is empty, we need seeding
        return ($fields === 0 || $templates === 0 || $details === 0);
    }

    /**
     * Seed sales import fields for a specific company type
     */
    private function seedFields(string $companyType): void
    {
        $fieldsCount = DB::table("{$companyType}_sales_import_fields")->count();

        if ($fieldsCount > 0) {
            return;
        }

        // For roofing, use dedicated migration
        if ($companyType === 'roofing') {
            $migrationPath = database_path('migrations/2025_07_22_025316_create_roofing_sales_import_fields_table.php');

            if (!file_exists($migrationPath)) {
                return;
            }

            $migration = require $migrationPath;
            $migration->up();
            return;
        }

        // For other types, use the main migration
        $migrationPath = database_path('migrations/2025_07_03_052208_create_company_type_import_category_details_table.php');

        if (!file_exists($migrationPath)) {
            return;
        }

        $migration = require $migrationPath;
        $migration->up();
    }

    /**
     * Seed sales import templates for a specific company type
     */
    private function seedTemplates(string $companyType): void
    {
        $templatesCount = DB::table("{$companyType}_sales_import_templates")->count();

        if ($templatesCount > 0) {
            return;
        }

        // Migration file mapping
        $migrations = [
            'pest' => '2025_07_05_052957_seed_pest_sales_import_template_details.php',
            'solar' => '2025_07_07_083759_seed_solar_sales_import_template_details.php',
            'turf' => '2025_07_07_084427_seed_turf_sales_import_template_details.php',
            'fiber' => '2025_07_07_083442_seed_fiber_sales_import_template_details.php',
            'mortgage' => '2025_07_06_070642_seed_mortgage_sales_import_template_details.php',
            'roofing' => '2025_07_22_224826_seed_roofing_sales_import_template_details.php',
        ];

        if (!isset($migrations[$companyType])) {
            return;
        }

        $migrationPath = database_path('migrations/' . $migrations[$companyType]);

        if (!file_exists($migrationPath)) {
            return;
        }

        $migration = require $migrationPath;
        $migration->up();
    }

}

