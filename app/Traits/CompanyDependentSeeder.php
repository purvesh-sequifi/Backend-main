<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\DB;

/**
 * Trait for company-dependent seeders
 * Provides validation and safeguards for seeders that depend on company profile
 */
trait CompanyDependentSeeder
{
    /**
     * Validate that company profile exists before seeding
     */
    protected function validateCompanyProfile(): ?CompanyProfile
    {
        $companyProfile = CompanyProfile::first();

        if (!$companyProfile) {
            if ($this->command) {
                $this->command->error('❌ CompanyProfile not found.');
                $this->command->warn('⚠️  This seeder should be run via: SeedCompanyDependentDataJob');
                $this->command->info('💡 Or create a company profile first via the API endpoint.');
            }
            return null;
        }

        return $companyProfile;
    }

    /**
     * Check if required tables have data
     */
    protected function checkTableExists(string $tableName, string $friendlyName = null): bool
    {
        $friendlyName = $friendlyName ?? $tableName;

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            if ($this->command) {
                $this->command->error("❌ Table '{$tableName}' does not exist.");
            }
            return false;
        }

        return true;
    }

    /**
     * Check if a table has data
     */
    protected function checkTableHasData(string $tableName, string $friendlyName = null): bool
    {
        $friendlyName = $friendlyName ?? $tableName;

        if (!$this->checkTableExists($tableName, $friendlyName)) {
            return false;
        }

        $count = DB::table($tableName)->count();

        if ($count === 0) {
            if ($this->command) {
                $this->command->warn("⚠️  Table '{$friendlyName}' is empty. Some features may not work correctly.");
            }
            return false;
        }

        return true;
    }

    /**
     * Warn if running in production
     */
    protected function warnIfProduction(): bool
    {
        $env = app()->environment();
        $isProduction = in_array(strtolower($env), ['production', 'prod'])
            || str_contains(strtolower($env), 'prod');

        if ($isProduction && $this->command) {
            $this->command->warn("⚠️  Running in {$env} environment!");
            $this->command->warn('⚠️  Consider using SeedCompanyDependentDataJob instead for safety.');
        }

        return $isProduction;
    }

    /**
     * Check if seeder should run (validates all prerequisites)
     */
    protected function shouldRun(array $dependencies = []): bool
    {
        // Warn if in production
        $this->warnIfProduction();

        // Check company profile exists
        $companyProfile = $this->validateCompanyProfile();
        if (!$companyProfile) {
            return false;
        }

        // Check dependencies
        foreach ($dependencies as $table => $friendlyName) {
            if (!$this->checkTableHasData($table, $friendlyName)) {
                if ($this->command) {
                    $this->command->error("❌ Dependency missing: {$friendlyName}");
                    $this->command->info("💡 Run dependent seeders first or use the job.");
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Log seeder execution
     */
    protected function logSeederRun(string $seederName, bool $success = true): void
    {
        $status = $success ? '✅ SUCCESS' : '❌ FAILED';
        $companyProfile = CompanyProfile::first();

        \Log::info("Seeder executed independently: {$seederName}", [
            'status' => $status,
            'company_id' => $companyProfile->id ?? null,
            'company_type' => $companyProfile->company_type ?? null,
            'environment' => app()->environment(),
            'run_individually' => true,
        ]);
    }
}

