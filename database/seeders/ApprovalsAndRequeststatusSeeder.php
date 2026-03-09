<?php

namespace Database\Seeders;

use App\Models\ApprovalsAndRequeststatus;
use Illuminate\Database\Seeder;

class ApprovalsAndRequeststatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $name = [
        'Apporoved',
        'Pending',
        'Declined',
        'Reject',
    ];

    public function run(): void
    {
        foreach (range(1, count($this->name)) as $index) {
            ApprovalsAndRequeststatus::create([
                'name' => $this->name[$index - 1],
            ]);

        }

    }
}
