<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserEvereeMigrationSeeder extends Seeder
{
    /**
     * Update users everee_workerId for incomplete onboarding.
     * Data from migration 2025_09_18_054048_update_users_everee_worker_id_for_incomplete_onboarding.php
     * 
     * This seeder is idempotent - checks if migration already done.
     */
    public function run(): void
    {
        // Check if migration already completed
        if ($this->isMigrationCompleted()) {
            Log::info('User Everee migration already completed, skipping');
            return;
        }

        Log::info('Starting user Everee data migration');

        // Update users to set everee_workerId to null
        // where onboardProcess = 0 and everee_workerId is not null
        $affected = DB::table('users')
            ->where('onboardProcess', 0)
            ->whereNotNull('everee_workerId')
            ->update(['everee_workerId' => null]);

        Log::info("Updated {$affected} users with incomplete onboarding");

        // Mark migration as completed
        $this->markMigrationCompleted();
    }

    private function isMigrationCompleted(): bool
    {
        return DB::table('system_settings')
            ->where('key', 'user_everee_migrated')
            ->where('value', '1')
            ->exists();
    }

    private function markMigrationCompleted(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'user_everee_migrated'],
            [
                'value' => '1',
                'description' => 'User Everee worker ID migration completed',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}

