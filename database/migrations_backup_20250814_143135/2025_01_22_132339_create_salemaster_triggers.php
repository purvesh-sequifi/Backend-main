<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // fetching first information regarding which table is associated with which trigger for our current database.
        $dbName = DB::select('SELECT DATABASE() as dbName');
        $dbName = $dbName[0]->dbName;
        $allTriggers = DB::select('show triggers from '.$dbName);
        $uniqueTriggerNames = [];
        if (! empty($allTriggers)) {
            foreach ($allTriggers as $snglTrigger) {
                $uniqueTriggerNames[] = $snglTrigger->Trigger.'-'.$snglTrigger->Table;
            }
        }

        if (! in_array('before_insert_sale_master_process-sale_master_process', $uniqueTriggerNames)) {
            DB::unprepared('
            CREATE TRIGGER before_insert_sale_master_process  BEFORE INSERT ON `sale_master_process`
                FOR EACH ROW BEGIN
                    UPDATE `sale_masters` SET closer1_name = ( SELECT CONCAT(first_name, " " , last_name) FROM `users` WHERE id = NEW.closer1_id LIMIT 1 ), closer1_id = NEW.closer1_id WHERE sale_masters.id = NEW.sale_master_id;
                    UPDATE `sale_masters` SET closer2_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.closer2_id LIMIT 1 ), closer2_id = NEW.closer2_id WHERE sale_masters.id = NEW.sale_master_id;
                    UPDATE `sale_masters` SET setter1_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.setter1_id LIMIT 1
                    ), setter1_id = NEW.setter1_id WHERE sale_masters.id = NEW.sale_master_id;
                    -- Insert or update setter2_name and setter2_id
                    UPDATE `sale_masters` SET setter2_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.setter2_id LIMIT 1 ), setter2_id = NEW.setter2_id WHERE sale_masters.id = NEW.sale_master_id;
                END
            ');
        }

        if (! in_array('before_update_sale_master_process-sale_master_process', $uniqueTriggerNames)) {
            DB::unprepared('
            CREATE TRIGGER before_update_sale_master_process BEFORE UPDATE ON `sale_master_process`
            FOR EACH ROW BEGIN
                UPDATE `sale_masters` SET closer1_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.closer1_id LIMIT 1 ), closer1_id = NEW.closer1_id WHERE sale_masters.id = NEW.sale_master_id;
                UPDATE `sale_masters` SET closer2_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.closer2_id LIMIT 1 ), closer2_id = NEW.closer2_id WHERE sale_masters.id = NEW.sale_master_id;
                UPDATE `sale_masters` SET setter1_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.setter1_id LIMIT 1 ), setter1_id = NEW.setter1_id WHERE sale_masters.id = NEW.sale_master_id;
                UPDATE `sale_masters` SET setter2_name = ( SELECT CONCAT(first_name, " ", last_name) FROM `users` WHERE id = NEW.setter2_id LIMIT 1 ), setter2_id = NEW.setter2_id WHERE sale_masters.id = NEW.sale_master_id;
                END
            ');
        }

        if (! in_array('update_sale_product_master_after_user_commission_update-user_commission', $uniqueTriggerNames)) {
            DB::unprepared('
            CREATE TRIGGER update_sale_product_master_after_user_commission_update AFTER UPDATE ON `user_commission`
            FOR EACH ROW BEGIN
                IF OLD.status = 1 AND NEW.status = 3 THEN
                    UPDATE `sale_product_master` SET is_paid = 1 WHERE sale_product_master.type = NEW.schema_type AND (setter1_id = NEW.user_id OR setter2_id = NEW.user_id OR closer1_id = NEW.user_id OR closer2_id = NEW.user_id);
                END IF;
                END
            ');
        }

        // table not found
        if (! in_array('update_name_after_milestone_schema_trigger_update-milestone_schema_trigger', $uniqueTriggerNames)) {
            DB::unprepared('
            CREATE TRIGGER update_name_after_milestone_schema_trigger_update AFTER UPDATE ON `milestone_schema_trigger`
            FOR EACH ROW BEGIN
                IF OLD.name != NEW.name OR OLD.on_trigger != NEW.on_trigger THEN
                    UPDATE `user_commission` SET schema_name = NEW.name, schema_trigger = NEW.on_trigger WHERE user_commission.milestone_schema_id = NEW.id;
                    UPDATE `user_commission_lock` SET schema_name = NEW.name, schema_trigger = NEW.on_trigger WHERE user_commission_lock.milestone_schema_id = NEW.id;
                    UPDATE `clawback_settlements` SET schema_name = NEW.name, schema_trigger = NEW.on_trigger WHERE clawback_settlements.milestone_schema_id = NEW.id;
                    UPDATE `clawback_settlements_lock` SET schema_name = NEW.name, schema_trigger = NEW.on_trigger WHERE clawback_settlements_lock.milestone_schema_id = NEW.id;
                    UPDATE `projection_user_commissions` SET schema_name = NEW.name, schema_trigger = NEW.on_trigger WHERE projection_user_commissions.milestone_schema_id = NEW.id;
                END IF;
                END
            ');
        }

        if (! in_array('update_sale_invoice_on_kw_update-sale_masters', $uniqueTriggerNames)) {
            DB::unprepared('
            CREATE TRIGGER update_sale_invoice_on_kw_update AFTER UPDATE ON `sale_masters`
            FOR EACH ROW BEGIN
                IF NEW.kw != OLD.kw THEN
                CREATE TEMPORARY TABLE `temp_sale_invoice` AS SELECT id as invoice_id, pid FROM sales_invoice_details WHERE pid = NEW.pid ORDER BY id DESC LIMIT 1;
                UPDATE `sales_invoice_details` SET updated_kw = NEW.kw, updated_kw_date = now() WHERE id = (SELECT invoice_id FROM temp_sale_invoice );
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
        Schema::dropIfExists('salemaster_triggers');
    }
};
