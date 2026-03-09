<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fix the trigger definer for 'update_m2_date_on_insert' trigger on sale_product_master table.
     * The trigger was created with 'admin_multitenant@%' as definer, but that user doesn't exist.
     * This migration recreates the trigger with the correct definer matching the current DB user.
     */
    public function up(): void
    {
        // Get the current database username from config
        $dbUsername = config('database.connections.mysql.username', 'admin');
        
        // Drop the problematic trigger
        DB::unprepared('DROP TRIGGER IF EXISTS `update_m2_date_on_insert`');
        
        // Recreate the trigger with correct definer
        DB::unprepared("
            CREATE DEFINER = `{$dbUsername}`@`%` TRIGGER update_m2_date_on_insert
            AFTER INSERT ON sale_product_master
            FOR EACH ROW
            BEGIN
                IF NEW.is_last_date = 1 THEN
                    UPDATE sale_masters
                    SET m2_date = NEW.milestone_date
                    WHERE pid = NEW.pid;
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     * 
     * Restore the original trigger with admin_multitenant definer.
     * Note: This will restore the broken state, but allows proper rollback.
     */
    public function down(): void
    {
        // Drop the current trigger
        DB::unprepared('DROP TRIGGER IF EXISTS `update_m2_date_on_insert`');
        
        // Recreate with original definer (admin_multitenant)
        // Note: This will put the database back in the broken state
        DB::unprepared("
            CREATE DEFINER = `admin_multitenant`@`%` TRIGGER update_m2_date_on_insert
            AFTER INSERT ON sale_product_master
            FOR EACH ROW
            BEGIN
                IF NEW.is_last_date = 1 THEN
                    UPDATE sale_masters
                    SET m2_date = NEW.milestone_date
                    WHERE pid = NEW.pid;
                END IF;
            END
        ");
    }
};
