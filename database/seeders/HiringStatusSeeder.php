<?php

namespace Database\Seeders;

use App\Models\HiringStatus;
use Illuminate\Database\Seeder;

class HiringStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $type =
        [
            'Accepted',
            'Declined',
            'Approve',
            'Offer Letter Sent',
            'Offer Expired',
            'Requested Change',
            'Onboarding',
            'Draft',
            'FollowUp',
            'Not Interested',
            'Rejected',
            'Offer Letter Resent',
            'Offer Letter Accepted',
            'Active',
            'Admin Reject',
        ];

    public function run(): void
    {
        foreach (range(1, count($this->type)) as $index) {
            HiringStatus::create([
                'status' => $this->type[$index - 1],
            ]);

        }
    }
}
