<?php

namespace Database\Seeders;

use App\Models\FrequencyType;
use Illuminate\Database\Seeder;

class FrequencyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding in production environment (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 FrequencyTypeSeeder BLOCKED in ' . app()->environment());
            return;
        }

        // Create all frequency types with correct IDs
        // ID 2: Weekly (matches FrequencyType::WEEKLY_ID = 2)
        FrequencyType::updateOrCreate(
            ['id' => FrequencyType::WEEKLY_ID],
            [
                'name' => 'Weekly',
                'status' => 1,
            ]
        );

        // ID 3: Bi-Weekly (matches FrequencyType::BI_WEEKLY_ID = 3)
        FrequencyType::updateOrCreate(
            ['id' => FrequencyType::BI_WEEKLY_ID],
            [
                'name' => 'Bi-Weekly',
                'status' => 1,
            ]
        );

        // ID 4: Semi-Monthly (matches FrequencyType::SEMI_MONTHLY_ID = 4)
        FrequencyType::updateOrCreate(
            ['id' => FrequencyType::SEMI_MONTHLY_ID],
            [
                'name' => 'Semi-Monthly',
                'status' => 1,
            ]
        );

        // ID 5: Monthly (matches FrequencyType::MONTHLY_ID = 5)
        FrequencyType::updateOrCreate(
            ['id' => FrequencyType::MONTHLY_ID],
            [
                'name' => 'Monthly',
                'status' => 1,
            ]
        );

        // ID 6: Daily Pay (matches FrequencyType::DAILY_PAY_ID = 6)
        FrequencyType::updateOrCreate(
            ['id' => FrequencyType::DAILY_PAY_ID],
            [
                'name' => 'Daily Pay',
                'status' => 1,
            ]
        );
    }
}
