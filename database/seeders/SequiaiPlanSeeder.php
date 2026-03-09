<?php

namespace Database\Seeders;

use App\Models\SequiaiPlan;
use Illuminate\Database\Seeder;

class SequiaiPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $checkPlan = SequiaiPlan::where('name', 'default')->first();
        if ($checkPlan == null) {
            SequiaiPlan::create([
                'name' => 'default',
                'price' => 25,
                'min_request' => 1000000,
                'additional_price' => 10,
                'additional_min_request' => 1000000,
            ]);
        }
    }
}
