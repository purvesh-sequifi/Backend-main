<?php

namespace Database\Seeders;

use App\Models\EmailNotificationSetting;
use Illuminate\Database\Seeder;

class EmailNotificationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        EmailNotificationSetting::create([
            'company_id' => 1,
            'status' => 1,
            'email_setting_type' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
