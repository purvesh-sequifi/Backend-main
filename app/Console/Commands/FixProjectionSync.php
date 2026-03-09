<?php

namespace App\Console\Commands;

use App\Models\ProjectionUserCommission;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FixProjectionSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:projection-sync {--dry-run : Show what would be fixed without making changes} {--limit=50 : Limit number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix synchronization between projected_commission flag and ProjectionUserCommission data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('🔍 Analyzing projection synchronization issues...');
        $this->newLine();

        // Find sales with projected_commission = 1 but no projection data
        $missingProjectionData = SalesMaster::where('projected_commission', 1)
            ->whereNull('date_cancelled')
            ->whereNotExists(function ($query) {
                $query->select(\DB::raw(1))
                    ->from('projection_user_commissions')
                    ->whereColumn('projection_user_commissions.pid', 'sale_masters.pid');
            })
            ->limit($limit)
            ->get();

        $this->info("📊 Found {$missingProjectionData->count()} sales with projected_commission=1 but missing projection data");

        if ($missingProjectionData->count() === 0) {
            $this->info('✅ No synchronization issues found!');

            return Command::SUCCESS;
        }

        // Show sample of issues
        $this->table(
            ['PID', 'Customer Name', 'Signoff Date', 'Has Pending Milestones'],
            $missingProjectionData->take(10)->map(function ($sale) {
                $pendingMilestones = SaleProductMaster::where('pid', $sale->pid)
                    ->whereNull('milestone_date')
                    ->count();

                return [
                    $sale->pid,
                    $sale->customer_name,
                    $sale->customer_signoff,
                    $pendingMilestones > 0 ? "Yes ({$pendingMilestones})" : 'No',
                ];
            })->toArray()
        );

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->info("Would fix {$missingProjectionData->count()} sales by running projection sync");

            return Command::SUCCESS;
        }

        // Fix the issues
        $this->info('🔧 Fixing projection synchronization...');

        $bar = $this->output->createProgressBar($missingProjectionData->count());
        $bar->start();

        $fixed = 0;
        $errors = 0;

        foreach ($missingProjectionData as $sale) {
            try {
                // Run projection sync for this specific PID
                Artisan::call('syncSalesProjectionData:sync', ['pid' => $sale->pid]);

                // Verify projection data was created
                $projectionCount = ProjectionUserCommission::where('pid', $sale->pid)->count();

                if ($projectionCount > 0) {
                    $fixed++;
                    Log::info("Fixed projection sync for PID {$sale->pid}", [
                        'pid' => $sale->pid,
                        'projections_created' => $projectionCount,
                    ]);
                } else {
                    $errors++;
                    Log::warning("No projections created for PID {$sale->pid} after sync");
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error("Failed to fix projection sync for PID {$sale->pid}", [
                    'pid' => $sale->pid,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ Completed projection sync fix:');
        $this->info("   - Fixed: {$fixed} sales");
        $this->info("   - Errors: {$errors} sales");

        if ($errors > 0) {
            $this->warn('⚠️  Check logs for details on failed syncs');
        }

        return Command::SUCCESS;
    }
}
