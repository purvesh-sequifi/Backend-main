<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\SchemaTriggerDate;
use App\Traits\CompanyDependentSeeder;
use Illuminate\Database\Seeder;

class SchemaTriggerDateSeeder extends Seeder
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

        $companyProfile = CompanyProfile::first();

        // Initialize trigger dates array
        $triggerDates = [];

        // Create trigger date options based on company type
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $triggerDates = [
                ['name' => 'Initial Service Date', 'color_code' => null],
                ['name' => 'Service Completion Date', 'color_code' => null],
                ['name' => 'Final Payment', 'color_code' => null],
            ];
        } elseif ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $triggerDates = [
                ['name' => 'M1 Date', 'color_code' => null],
                ['name' => 'M2 Date', 'color_code' => null],
                ['name' => 'Final Payment', 'color_code' => null],
            ];
        } else {
            // Default fallback for unknown company types
            $triggerDates = [
                ['name' => 'M1 Date', 'color_code' => null],
                ['name' => 'M2 Date', 'color_code' => null],
                ['name' => 'Final Payment', 'color_code' => null],
            ];
        }

        // Create or update trigger dates
        foreach ($triggerDates as $triggerDate) {
            SchemaTriggerDate::updateOrCreate(
                ['name' => $triggerDate['name']],
                ['color_code' => $triggerDate['color_code']]
            );
        }

        // Log that seeder ran independently
        $this->logSeederRun('SchemaTriggerDateSeeder', true);
    }
}

