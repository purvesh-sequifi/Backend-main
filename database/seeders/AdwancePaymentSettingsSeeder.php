<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdvancePaymentSetting;
use Illuminate\Database\Seeder;

class AdwancePaymentSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use updateOrCreate to ensure record with ID 1 exists and prevent duplicates
        AdvancePaymentSetting::updateOrCreate(
            ['id' => 1],
            [
                'adwance_setting' => 'automatic',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
