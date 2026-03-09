<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update silvergrey.in domain to sequifi.com in domain_settings table
     * Only updates if silvergrey.in exists
     *
     * @return void
     */
    public function up(): void
    {
        try {
            // Check if silvergrey.in exists in domain_settings table
            $silvergreyDomain = DB::table('domain_settings')
                ->where('domain_name', 'silvergrey.in')
                ->first();

            if ($silvergreyDomain) {
                // Log the update for audit purposes
                Log::info('Migration: Found silvergrey.in domain, updating to sequifi.com', [
                    'old_domain' => 'silvergrey.in',
                    'new_domain' => 'sequifi.com',
                    'domain_id' => $silvergreyDomain->id,
                ]);

                // Update silvergrey.in to sequifi.com
                DB::table('domain_settings')
                    ->where('domain_name', 'silvergrey.in')
                    ->update([
                        'domain_name' => 'sequifi.com',
                        'updated_at' => now(),
                    ]);

                Log::info('Migration: Successfully updated silvergrey.in to sequifi.com');
            } else {
                Log::info('Migration: silvergrey.in domain not found in domain_settings table, skipping update');
            }
        } catch (\Exception $e) {
            Log::error('Migration: Failed to update silvergrey.in domain', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     *
     * Rollback sequifi.com to silvergrey.in if needed
     *
     * @return void
     */
    public function down(): void
    {
        try {
            // Check if sequifi.com exists (in case we need to rollback)
            $sequifiDomain = DB::table('domain_settings')
                ->where('domain_name', 'sequifi.com')
                ->first();

            if ($sequifiDomain) {
                Log::info('Migration Rollback: Found sequifi.com domain, reverting to silvergrey.in', [
                    'old_domain' => 'sequifi.com',
                    'new_domain' => 'silvergrey.in',
                    'domain_id' => $sequifiDomain->id,
                ]);

                // Rollback to silvergrey.in
                DB::table('domain_settings')
                    ->where('domain_name', 'sequifi.com')
                    ->update([
                        'domain_name' => 'silvergrey.in',
                        'updated_at' => now(),
                    ]);

                Log::info('Migration Rollback: Successfully reverted sequifi.com to silvergrey.in');
            } else {
                Log::info('Migration Rollback: sequifi.com domain not found, skipping rollback');
            }
        } catch (\Exception $e) {
            Log::error('Migration Rollback: Failed to revert domain', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
};
