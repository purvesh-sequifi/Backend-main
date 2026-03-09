<?php

namespace Database\Seeders;

use App\Models\HiringStatus;
use Illuminate\Database\Seeder;

class CreateStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private $names = [
        'Accepted',
        'Declined',
        'Approve',
        'Offer Letter Sent',
        'Offer Expired',
        'Requested Change',
        'Hired',
        'Draft',
        'FollowUp',
        'Not Interested',
        'Rejected',
        'Offer Letter Resent',
    ];

    public function run(): void
    {
        // foreach(range(1, sizeOf($this->names)) as $index)
        // {
        //     HiringStatus::create([
        //         'status' => $this->names[$index-1]
        //     ]);

        // }

    }
}
