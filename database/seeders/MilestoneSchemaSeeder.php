<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\MilestoneSchema;
use App\Models\MilestoneSchemaTrigger;
use App\Traits\CompanyDependentSeeder;
use Illuminate\Database\Seeder;

class MilestoneSchemaSeeder extends Seeder
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

        // Create only one default milestone schema
        $milestone = MilestoneSchema::updateOrCreate(
            ['prefix' => 'MS', 'schema_name' => 'Default'],
            [
                'schema_description' => 'Default Milestone',
                'status' => 1,
            ]
        );

        $companyProfile = CompanyProfile::first();

        // Initialize trigger data
        $triggerData = null;

        // Create only 1 payment trigger named "Final Payment" based on company type
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $triggerData = ['name' => 'Final Payment', 'on_trigger' => 'Initial Service Date'];
        } elseif ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $triggerData = ['name' => 'Final Payment', 'on_trigger' => 'M1 Date'];
        } else {
            // Default fallback for unknown company types
            $triggerData = ['name' => 'Final Payment', 'on_trigger' => 'M1 Date'];
        }

        if ($triggerData) {
            MilestoneSchemaTrigger::updateOrCreate(
                [
                    'milestone_schema_id' => $milestone->id,
                    'name' => $triggerData['name'],
                ],
                [
                    'on_trigger' => $triggerData['on_trigger'] ?? null,
                ]
            );
        }

        // Log that seeder ran independently
        $this->logSeederRun('MilestoneSchemaSeeder', true);
    }
}
