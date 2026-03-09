<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SalesMaster;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestPerformanceTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:test {--pids=10 : Number of PIDs to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the performance tracking system with sample data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pidCount = (int) $this->option('pids');
        
        $this->info("🚀 Testing Performance Tracking System");
        $this->info("Fetching {$pidCount} sample PIDs for testing...");

        // Get sample PIDs from the database
        $samplePids = SalesMaster::whereNotNull('pid')
            ->limit($pidCount)
            ->pluck('pid')
            ->toArray();

        if (empty($samplePids)) {
            $this->error("No PIDs found in the database. Please ensure you have sales data.");
            return Command::FAILURE;
        }

        $this->info("Found " . count($samplePids) . " PIDs: " . implode(', ', array_slice($samplePids, 0, 5)) . (count($samplePids) > 5 ? '...' : ''));

        // Create a mock request
        $request = new Request();
        $request->merge(['pids' => $samplePids]);

        // Call the recalculateSaleAll method
        $this->info("🔄 Triggering recalculate-sale-all with performance tracking...");
        
        try {
            $controller = new \App\Http\Controllers\API\V2\Sales\SalesController();
            
            // Capture the response
            ob_start();
            $controller->recalculateSaleAll($request);
            $output = ob_get_clean();
            
            $this->info("✅ Successfully dispatched recalculation jobs!");
            $this->info("📊 You can monitor the progress using:");
            $this->info("   • Dashboard: " . url('/performance-dashboard'));
            $this->info("   • API: " . url('/api/v2/sales/performance/active-batches'));
            $this->info("   • Horizon: " . url('/horizon'));
            
            $this->newLine();
            $this->info("🔍 Performance Monitoring Commands:");
            $this->info("   php artisan performance:show-recent");
            $this->info("   php artisan performance:compare");
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
