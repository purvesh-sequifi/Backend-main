<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::raw('UPDATE sale_masters sm
            JOIN sale_product_master spm ON sm.pid = spm.pid
            SET sm.m2_date = spm.milestone_date
            WHERE spm.is_last_date = 1');

        $triggerExists = DB::select("
            SELECT TRIGGER_NAME 
            FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = DATABASE() 
            AND TRIGGER_NAME = 'update_m2_date_on_insert_update'
        ");
        if (empty($triggerExists)) {
            DB::unprepared('
                CREATE TRIGGER update_m2_date_on_insert_update
                AFTER INSERT ON sale_product_master
                FOR EACH ROW
                BEGIN
        IF NEW.is_last_date = 1 THEN
            UPDATE sale_masters
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;

            UPDATE legacy_api_data_null
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;
        END IF;
    END
            ');
        }

        $triggerExists = DB::select("
            SELECT TRIGGER_NAME 
            FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = DATABASE() 
            AND TRIGGER_NAME = 'update_m2_date_on_update'
        ");
        if (empty($triggerExists)) {
            DB::unprepared('
                CREATE TRIGGER update_m2_date_on_update
                AFTER UPDATE ON sale_product_master
                FOR EACH ROW
                BEGIN
        IF NEW.is_last_date = 1 THEN
            UPDATE sale_masters
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;

            UPDATE legacy_api_data_null
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;
        END IF;
    END
            ');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
