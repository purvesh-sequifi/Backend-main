<?php

namespace Database\Seeders;

use App\Models\HiringStatus;
use Illuminate\Database\Seeder;

class HiringStatusSetDisplayOrderAndColorCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $checkHireNow = HiringStatus::where('status', 'Hire Now')->first();
        if ($checkHireNow == null) {
            HiringStatus::create([
                'status' => 'Hire Now',
            ]);
        }

        $hiringStatus = HiringStatus::get();

        foreach ($hiringStatus as $key => $hiring) {
            $hiring->show_on_card = 0;

            if ($hiring->status == 'Draft') {
                $hiring->display_order = 1;
                $hiring->colour_code = '#f9fafc';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Offer Letter Sent') {
                $hiring->display_order = 2;
                $hiring->colour_code = '#e4e8ff';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Requested Change') {
                $hiring->display_order = 3;
                $hiring->colour_code = '#e3f3fc';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Offer Letter Accepted') {
                $hiring->display_order = 4;
                $hiring->colour_code = '#eef8e7';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Hire Now') {
                $hiring->display_order = 5;
                $hiring->colour_code = '#eef8e7';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Onboarding') {
                $hiring->display_order = 6;
                $hiring->colour_code = '#ecedef';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Offer Expired') {
                $hiring->display_order = 7;
                $hiring->colour_code = '#fff8dd';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Rejected') {
                $hiring->display_order = 8;
                $hiring->colour_code = '#fbe7e5';
                $hiring->show_on_card = 1;
            } elseif ($hiring->status == 'Admin Reject') {
                $hiring->display_order = 9;
                $hiring->colour_code = '#fbe7e5';
                $hiring->show_on_card = 1;
            }

            $hiring->save();
        }
    }
}
