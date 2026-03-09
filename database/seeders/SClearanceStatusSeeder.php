<?php

namespace Database\Seeders;

use App\Models\SClearanceStatus;
use Illuminate\Database\Seeder;

class SClearanceStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = $this->data();

        foreach ($data as $value) {
            SClearanceStatus::create([
                'status_name' => $value['status_name'],
            ]);
        }
    }

    public function data()
    {
        return [
            ['status_name' => 'Mail Sent'],
            ['status_name' => 'Pending Verification'],
            ['status_name' => 'In Progress'],
            ['status_name' => 'Verification SuccessFul'],
            ['status_name' => 'Verification Failed'],
            ['status_name' => 'Approval Pending'],
            ['status_name' => 'Canceled'],
            ['status_name' => 'Approved'],
            ['status_name' => 'Declined'],
        ];
    }
}
