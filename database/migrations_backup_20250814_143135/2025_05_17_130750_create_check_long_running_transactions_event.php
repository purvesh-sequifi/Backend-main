<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateCheckLongRunningTransactionsEvent extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Skip if we don't have the required privileges
        if (! DB::select("SELECT 1 FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = CONCAT('\'', CURRENT_USER(), '\'') AND PRIVILEGE_TYPE = 'EVENT'")) {
            // Log that we're skipping the event creation
            if (Schema::hasTable('migrations')) {
                DB::table('migrations')->insert([
                    'migration' => '2025_05_17_130750_create_check_long_running_transactions_event',
                    'batch' => 1,
                ]);
            }

            return;
        }

        // Create the event with minimal privileges required
        $event = "
        CREATE EVENT IF NOT EXISTS check_long_running_transactions
        ON SCHEDULE EVERY 1 MINUTE
        COMMENT 'Checks for and kills transactions running longer than 1 hour'
        DO
        BEGIN
            -- Simple call to the procedure which handles the actual work
            CALL kill_long_running_transactions(3600);
        END;";

        try {
            // Try to create the event without requiring global privileges
            DB::unprepared('DROP EVENT IF EXISTS check_long_running_transactions');
            DB::unprepared($event);

            // Log successful creation
            if (Schema::hasTable('migrations')) {
                DB::table('migrations')->insert([
                    'migration' => '2025_05_17_130750_create_check_long_running_transactions_event',
                    'batch' => 1,
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but don't fail the migration
            if (Schema::hasTable('migrations')) {
                DB::table('migrations')->insert([
                    'migration' => '2025_05_17_130750_create_check_long_running_transactions_event',
                    'batch' => 1,
                ]);
            }

            // Log to error log if we can't write to database
            error_log('Failed to create check_long_running_transactions event: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP EVENT IF EXISTS check_long_running_transactions');
    }
}
