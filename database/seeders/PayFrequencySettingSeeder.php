<?php

namespace Database\Seeders;

use App\Models\FrequencyType;
use App\Models\payFrequencySetting;
use Illuminate\Database\Seeder;

class PayFrequencySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding in production environment (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 PayFrequencySettingSeeder BLOCKED in ' . app()->environment());
            return;
        }

        // Create default Weekly pay frequency setting
        // frequency_type_id = 2 is Weekly (matches FrequencyType::WEEKLY_ID)
        payFrequencySetting::updateOrCreate(
            ['frequency_type_id' => FrequencyType::WEEKLY_ID],
            [
                'first_months' => null,
                'first_day' => null,
                'day_of_week' => 'Friday',
                'day_of_months' => null,
                'pay_period' => null,
                'monthly_pay_type' => 'Other',
                'monthly_per_days' => null,
                'first_day_pay_of_manths' => null,
                'second_pay_day_of_month' => null,
                'deadline_to_run_payroll' => null,
                'first_pay_period_ends_on' => null,
            ]
        );
    }
}

