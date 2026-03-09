<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Removes update_m2_date_on_insert_update trigger and modifies 
     * update_m2_date_on_update to only update sale_masters table.
     * This fixes lock timeout issues on legacy_api_data_null table.
     */
    public function up(): void
    {
        try {
            // Drop the redundant insert trigger that updates both tables
            DB::unprepared('DROP TRIGGER IF EXISTS update_m2_date_on_insert_update');
            
            // Drop and recreate the update trigger to only update sale_masters
            DB::unprepared('DROP TRIGGER IF EXISTS update_m2_date_on_update');
            
            DB::unprepared('
                CREATE TRIGGER update_m2_date_on_update
                AFTER UPDATE ON sale_product_master
                FOR EACH ROW
                BEGIN
                    IF NEW.is_last_date = 1 THEN
                        UPDATE sale_masters
                        SET m2_date = NEW.milestone_date
                        WHERE pid = NEW.pid;
                    END IF;
                END
            ');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to modify triggers: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Reverse the migrations.
     * 
     * NOTE: This migration fixes critical production issues (duplicate triggers and lock timeouts).
     * Rolling back would restore the problematic triggers that caused:
     * - Duplicate updates to sale_masters table
     * - Lock timeouts on legacy_api_data_null table
     * 
     * Therefore, rollback is NOT supported for this migration.
     * If rollback is absolutely necessary, it must be done manually with proper testing.
     */
    public function down(): void
    {
        // This migration fixes critical production issues and cannot be safely rolled back.
        // Rolling back would restore problematic triggers that caused duplicate updates and lock timeouts.
    }
};
