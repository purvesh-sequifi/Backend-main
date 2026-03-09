<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPerSaleToCommissionAmountTypeEnumInPositionCommissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Alter the ENUM column to add the 'per sale' value
        DB::statement("ALTER TABLE position_commissions MODIFY COLUMN commission_amount_type ENUM('percent', 'per kw', 'per sale') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Rollback by removing 'per sale' from the ENUM
        DB::statement("ALTER TABLE position_commissions MODIFY COLUMN commission_amount_type ENUM('percent', 'per kw') NOT NULL");
    }
}
