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
        DB::statement("ALTER TABLE `position_tiers`
CHANGE `tiers_schema_id` `tiers_schema_id` bigint(20) NULL AFTER `position_id`,
CHANGE `tier_advancement` `tier_advancement` varchar(255) NULL AFTER `tiers_schema_id`,
CHANGE `type` `type` enum('commission','upfront','override') NULL AFTER `tier_advancement`,
CHANGE `status` `status` tinyint(4) NULL AFTER `type`;");
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
