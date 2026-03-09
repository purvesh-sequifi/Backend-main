<?php

namespace Database\Seeders;

use App\Http\Controllers\API\V2\Sales\SalesStandardController;
use App\Traits\CompanyDependentSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MilestoneSeeder extends Seeder
{
    use CompanyDependentSeeder;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Validate prerequisites
        if (!$this->shouldRun()) {
            return;
        }

        try {
            if (DB::table('milestone_schemas')->count() === 0) {
                $salesStandardController = app(SalesStandardController::class);
                $salesStandardController->migrateProductData();
            }

            // Log that seeder ran independently
            $this->logSeederRun('MilestoneSeeder', true);
        } catch (\Exception $e) {
            // Log error
            $this->logSeederRun('MilestoneSeeder', false);
            
            if ($this->command) {
                $this->command->error('An error occurred during milestone data seeding: '.$e->getMessage());
            }
            throw $e;
        }
    }
}
