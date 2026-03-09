<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('user_upfront_history', function (Blueprint $table) {
            DB::statement("ALTER TABLE `user_upfront_history` MODIFY `upfront_sale_type` ENUM('percent', 'per kw', 'per sale')");
            DB::statement("ALTER TABLE `user_upfront_history` MODIFY `old_upfront_sale_type` ENUM('percent', 'per kw', 'per sale')");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_upfront_history', function (Blueprint $table) {
            //
        });
    }
};
