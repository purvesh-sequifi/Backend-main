<?php

namespace Database\Seeders;

use App\Models\PayrollStatus;
use Illuminate\Database\Seeder;

class PayrollStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $status =
        [
            'pending',
            'finalize',
            'paid',
            'next_payroll',
            'skipped',
            'move_reconciliation',
            'Not Undo',
        ];

    public function run(): void
    {
        foreach (range(1, count($this->status)) as $index) {
            PayrollStatus::create([
                'status' => $this->status[$index - 1],
            ]);

        }
    }
}
