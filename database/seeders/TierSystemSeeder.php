<?php

namespace Database\Seeders;

use App\Models\TierSystem;
use Illuminate\Database\Seeder;

class TierSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip in production - uses truncate() which deletes all data (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 TierSystemSeeder BLOCKED in ' . app()->environment() . ' - uses destructive truncate()');
            return;
        }

        TierSystem::truncate();
        $tierSystems = [
            [
                'value' => 'Tiered based on Individual performance',
            ],
            [
                'value' => 'Tiered based on Office Performance',
            ],
            [
                'value' => 'Tiered based on hiring/ recruitment performance',
            ],
            [
                'value' => 'Tiered based on job metrics',
            ],
            [
                'value' => 'Tiered based on Downline Performance',
            ],
            [
                'value' => 'Tiered based on job metrics (Exact Match)',
            ],
        ];

        foreach ($tierSystems as $tierSystem) {
            if (! TierSystem::where('value', $tierSystem['value'])->first()) {
                TierSystem::create(['value' => $tierSystem['value']]);
            }
        }
    }
}
