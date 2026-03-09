<?php

namespace Database\Seeders;

use App\Models\MilestoneSchema;
use App\Models\MilestoneSchemaTrigger;
use App\Models\ProductCode;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Traits\CompanyDependentSeeder;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use CompanyDependentSeeder;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Validate prerequisites with dependencies
        if (!$this->shouldRun([
            'milestone_schemas' => 'Milestone Schemas',
        ])) {
            return;
        }

        // Create only one default product
        $product = Products::updateOrCreate(
            ['name' => 'Default Product'],
            [
                'product_id' => 'DBP',
                'description' => 'Default Product',
            ]
        );

        // Create default product code
        if ($product) {
            ProductCode::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'product_code' => 'DBP',
                ]
            );
        }

        // Get the first milestone schema
        $milestoneSchema = MilestoneSchema::first();
        $milestoneTrigger = MilestoneSchemaTrigger::first();

        // Only create milestone history if not exists
        if ($product && $milestoneSchema && $milestoneTrigger) {
            ProductMilestoneHistories::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'milestone_schema_id' => $milestoneSchema->id,
                    'effective_date' => now()->format('Y-m-d'),
                    'clawback_exempt_on_ms_trigger_id' => $milestoneTrigger->id,
                    'product_redline' => 2.30,
                ]
            );
        }

        // Log that seeder ran independently
        $this->logSeederRun('ProductSeeder', true);
    }
}
