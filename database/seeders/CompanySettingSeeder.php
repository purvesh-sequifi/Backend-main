<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanySetting::create([
            'type' => 'reconciliation',
            'status' => 1,

        ]);
        CompanySetting::create([
            'type' => 'overrides',
            'status' => 1,

        ]);
        CompanySetting::create([
            'type' => 'tier',
            'status' => 1,

        ]);
        CompanySetting::create([
            'type' => 'marketing Deal',
            'status' => 1,

        ]);
        CompanySetting::create([
            'type' => 'w2',
            'status' => 0,

        ]);
    }
}
