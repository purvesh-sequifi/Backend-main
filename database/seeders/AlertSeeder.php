<?php

namespace Database\Seeders;

use App\Models\Alerts;
// use Illuminate\Console\View\Components\Alert;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Alerts::create([
            'name' => 'Marketing Deal(MD)',
            'status' => '1',
        ]);
        Alerts::create([
            'name' => 'Incomple Account Alert',
            'status' => '1',
        ]);
    }
}
