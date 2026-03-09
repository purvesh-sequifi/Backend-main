<?php

namespace Database\Seeders;

use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

class LeadStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = $this->data();

        foreach ($data as $value) {
            LeadStatus::create([
                'status_name' => $value['status_name'],
                'sequence' => $value['sequence'],
                'hide_show_status' => $value['hide_show_status'],
            ]);
        }
    }

    public function data()
    {
        return [
            ['status_name' => 'Follow Up', 'sequence' => 1, 'hide_show_status' => 1],
            ['status_name' => 'Hired', 'sequence' => 2, 'hide_show_status' => 1],
            ['status_name' => 'Rejected', 'sequence' => 3, 'hide_show_status' => 1],
        ];
    }
}
