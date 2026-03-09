<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyCompanyDependentData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:verify-dependent-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify that all company-dependent data has been seeded correctly';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Verifying company-dependent data...');
        $this->newLine();

        // Check if CompanyProfile exists
        $companyProfile = DB::table('company_profiles')->first();

        if (!$companyProfile) {
            $this->error('❌ CompanyProfile not found!');
            return Command::FAILURE;
        }

        $this->info("Company: {$companyProfile->name}");
        $this->info("Type: {$companyProfile->company_type}");
        $this->newLine();

        // Verify all tables
        $verificationResults = $this->verifyAllTables();

        // Display results in a table
        $tableData = [];
        $allPassed = true;

        foreach ($verificationResults as $result) {
            $status = $result['status'] === 'success' ? '✅' : ($result['status'] === 'info' ? 'ℹ️' : '❌');

            if ($result['status'] === 'failed') {
                $allPassed = false;
            }

            $tableData[] = [
                'Status' => $status,
                'Component' => $result['name'],
                'Table' => $result['table'],
                'Count' => $result['count'] ?? 'N/A',
                'Expected Min' => $result['expected_min'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Status', 'Component', 'Table', 'Count', 'Expected Min'],
            $tableData
        );

        $this->newLine();

        if ($allPassed) {
            $this->info('✅ All verification checks passed!');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Some verification checks failed!');
            $this->newLine();
            $this->warn('💡 Run: php artisan company:seed-dependent-data');
            return Command::FAILURE;
        }
    }

    /**
     * Verify all company-dependent tables
     */
    private function verifyAllTables(): array
    {
        $results = [];

        // Get company profile
        $companyProfile = DB::table('company_profiles')->first();
        $companyType = $companyProfile ? $companyProfile->company_type : 'unknown';

        // 1. TierMetrics (8 tiers)
        $tierMetricsCount = DB::table('tier_metrics')->count();
        $results[] = [
            'name' => 'TierMetrics',
            'table' => 'tier_metrics',
            'count' => $tierMetricsCount,
            'status' => $tierMetricsCount >= 8 ? 'success' : 'failed',
            'expected_min' => 8
        ];

        // 2. SchemaTriggerDate (at least 2: milestone date change, job status change)
        $triggerDateCount = DB::table('schema_trigger_dates')->count();
        $results[] = [
            'name' => 'SchemaTriggerDate',
            'table' => 'schema_trigger_dates',
            'count' => $triggerDateCount,
            'status' => $triggerDateCount >= 2 ? 'success' : 'failed',
            'expected_min' => 2
        ];

        // 3. MilestoneSchema (default milestone schemas)
        $milestoneSchemaCount = DB::table('milestone_schemas')->count();
        $results[] = [
            'name' => 'MilestoneSchema',
            'table' => 'milestone_schemas',
            'count' => $milestoneSchemaCount,
            'status' => $milestoneSchemaCount > 0 ? 'success' : 'failed',
            'expected_min' => 1
        ];

        // 4. MilestoneSchemaTrigger (triggers for milestones)
        $milestoneTriggerCount = DB::table('milestone_schema_trigger')->count();
        $results[] = [
            'name' => 'MilestoneSchemaTrigger',
            'table' => 'milestone_schema_trigger',
            'count' => $milestoneTriggerCount,
            'status' => $milestoneTriggerCount > 0 ? 'success' : 'failed',
            'expected_min' => 1
        ];

        // 5. Products (default products)
        $productsCount = DB::table('products')->count();
        $results[] = [
            'name' => 'Products',
            'table' => 'products',
            'count' => $productsCount,
            'status' => $productsCount > 0 ? 'success' : 'failed',
            'expected_min' => 1
        ];

        // 6. ProductMilestoneHistories
        $productMilestoneCount = DB::table('product_milestone_histories')->count();
        $results[] = [
            'name' => 'ProductMilestoneHistories',
            'table' => 'product_milestone_histories',
            'count' => $productMilestoneCount,
            'status' => $productMilestoneCount > 0 ? 'success' : 'failed',
            'expected_min' => 1
        ];

        // 7. ImportCategories (company-specific import categories)
        $importCategoriesCount = DB::table('import_categories')->count();
        $results[] = [
            'name' => 'ImportCategories',
            'table' => 'import_categories',
            'count' => $importCategoriesCount,
            'status' => $importCategoriesCount > 0 ? 'success' : 'failed',
            'expected_min' => 1
        ];

        // 8. ImportCategoryDetails (mapped fields for imports)
        $importDetailsCount = DB::table('import_category_details')->count();
        $results[] = [
            'name' => 'ImportCategoryDetails',
            'table' => 'import_category_details',
            'count' => $importDetailsCount,
            'status' => $importDetailsCount >= 10 ? 'success' : 'failed',
            'expected_min' => 10
        ];

        // 9. SequiDocsEmailSettings (email templates)
        $emailSettingsCount = DB::table('sequi_docs_email_settings')->count();
        $results[] = [
            'name' => 'SequiDocsEmailSettings',
            'table' => 'sequi_docs_email_settings',
            'count' => $emailSettingsCount,
            'status' => $emailSettingsCount > 0 ? 'success' : 'failed',
            'expected_min' => 1
        ];

        // 10. All Company Type import tables (seeded for ALL types)
        $this->newLine();
        $this->comment('📋 Sales import tables (all company types):');

        $allCompanyTypes = ['pest', 'solar', 'turf', 'fiber', 'mortgage', 'roofing'];

        foreach ($allCompanyTypes as $type) {
            $importFieldsTable = "{$type}_sales_import_fields";
            $importTemplatesTable = "{$type}_sales_import_templates";
            $importTemplateDetailsTable = "{$type}_sales_import_template_details";

            try {
                $importFieldsCount = DB::table($importFieldsTable)->count();
                $importTemplatesCount = DB::table($importTemplatesTable)->count();
                $importTemplateDetailsCount = DB::table($importTemplateDetailsTable)->count();

                $allSeeded = ($importFieldsCount > 0 && $importTemplatesCount > 0 && $importTemplateDetailsCount > 0);

                $results[] = [
                    'name' => '  ' . ucfirst($type) . ' Import Fields',
                    'table' => $importFieldsTable,
                    'count' => $importFieldsCount,
                    'status' => $allSeeded ? 'info' : 'failed',
                    'expected_min' => '1+'
                ];

                $results[] = [
                    'name' => '  ' . ucfirst($type) . ' Import Templates',
                    'table' => $importTemplatesTable,
                    'count' => $importTemplatesCount,
                    'status' => $allSeeded ? 'info' : 'failed',
                    'expected_min' => '1+'
                ];

                $results[] = [
                    'name' => '  ' . ucfirst($type) . ' Template Details',
                    'table' => $importTemplateDetailsTable,
                    'count' => $importTemplateDetailsCount,
                    'status' => $allSeeded ? 'info' : 'failed',
                    'expected_min' => '1+'
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'name' => '  ' . ucfirst($type) . ' Import (Error)',
                    'table' => $importFieldsTable,
                    'count' => 'Error',
                    'status' => 'failed',
                    'expected_min' => 'N/A'
                ];
            }
        }

        return $results;
    }
}
