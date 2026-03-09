<?php

namespace Database\Seeders;

use App\Models\EmployeeIdSetting;
use Illuminate\Database\Seeder;

class EmployeeIdSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding in production environment (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 EmployeeIdSettingSeeder BLOCKED in ' . app()->environment());
            return;
        }

        // Create default employee ID settings
        // DOMAIN_NAME is fetched from environment variable
        $domainName = env('DOMAIN_NAME', 'DOMAIN_NAME');

        EmployeeIdSetting::updateOrCreate(
            ['id' => 1],
            [
                'prefix' => 'Prefix',
                'id_code' => $domainName,
                'id_code_no_to_start_from' => '0001',
                'onbording_prefix' => 'Prefix',
                'onbording_id_code' => $domainName . '-ONB',
                'onbording_id_code_no_to_start_from' => '0001',
                'select_offer_letter_to_send' => null,
                'select_agreement_to_sign' => null,
                'automatic_hiring_status' => 0,
                'approval_onboarding_position' => null,
                'require_approval_status' => 0,
                'special_approval_status' => 0,
                'approval_position' => null,
            ]
        );
    }
}

