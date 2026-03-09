<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use UPSERT approach - insert or update existing statuses to standardize across servers
        // This preserves any additional columns that might be added by future migrations
        $standardizedStatuses = [
            // ID 1: Accepted
            [
                'status' => 'Accepted',
                'display_order' => 0,
                'hide_status' => 1,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 2: Declined
            [
                'status' => 'Declined',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 3: Approve
            [
                'status' => 'Approve',
                'display_order' => 0,
                'hide_status' => 1,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 4: Offer Letter Sent
            [
                'status' => 'Offer Letter Sent',
                'display_order' => 2,
                'hide_status' => 0,
                'colour_code' => '#e4e8ff',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 5: Offer Expired
            [
                'status' => 'Offer Expired',
                'display_order' => 7,
                'hide_status' => 0,
                'colour_code' => '#fff8dd',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 6: Requested Change
            [
                'status' => 'Requested Change',
                'display_order' => 3,
                'hide_status' => 0,
                'colour_code' => '#e3f3fc',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 7: Onboarding
            [
                'status' => 'Onboarding',
                'display_order' => 6,
                'hide_status' => 0,
                'colour_code' => '#ecedef',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 8: Draft
            [
                'status' => 'Draft',
                'display_order' => 1,
                'hide_status' => 0,
                'colour_code' => '#f9fafc',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 9: FollowUp
            [
                'status' => 'FollowUp',
                'display_order' => 0,
                'hide_status' => 1,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 10: Not Interested
            [
                'status' => 'Not Interested',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 11: Rejected
            [
                'status' => 'Rejected',
                'display_order' => 8,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 12: Offer Letter Resent
            [
                'status' => 'Offer Letter Resent',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 13: Offer Letter Accepted
            [
                'status' => 'Offer Letter Accepted',
                'display_order' => 4,
                'hide_status' => 0,
                'colour_code' => '#eef8e7',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 14: Active
            [
                'status' => 'Active',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 15: Admin Reject
            [
                'status' => 'Admin Reject',
                'display_order' => 9,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Additional statuses found on some servers (will get next available IDs)
            // ID 16: Document Review
            [
                'status' => 'Document Review',
                'display_order' => 5,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 17: Offer Review
            [
                'status' => 'Offer Review',
                'display_order' => 1,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 18: Special Review
            [
                'status' => 'Special Review',
                'display_order' => 1,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 19: Manager Rejected
            [
                'status' => 'Manager Rejected',
                'display_order' => 10,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 20: Conditions Rejected
            [
                'status' => 'Conditions Rejected',
                'display_order' => 19,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 21: Offer Sent, Read
            [
                'status' => 'Offer Sent, Read',
                'display_order' => 11,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 22: Offer Accepted, Docs Pending
            [
                'status' => 'Offer Accepted, Docs Pending',
                'display_order' => 16,
                'hide_status' => 0,
                'colour_code' => '#EEF8E7',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 23: Offer Pending, Docs Completed
            [
                'status' => 'Offer Pending, Docs Completed',
                'display_order' => 17,
                'hide_status' => 0,
                'colour_code' => '#E4E8FF',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // ID 24: Offer Sent, Unread
            [
                'status' => 'Offer Sent, Unread',
                'display_order' => 18,
                'hide_status' => 0,
                'colour_code' => '#FFF8DD',
                'show_on_card' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Use UPSERT approach to insert or update statuses
        foreach ($standardizedStatuses as $index => $statusData) {
            $id = $index + 1; // IDs start from 1

            // Check if status exists
            $existingStatus = DB::table('hiring_status')->where('id', $id)->first();

            if ($existingStatus) {
                // Update existing status while preserving any additional columns
                DB::table('hiring_status')
                    ->where('id', $id)
                    ->update([
                        'status' => $statusData['status'],
                        'display_order' => $statusData['display_order'],
                        'hide_status' => $statusData['hide_status'],
                        'colour_code' => $statusData['colour_code'],
                        'show_on_card' => $statusData['show_on_card'],
                        'updated_at' => now(),
                        // Note: We don't update created_at to preserve original timestamp
                        // Note: Any additional columns added by future migrations are preserved
                    ]);
            } else {
                // Insert new status with explicit ID
                DB::table('hiring_status')->insert(array_merge(['id' => $id], $statusData));
            }
        }

        // Verify all expected statuses exist with their specific IDs
        $expectedStatusIds = range(1, count($standardizedStatuses)); // IDs 1-24
        $existingStatusIds = DB::table('hiring_status')
            ->whereIn('id', $expectedStatusIds)
            ->pluck('id')
            ->toArray();

        $missingStatusIds = array_diff($expectedStatusIds, $existingStatusIds);

        if (! empty($missingStatusIds)) {
            throw new Exception('Migration verification failed: The following status IDs are missing: '.implode(', ', $missingStatusIds).'. Expected all status IDs from 1 to '.count($standardizedStatuses).' to exist.');
        }

        // Verify that each status has the correct data
        $statusVerificationErrors = [];
        foreach ($standardizedStatuses as $index => $expectedStatus) {
            $id = $index + 1;
            $actualStatus = DB::table('hiring_status')->where('id', $id)->first();

            if (! $actualStatus) {
                $statusVerificationErrors[] = "Status ID $id is missing";

                continue;
            }

            // Check critical fields
            if ($actualStatus->status !== $expectedStatus['status']) {
                $statusVerificationErrors[] = "Status ID $id: Expected status '{$expectedStatus['status']}', got '{$actualStatus->status}'";
            }
            if ($actualStatus->display_order != $expectedStatus['display_order']) {
                $statusVerificationErrors[] = "Status ID $id: Expected display_order {$expectedStatus['display_order']}, got {$actualStatus->display_order}";
            }
            if ($actualStatus->hide_status != $expectedStatus['hide_status']) {
                $statusVerificationErrors[] = "Status ID $id: Expected hide_status {$expectedStatus['hide_status']}, got {$actualStatus->hide_status}";
            }
            if (strtolower($actualStatus->colour_code) !== strtolower($expectedStatus['colour_code'])) {
                $statusVerificationErrors[] = "Status ID $id: Expected colour_code '{$expectedStatus['colour_code']}', got '{$actualStatus->colour_code}'";
            }
            if ($actualStatus->show_on_card != $expectedStatus['show_on_card']) {
                $statusVerificationErrors[] = "Status ID $id: Expected show_on_card {$expectedStatus['show_on_card']}, got {$actualStatus->show_on_card}";
            }
        }

        if (! empty($statusVerificationErrors)) {
            throw new Exception("Migration verification failed with the following errors:\n".implode("\n", $statusVerificationErrors));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be easily reversed as it truncates the table
        // To reverse, you would need to restore from backup or re-run previous seeders
        throw new Exception('This migration cannot be reversed as it truncates the hiring_status table. Please restore from backup if needed.');
    }
};
