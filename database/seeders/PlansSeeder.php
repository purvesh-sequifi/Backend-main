<?php

namespace Database\Seeders;

use App\Models\Plans;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plans::create([
            'name' => 'Every Unique, M2 Complete PID',
            'unique_pid_rack_price' => '0.01',
            'unique_pid_discount_price' => '0.005',
            'm2_rack_price' => '0.01',
            'm2_discount_price' => '0.005',
        ]);

    }
}
