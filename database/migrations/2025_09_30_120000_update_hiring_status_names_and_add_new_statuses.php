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
        // Update existing status names and properties based on current hiring_status table
        $statusUpdates = [
            // ID 4: "Offer Sent, Unread"
            4 => [
                'status' => 'Offer Sent, Unread',
                'display_order' => 2,
                'hide_status' => 0,
                'colour_code' => '#e4e8ff',
                'show_on_card' => 1,
                'updated_at' => now(),
            ],
            // ID 12: "Offer Resent, Unread"
            12 => [
                'status' => 'Offer Resent, Unread',
                'display_order' => 0,
                'hide_status' => 0,
                'colour_code' => '#E4E9FF',
                'show_on_card' => 0,
                'updated_at' => now(),
            ],
            // ID 21: "Offer Sent, Read"
            21 => [
                'status' => 'Offer Sent, Read',
                'display_order' => 11,
                'hide_status' => 0,
                'colour_code' => '#fbe7e5',
                'show_on_card' => 1,
                'updated_at' => now(),
            ],
            // ID 24: "Offer Resent, Read"
            24 => [
                'status' => 'Offer Resent, Read',
                'display_order' => 18,
                'hide_status' => 0,
                'colour_code' => '#FFF8DD',
                'show_on_card' => 1,
                'updated_at' => now(),
            ],
        ];

        // Apply status updates
        foreach ($statusUpdates as $statusId => $updateData) {
            $existingStatus = DB::table('hiring_status')->where('id', $statusId)->first();

            if ($existingStatus) {
                DB::table('hiring_status')
                    ->where('id', $statusId)
                    ->update($updateData);
            } else {
                // If status doesn't exist, create it with the specified ID
                DB::table('hiring_status')->insert(array_merge(['id' => $statusId], $updateData, [
                    'created_at' => now(),
                ]));
            }
        }

        // Verify the updates were applied correctly
        $this->verifyStatusUpdates($statusUpdates);
    }

    /**
     * Verify that status updates were applied correctly
     */
    private function verifyStatusUpdates(array $expectedUpdates): void
    {
        $verificationErrors = [];

        foreach ($expectedUpdates as $statusId => $expectedData) {
            $actualStatus = DB::table('hiring_status')->where('id', $statusId)->first();

            if (! $actualStatus) {
                $verificationErrors[] = "Status ID {$statusId} not found after update";

                continue;
            }

            // Verify the status name was updated
            if ($actualStatus->status !== $expectedData['status']) {
                $verificationErrors[] = "Status ID {$statusId}: Expected status '{$expectedData['status']}', got '{$actualStatus->status}'";
            }
        }

        if (! empty($verificationErrors)) {
            throw new Exception("Status update verification failed:\n".implode("\n", $verificationErrors));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This migration updates existing statuses to match the current table structure.
        // Rolling back would require knowing the previous values, which may vary by environment.
        // For safety, we'll log a warning instead of making destructive changes.

        \Log::warning('Rolling back hiring status migration. Manual verification may be required.', [
            'migration' => '2025_09_30_120000_update_hiring_status_names_and_add_new_statuses',
            'affected_status_ids' => [4, 12, 21, 24],
            'note' => 'This migration updates existing status records to match current table structure',
        ]);

        // If you need to revert specific changes, uncomment and modify as needed:
        /*
        $revertUpdates = [
            4 => ['status' => 'Previous Status Name', 'updated_at' => now()],
            12 => ['status' => 'Previous Status Name', 'updated_at' => now()],
            21 => ['status' => 'Previous Status Name', 'updated_at' => now()],
            24 => ['status' => 'Previous Status Name', 'updated_at' => now()],
        ];

        foreach ($revertUpdates as $statusId => $revertData) {
            DB::table('hiring_status')
                ->where('id', $statusId)
                ->update($revertData);
        }
        */
    }
};
