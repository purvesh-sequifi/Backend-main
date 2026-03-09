<?php

namespace Database\Seeders;

use App\Models\MarkAccountStatus;
use Illuminate\Database\Seeder;

class MarkAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MarkAccountStatus::create([
            'account_status' => 'Clawed Back',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'M1 Pending',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'Total Commission Calculated',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'M1 calculated',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'M2 calculated',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'Canceled',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'M1 Paid',
        ]);
        MarkAccountStatus::create([
            'account_status' => 'M2 Paid',
        ]);

    }
}
