<?php

namespace Database\Seeders;

use App\Models\Subscriptions;
use Illuminate\Database\Seeder;

class SubScriptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $plan = [
        1,
    ];

    private $planId = [
        1,

    ];

    private $startDate = [
        '2023-08-01',

    ];

    private $endDate = [
        '2023-08-31',
    ];

    private $status = [
        1,

    ];

    private $paidStatus = [
        0,

    ];

    private $totalPid = [
        0,

    ];

    private $totalM2 = [
        0,

    ];

    private $salesTexplan = [
        '7.25',

    ];

    private $salesTaxAmount = [
        0,
    ];

    private $amount = [
        '0.00',
    ];

    private $grandTotal = [
        '0.00',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->plan)) as $index) {

            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            Subscriptions::create([
                'plan_type_id' => $this->plan[$index - 1],
                'plan_id' => $this->planId[$index - 1],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $this->status[$index - 1],
                'paid_status' => $this->paidStatus[$index - 1],
                'total_pid' => $this->totalPid[$index - 1],
                'total_m2' => $this->totalM2[$index - 1],
                'sales_tax_per' => $this->salesTexplan[$index - 1],
                'sales_tax_amount' => $this->salesTaxAmount[$index - 1],
                'amount' => $this->amount[$index - 1],
                'grand_total' => $this->grandTotal[$index - 1],
            ]);

        }
    }
}
