<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HiringStatusDataSeeder extends Seeder
{
    /**
     * Seed hiring status data that was previously in migrations.
     * This seeder is idempotent and can be run multiple times.
     */
    public function run(): void
    {
        // Data from 2025_09_15_073521_add_three_new_hiring_statuses_to_hiring_status_table.php
        $newStatuses = [
            [
                'status' => 'Offer Letter Accepted, Rest Pending',
                'display_order' => 16,
                'hide_status' => 0,
                'colour_code' => '#EEF8E7',
                'show_on_card' => 1,
            ],
            [
                'status' => 'Offer Letter Pending, Rest Completed',
                'display_order' => 17,
                'hide_status' => 0,
                'colour_code' => '#E4E8FF',
                'show_on_card' => 1,
            ],
            [
                'status' => 'Offer Letter Sent, Pending',
                'display_order' => 18,
                'hide_status' => 0,
                'colour_code' => '#FFF8DD',
                'show_on_card' => 1,
            ],
        ];

        foreach ($newStatuses as $statusData) {
            // Check if status already exists to avoid duplicates
            $existingStatus = DB::table('hiring_status')
                ->where('status', $statusData['status'])
                ->first();

            if (! $existingStatus) {
                DB::table('hiring_status')->insert(array_merge($statusData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}

