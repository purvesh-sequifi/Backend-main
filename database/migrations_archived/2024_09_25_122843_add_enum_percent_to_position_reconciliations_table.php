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
        DB::statement("ALTER TABLE position_reconciliations MODIFY COLUMN commission_type ENUM('per kw', 'per sale', 'percent')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE position_reconciliations MODIFY COLUMN commission_type ENUM('per kw', 'per sale')");
    }
};
