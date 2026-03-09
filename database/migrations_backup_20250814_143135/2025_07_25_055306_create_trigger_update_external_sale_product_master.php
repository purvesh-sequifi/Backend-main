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
        DB::unprepared('
        CREATE TRIGGER update_external_sale_product_master_after_user_commission_update AFTER UPDATE ON `user_commission`
        FOR EACH ROW BEGIN
            IF OLD.status = 1 AND NEW.status = 3 THEN
                UPDATE `external_sale_product_master` SET is_paid = 1 WHERE external_sale_product_master.type = NEW.schema_type AND external_sale_product_master.pid = NEW.pid AND external_sale_product_master.worker_id = NEW.user_id AND NEW.worker_type = "external";
            END IF;
        END;
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared('DROP TRIGGER IF EXISTS update_external_sale_product_master_after_user_commission_update');

    }
};
