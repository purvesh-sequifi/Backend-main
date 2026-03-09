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
        DB::statement("ALTER TABLE user_withheld_history MODIFY COLUMN withheld_type ENUM('per sale', 'per kw', 'percent')");
        DB::statement("ALTER TABLE user_withheld_history MODIFY COLUMN old_withheld_type ENUM('per sale', 'per kw', 'percent')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE user_withheld_history MODIFY COLUMN withheld_type ENUM('per sale', 'per kw')");
        DB::statement("ALTER TABLE user_withheld_history MODIFY COLUMN old_withheld_type ENUM('per sale', 'per kw')");
    }
};
