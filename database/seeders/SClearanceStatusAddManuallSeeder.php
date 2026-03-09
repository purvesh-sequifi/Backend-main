<?php

namespace Database\Seeders;

use App\Models\SClearanceStatus;
use Illuminate\Database\Seeder;

class SClearanceStatusAddManuallSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SClearanceStatus::create([
            'status_name' => 'Manual Verification Pending',
        ]);
    }
}
