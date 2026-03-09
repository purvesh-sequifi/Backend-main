<?php

namespace App\Console\Commands;

use App\Models\ProjectionUserCommission;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use Illuminate\Console\Command;

class DebugProjectedEarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:projected-earnings {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug why projected earnings API returns empty results for a specific user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');

        $this->info("Debugging projected earnings for User ID: $userId");
        $this->newLine();

        // 1. Check if user exists
        $user = User::find($userId);
        if (! $user) {
            $this->error("User not found with ID: $userId");

            return Command::FAILURE;
        }

        $this->info("✅ User found: {$user->first_name} {$user->last_name}");

        // 2. Check sales PIDs associated with user
        $salesPids = SaleMasterProcess::where('closer1_id', $userId)
            ->orWhere('closer2_id', $userId)
            ->orWhere('setter1_id', $userId)
            ->orWhere('setter2_id', $userId)
            ->pluck('pid')->toArray();

        $this->info('📊 Sales PIDs associated with user: '.count($salesPids));
        if (count($salesPids) === 0) {
            $this->error('❌ No sales found for this user as closer or setter');

            return Command::SUCCESS;
        }

        $this->info('PIDs: '.implode(', ', array_slice($salesPids, 0, 10)).(count($salesPids) > 10 ? '...' : ''));

        // 3. Check if sales exist in SalesMaster
        $salesCount = SalesMaster::whereIn('pid', $salesPids)->count();
        $this->info("📈 Sales in SalesMaster: $salesCount");

        // 4. Check projection commission records
        $projectionCommissions = ProjectionUserCommission::where('user_id', $userId)->get();
        $this->info('💰 Total projection commission records: '.count($projectionCommissions));

        if (count($projectionCommissions) === 0) {
            $this->error('❌ No projection commission records found for user');
            $this->newLine();
            $this->info('🔧 Suggested fixes:');
            $this->info('1. Run projection sync: php artisan syncSalesProjectionData:sync');
            $this->info('2. Check if projection sync command is working properly');
            $this->info('3. Verify user has sales with projected commissions');

            return Command::SUCCESS;
        }

        // 5. Show projection commission details
        $this->info('💡 Projection commission breakdown:');
        $grouped = $projectionCommissions->groupBy('schema_name');
        foreach ($grouped as $schemaName => $commissions) {
            $total = $commissions->sum('amount');
            $this->info("  - {$schemaName}: $".number_format($total, 2).' ('.count($commissions).' records)');
        }

        // 6. Check for recent sales without projections
        $recentSales = SalesMaster::whereIn('pid', $salesPids)
            ->where('customer_signoff', '>=', now()->subMonths(6))
            ->whereNotIn('pid', $projectionCommissions->pluck('pid'))
            ->get();

        if (count($recentSales) > 0) {
            $this->warn('⚠️  Recent sales without projections: '.count($recentSales));
            foreach ($recentSales->take(5) as $sale) {
                $this->info("  - PID: {$sale->pid}, Customer: {$sale->customer_name}, Date: {$sale->customer_signoff}");
            }
        }

        $this->newLine();
        $this->info('✅ Debug complete!');

        return Command::SUCCESS;
    }
}
