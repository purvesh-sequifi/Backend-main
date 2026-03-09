<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddSalesToCalculatedByEnumInPositionCommissionUpfronts extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE position_commission_upfronts MODIFY COLUMN calculated_by ENUM('per sale', 'per kw', 'percent') NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE position_commission_upfronts MODIFY COLUMN calculated_by ENUM('per sale', 'percent') NOT NULL");
    }
}
