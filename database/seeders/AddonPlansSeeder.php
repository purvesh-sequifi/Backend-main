<?php

namespace Database\Seeders;

use App\Models\AddOnPlans;
use Illuminate\Database\Seeder;

class AddonPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AddOnPlans::create([
            'name' => 'Document Management',
            'rack_price' => '1',
            'rack_price_type' => 'watt',
            'discount_type' => 'watt',
            'discount_price' => '1',

        ]);
    }
}
