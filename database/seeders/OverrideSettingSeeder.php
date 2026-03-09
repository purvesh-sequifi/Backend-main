<?php

namespace Database\Seeders;

use App\Models\OverrideSettings;
use Illuminate\Database\Seeder;

class OverrideSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OverrideSettings::create([
            'settlement_type' => 'Backend',
            'status' => 1,
        ]);
    }
}
