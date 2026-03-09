<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CompanyProfile;

// Import all required seeders (direct invocation bypasses console.php blocks)
use Database\Seeders\TierMetricsSeeder;
use Database\Seeders\SchemaTriggerDateSeeder;
use Database\Seeders\MilestoneSchemaSeeder;
use Database\Seeders\MilestoneSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\ImportCategorySeeder;
use Database\Seeders\SalesImportTemplatesSeeder;
use Database\Seeders\SequiDocsEmailSettingSeeder;

class SeedCompanyDependentDataJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 600;

    /**
     * Company profile ID to seed data for
     */
    protected int $companyProfileId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $companyProfileId)
    {
        $this->companyProfileId = $companyProfileId;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'seed-company-' . $this->companyProfileId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $companyProfile = CompanyProfile::find($this->companyProfileId);

        if (!$companyProfile) {
            Log::error('Company profile not found for seeding', [
                'company_profile_id' => $this->companyProfileId
            ]);
            $this->fail(new \Exception('Company profile not found'));
            return;
        }

        // Update status to seeding
        $companyProfile->update(['setup_status' => 'seeding']);

        try {
            // Define seeders to run (in order)
            // Using direct invocation to bypass console.php command override in production
            $seeders = [
                TierMetricsSeeder::class,
                SchemaTriggerDateSeeder::class,
                MilestoneSchemaSeeder::class,
                MilestoneSeeder::class,
                ProductSeeder::class,
                ImportCategorySeeder::class,
                SalesImportTemplatesSeeder::class,
                SequiDocsEmailSettingSeeder::class,
            ];

            Log::info('Starting company data seeding', [
                'company_profile_id' => $this->companyProfileId,
                'seeder_count' => count($seeders)
            ]);

            // Run all seeders in a transaction (atomic operation - all or nothing)
            DB::transaction(function () use ($seeders) {
                foreach ($seeders as $seederClass) {
                    Log::info('Running seeder', ['seeder' => class_basename($seederClass)]);
                    
                    $seederInstance = app()->make($seederClass);
                    $seederInstance->run();
                    
                    Log::info('Seeder completed', ['seeder' => class_basename($seederClass)]);
                }
            });

            // Mark setup as complete (only reached if transaction succeeds)
            $companyProfile->update([
                'setup_status' => 'completed',
                'setup_completed_at' => now()
            ]);

        } catch (\Exception $e) {
            // Mark setup as failed
            $companyProfile->update([
                'setup_status' => 'failed',
                'setup_error' => $e->getMessage()
            ]);

            // Build log context (conditionally include trace for non-production)
            $logContext = [
                'company_id' => $companyProfile->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            // Only include stack trace in non-production environments
            if (!app()->isProduction()) {
                $logContext['trace'] = $e->getTraceAsString();
            }

            Log::error('Company setup failed', $logContext);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $companyProfile = CompanyProfile::find($this->companyProfileId);

        if ($companyProfile) {
            $companyProfile->update([
                'setup_status' => 'failed',
                'setup_error' => $exception->getMessage()
            ]);
        }

        Log::critical('Company seeding job failed permanently', [
            'company_profile_id' => $this->companyProfileId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}

