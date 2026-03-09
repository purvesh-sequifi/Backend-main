<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HiringStatusCompleteSeeder extends Seeder
{
    /**
     * Seed complete hiring status data.
     * This seeder contains all 24 hiring statuses with complete configuration.
     * It's idempotent and can be run multiple times safely.
     */
    public function run(): void
    {
        $hiringStatuses = [
            [
                'id' => 1,
                'status' => 'Accepted',
                'display_order' => 0,
                'hide_status' => 1,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 2,
                'status' => 'Declined',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 3,
                'status' => 'Approve',
                'display_order' => 0,
                'hide_status' => 1,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 4,
                'status' => 'Offer Sent, Unread',
                'display_order' => 2,
                'hide_status' => 1,
                'colour_code' => '#e4e8ff',
                'show_on_card' => 1,
            ],
            [
                'id' => 5,
                'status' => 'Offer Expired',
                'display_order' => 7,
                'hide_status' => 0,
                'colour_code' => '#fff8dd',
                'show_on_card' => 1,
            ],
            [
                'id' => 6,
                'status' => 'Requested Change',
                'display_order' => 3,
                'hide_status' => 0,
                'colour_code' => '#e3f3fc',
                'show_on_card' => 1,
            ],
            [
                'id' => 7,
                'status' => 'Onboarding',
                'display_order' => 6,
                'hide_status' => 0,
                'colour_code' => '#ecedef',
                'show_on_card' => 1,
            ],
            [
                'id' => 8,
                'status' => 'Draft',
                'display_order' => 1,
                'hide_status' => 0,
                'colour_code' => '#f9fafc',
                'show_on_card' => 1,
            ],
            [
                'id' => 9,
                'status' => 'FollowUp',
                'display_order' => 0,
                'hide_status' => 1,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 10,
                'status' => 'Not Interested',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 11,
                'status' => 'Rejected',
                'display_order' => 8,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
            ],
            [
                'id' => 12,
                'status' => 'Offer Resent, Unread',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 13,
                'status' => 'Offer Letter Accepted',
                'display_order' => 4,
                'hide_status' => 1,
                'colour_code' => '#eef8e7',
                'show_on_card' => 1,
            ],
            [
                'id' => 14,
                'status' => 'Active',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
            ],
            [
                'id' => 15,
                'status' => 'Admin Reject',
                'display_order' => 9,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
            ],
            [
                'id' => 16,
                'status' => 'Document Review',
                'display_order' => 5,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 1,
            ],
            [
                'id' => 17,
                'status' => 'Offer Review',
                'display_order' => 1,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 1,
            ],
            [
                'id' => 18,
                'status' => 'Special Review',
                'display_order' => 1,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 1,
            ],
            [
                'id' => 19,
                'status' => 'Manager Rejected',
                'display_order' => 10,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
            ],
            [
                'id' => 20,
                'status' => 'Conditions Rejected',
                'display_order' => 19,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
            ],
            [
                'id' => 21,
                'status' => 'Offer Sent, Read',
                'display_order' => 11,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
            ],
            [
                'id' => 22,
                'status' => 'Offer Accepted, Docs Pending',
                'display_order' => 16,
                'hide_status' => 0,
                'colour_code' => '#EEF8E7',
                'show_on_card' => 1,
            ],
            [
                'id' => 23,
                'status' => 'Offer Pending, Docs Completed',
                'display_order' => 17,
                'hide_status' => 0,
                'colour_code' => '#E4E8FF',
                'show_on_card' => 1,
            ],
            [
                'id' => 24,
                'status' => 'Offer Resent, Read',
                'display_order' => 18,
                'hide_status' => 0,
                'colour_code' => '#FFF8DD',
                'show_on_card' => 1,
            ],
            [
                'id' => 25,
                'status' => 'Offer Letter Sent, Pending',
                'display_order' => 18,
                'hide_status' => 0,
                'colour_code' => '#FFF8DD',
                'show_on_card' => 1,
            ],
            [
                'id' => 26,
                'status' => 'Worklio Onboarding',
                'display_order' => 6,
                'hide_status' => 0,
                'colour_code' => '#E4E9FFF',
                'show_on_card' => 1,
            ],
        ];




        foreach ($hiringStatuses as $statusData) {
            // Use updateOrCreate to avoid duplicates and update existing records
            DB::table('hiring_status')->updateOrInsert(
                ['id' => $statusData['id']], // Match by ID
                array_merge($statusData, [
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'), // Keep existing created_at if present
                    'updated_at' => now(),
                ])
            );
        }
    }
}

