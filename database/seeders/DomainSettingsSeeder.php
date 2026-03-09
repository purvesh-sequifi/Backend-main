<?php

namespace Database\Seeders;

use App\Models\DomainSetting;
use Illuminate\Database\Seeder;

class DomainSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $domain = [];

    private $status = [];

    private $settingType = [];

    public function run(): void
    {
        // Use updateOrCreate to make this seeder idempotent
        // Only sequifi.com domain as enabled
        DomainSetting::updateOrCreate(
            ['domain_name' => 'sequifi.com'],
            [
                'status' => 1,
                'email_setting_type' => 1,
            ]
        );
    }
}
