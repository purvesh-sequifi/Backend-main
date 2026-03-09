<?php

namespace Database\Seeders;

use App\Models\TierSettings;
use Illuminate\Database\Seeder;

class TierSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TierSettings::create([
            'status' => '1',
        ]);
    }
}
