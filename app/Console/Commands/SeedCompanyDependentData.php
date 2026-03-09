<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeedCompanyDependentData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:seed-dependent-data {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed data that depends on CompanyProfile (TierMetrics, Milestones, Products, ImportCategories)';

    /**
     * Required seeders (must succeed)
     *
     * @var array<string>
     */
    private array $requiredSeeders = [
        'TierMetricsSeeder',
        'SchemaTriggerDateSeeder',
        'MilestoneSchemaSeeder',
        'MilestoneSeeder',
        'ProductSeeder',
        'ImportCategorySeeder',
        'SalesImportTemplatesSeeder',  // Seed sales import templates for company type
        'SequiDocsEmailSettingSeeder',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Acquire lock to prevent concurrent seeding
        $lock = Cache::lock('company-seeding', 300); // 5 minutes

        if (!$lock->get()) {
            $this->error('❌ Another seeding process is already running');
            $this->error('Please wait for it to complete or try again in a few minutes');
            return Command::FAILURE;
        }

        try {
            $this->info('🚀 Starting company-dependent data seeding...');

            // Check if CompanyProfile exists
            if (!DB::table('company_profiles')->exists()) {
                $this->error('❌ CompanyProfile not found. Please create company profile first.');
                Log::error('Company-dependent seeding failed: CompanyProfile not found');
                return Command::FAILURE;
            }

            // Get company information
            $companyProfile = DB::table('company_profiles')->first();
            $companyType = $companyProfile ? $companyProfile->company_type : 'Unknown';
            $this->info("🏢 Company: {$companyProfile->name} (Type: {$companyType})");

            // Run seeders within transaction
            return $this->seedWithTransaction();

        } finally {
            $lock->release();
        }
    }

    /**
     * Run all seeders within a database transaction
     */
    private function seedWithTransaction(): int
    {
        $seederResults = [];

        try {
            DB::transaction(function () use (&$seederResults) {
                // Run required seeders
                foreach ($this->requiredSeeders as $seeder) {
                    $this->info("   📦 Seeding: {$seeder}");

                    $startTime = microtime(true);

                    Artisan::call('db:seed', [
                        '--class' => "Database\\Seeders\\{$seeder}",
                        '--force' => true,
                    ]);

                    $duration = round((microtime(true) - $startTime) * 1000);

                    // Get seeder output
                    $output = Artisan::output();
                    if ($output && trim($output)) {
                        $this->line($output);
                    }

                    $this->info("   ✓ {$seeder} completed ({$duration}ms)");

                    // Track success
                    $seederResults[] = [
                        'seeder' => $seeder,
                        'status' => 'success',
                        'duration_ms' => $duration,
                    ];
                }
            });

            // Success
            $this->newLine();

            $this->info('✅ All required seeders completed successfully');

            // Verify seeded data
            $this->newLine();
            $this->info('🔍 Verifying seeded data...');
            $verificationResults = $this->verifySeededData();

            // Display verification results
            foreach ($verificationResults as $check) {
                $status = $check['status'] === 'success' ? '✅' : ($check['status'] === 'info' ? 'ℹ️' : '❌');
                $this->line("   {$status} {$check['name']}: {$check['count']} records");
            }

            $allVerified = collect($verificationResults)->every(fn ($r) => in_array($r['status'], ['success', 'info']));

            if ($allVerified) {
                $this->newLine();
                $this->info('✅ All data verified successfully!');
            } else {
                $this->newLine();
                $this->warn('⚠️  Some data verification checks failed!');
            }

            Log::info('Company-dependent seeders executed successfully', [
                'results' => $seederResults,
                'verification' => $verificationResults,
            ]);

            // Store results in cache for API to retrieve
            cache()->put('last_seeding_results', [
                'status' => 'success',
                'seeders' => $seederResults,
                'verification' => $verificationResults,
                'completed_at' => now()->toDateTimeString(),
            ], 300); // Cache for 5 minutes

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Seeding failed: '.$e->getMessage());
            $this->error('🔄 All changes have been rolled back');
            $this->newLine();

            Log::error('Company-dependent seeding failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Store error in cache
            cache()->put('last_seeding_results', [
                'status' => 'failed',
                'seeders' => $seederResults,
                'error' => $e->getMessage(),
                'completed_at' => now()->toDateTimeString(),
            ], 300);

            return Command::FAILURE;
        }
    }

    /**
     * Verify that all required data was seeded correctly
     *
     * @return array<int, array<string, mixed>>
     */
    private function verifySeededData(): array
    {
        $results = [];

        // Get company profile to determine company type
        $companyProfile = DB::table('company_profiles')->first();
        $companyType = $companyProfile ? $companyProfile->company_type : 'unknown';

        // Check TierMetrics
        $tierMetricsCount = DB::table('tier_metrics')->count();
        $results[] = [
            'name' => 'TierMetrics',
            'table' => 'tier_metrics',
            'count' => $tierMetricsCount,
            'status' => $tierMetricsCount > 0 ? 'success' : 'failed',
            'expected_min' => 8,
        ];

        // Check SchemaTriggerDate
        $triggerDateCount = DB::table('schema_trigger_dates')->count();
        $results[] = [
            'name' => 'SchemaTriggerDate',
            'table' => 'schema_trigger_dates',
            'count' => $triggerDateCount,
            'status' => $triggerDateCount >= 2 ? 'success' : 'failed',
            'expected_min' => 2,
        ];

        // Check MilestoneSchema
        $milestoneSchemaCount = DB::table('milestone_schemas')->count();
        $results[] = [
            'name' => 'MilestoneSchema',
            'table' => 'milestone_schemas',
            'count' => $milestoneSchemaCount,
            'status' => $milestoneSchemaCount > 0 ? 'success' : 'failed',
            'expected_min' => 1,
        ];

        // Check MilestoneSchemaTrigger
        $milestoneTriggerCount = DB::table('milestone_schema_trigger')->count();
        $results[] = [
            'name' => 'MilestoneSchemaTrigger',
            'table' => 'milestone_schema_trigger',
            'count' => $milestoneTriggerCount,
            'status' => $milestoneTriggerCount > 0 ? 'success' : 'failed',
            'expected_min' => 1,
        ];

        // Check Products
        $productsCount = DB::table('products')->count();
        $results[] = [
            'name' => 'Products',
            'table' => 'products',
            'count' => $productsCount,
            'status' => $productsCount > 0 ? 'success' : 'failed',
            'expected_min' => 1,
        ];

        // Check ProductMilestoneHistories
        $productMilestoneCount = DB::table('product_milestone_histories')->count();
        $results[] = [
            'name' => 'ProductMilestoneHistories',
            'table' => 'product_milestone_histories',
            'count' => $productMilestoneCount,
            'status' => $productMilestoneCount > 0 ? 'success' : 'failed',
            'expected_min' => 1,
        ];

        // Check ImportCategories
        $importCategoriesCount = DB::table('import_categories')->count();
        $results[] = [
            'name' => 'ImportCategories',
            'table' => 'import_categories',
            'count' => $importCategoriesCount,
            'status' => $importCategoriesCount > 0 ? 'success' : 'failed',
            'expected_min' => 1,
        ];

        // Check ImportCategoryDetails
        $importDetailsCount = DB::table('import_category_details')->count();
        $results[] = [
            'name' => 'ImportCategoryDetails',
            'table' => 'import_category_details',
            'count' => $importDetailsCount,
            'status' => $importDetailsCount >= 10 ? 'success' : 'failed',
            'expected_min' => 10,
        ];

        // Check SequiDocsEmailSettings
        $emailSettingsCount = DB::table('sequi_docs_email_settings')->count();
        $results[] = [
            'name' => 'SequiDocsEmailSettings',
            'table' => 'sequi_docs_email_settings',
            'count' => $emailSettingsCount,
            'status' => $emailSettingsCount > 0 ? 'success' : 'failed',
            'expected_min' => 1,
        ];

        // Add company type info
        $results[] = [
            'name' => 'CompanyType',
            'table' => 'company_profiles',
            'count' => 1,
            'status' => 'info',
            'value' => $companyType,
        ];

        return $results;
    }
}
