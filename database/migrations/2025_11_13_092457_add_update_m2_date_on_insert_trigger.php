<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds the update_m2_date_on_insert trigger that was in the CSV but missing from schema.
     * This trigger only updates sale_masters (not legacy_api_data_null).
     * Note: update_m2_date_on_insert_update also exists and updates both tables.
     */
    public function up(): void
    {
        $triggerExists = DB::select("
            SELECT TRIGGER_NAME 
            FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = DATABASE() 
            AND TRIGGER_NAME = 'update_m2_date_on_insert'
        ");
        
        if (empty($triggerExists)) {
            DB::unprepared('
                CREATE TRIGGER update_m2_date_on_insert
                AFTER INSERT ON sale_product_master
                FOR EACH ROW
                BEGIN
                    IF NEW.is_last_date = 1 THEN
                        UPDATE sale_masters
                        SET m2_date = NEW.milestone_date
                        WHERE pid = NEW.pid;
                    END IF;
                END
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_m2_date_on_insert');
    }
};
