<?php

namespace Database\Seeders;

use App\Models\TierLevelSetting;
use Illuminate\Database\Seeder;

class TierLevelSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TierLevelSetting::create([
            'tier_type_id' => 1,
            'tier_setting_id' => 1,
            'scale_based_on' => 'Monthly',
            'shifts_on' => 'Quaterly',
            'rest' => 'Bi-Monthly',
        ]);
        TierLevelSetting::create([
            'tier_type_id' => 2,
            'tier_setting_id' => 1,
            'scale_based_on' => 'Monthly',
            'shifts_on' => 'Quaterly',
            'rest' => 'Bi-Monthly',
        ]);
        TierLevelSetting::create([
            'tier_type_id' => 3,
            'tier_setting_id' => 1,
            'scale_based_on' => 'Monthly',
            'shifts_on' => 'Quaterly',
            'rest' => 'Bi-Monthly',
        ]);
    }
}
