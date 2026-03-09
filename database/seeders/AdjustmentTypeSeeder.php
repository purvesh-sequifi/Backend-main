<?php

namespace Database\Seeders;

use App\Models\AdjustementType;
use DB;
use Illuminate\Database\Seeder;

class AdjustmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $name =
        [
            'Payroll Dispute',
            'Reimbursement',
            'Bonus',
            'Advance',
            'Fine/fee',
            'Incentive',
            'Leave(Unpaid)',
            'PTO',
            'Time Adjustment',
            'Commission',
            'Overrides',
            'Payroll',
        ];

    public function run(): void
    {
        DB::table('adjustement_types')->truncate();

        foreach (range(1, count($this->name)) as $index) {
            AdjustementType::create([
                'name' => $this->name[$index - 1],
            ]);

        }
    }
}
