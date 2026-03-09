<?php

namespace Database\Seeders;

use App\Models\SClearanceTurnStatus;
use Illuminate\Database\Seeder;

class SClearanceTurnStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = $this->data();

        foreach ($data as $value) {
            SClearanceTurnStatus::create([
                'status_code' => $value['status_code'],
                'status_name' => $value['status_name'],
            ]);
        }
    }

    public function data()
    {
        return [
            ['status_code' => 'emailed', 'status_name' => 'Emailed'],
            ['status_code' => 'initiated', 'status_name' => 'Initiated'],
            ['status_code' => 'consent', 'status_name' => 'Consent'],
            ['status_code' => 'review__identity', 'status_name' => 'Verifying'],
            ['status_code' => 'processing', 'status_name' => 'Processing'],
            ['status_code' => 'review', 'status_name' => 'Reviewing'],
            ['status_code' => 'pending', 'status_name' => 'Consider'],
            ['status_code' => 'pending__first_notice', 'status_name' => 'First Notice'],
            ['status_code' => 'pending__second_notice', 'status_name' => 'Second Notice'],
            ['status_code' => 'rejected', 'status_name' => 'Rejected'],
            ['status_code' => 'withdrawn', 'status_name' => 'Withdrawn'],
            ['status_code' => 'approved', 'status_name' => 'Approved'],
            ['status_code' => 'approved_by_admin', 'status_name' => 'Approved By admin'],
        ];
    }
}
