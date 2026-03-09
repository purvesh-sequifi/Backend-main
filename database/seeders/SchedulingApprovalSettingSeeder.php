<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchedulingApprovalSetting;
use Illuminate\Database\Seeder;

class SchedulingApprovalSettingSeeder extends Seeder
{
    public function run(): void
    {
        SchedulingApprovalSetting::firstOrCreate(
            [],
            ['scheduling_setting' => 'automatic']
        );
    }
}
