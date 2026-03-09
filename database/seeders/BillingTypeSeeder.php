<?php

namespace Database\Seeders;

use App\Models\BillingType;
use Illuminate\Database\Seeder;

class BillingTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BillingType::insert([
            [
                'name' => 'Billing on monthly basis',
                'frequency' => 'Monthly',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Pro-Rata on 1st of the month',
                'frequency' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Monthly min at $1000 or actual Bill whichever is h..',
                'frequency' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ]
        );

    }
}
