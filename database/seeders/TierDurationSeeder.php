<?php

namespace Database\Seeders;

use App\Models\TierDuration;
use Illuminate\Database\Seeder;

class TierDurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip in production - uses truncate() which deletes all data (case-insensitive check)
        $env = strtolower(app()->environment());
        if (in_array($env, ['production', 'prod']) || str_contains($env, 'prod')) {
            $this->command->error('🛑 TierDurationSeeder BLOCKED in ' . app()->environment() . ' - uses destructive truncate()');
            return;
        }

        TierDuration::truncate();
        $tierDurations = [
            [
                'value' => 'Per Pay Period',
            ],
            [
                'value' => 'Weekly',
            ],
            [
                'value' => 'Monthly',
            ],
            [
                'value' => 'Quarterly',
            ],
            [
                'value' => 'Semi-Annually',
            ],
            [
                'value' => 'Annually',
            ],
            [
                'value' => 'Per Recon Period',
            ],
            [
                'value' => 'On Demand',
            ],
            [
                'value' => 'Continuous',
            ],
        ];

        foreach ($tierDurations as $tierDuration) {
            if (! TierDuration::where('value', $tierDuration['value'])->first()) {
                TierDuration::create(['value' => $tierDuration['value']]);
            }
        }
    }
}
