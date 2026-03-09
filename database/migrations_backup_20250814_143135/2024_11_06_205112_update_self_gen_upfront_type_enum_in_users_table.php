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
        DB::statement("ALTER TABLE `users` MODIFY `upfront_sale_type` ENUM('per sale', 'per kw', 'percent') NULL DEFAULT 'per sale';");
        DB::statement("ALTER TABLE `users` MODIFY `self_gen_upfront_type` ENUM('per sale', 'per kw', 'percent') NULL;");

        DB::statement("ALTER TABLE `onboarding_employees` MODIFY `upfront_sale_type` ENUM('per sale', 'per kw', 'percent') NULL DEFAULT 'per sale';");
        DB::statement("ALTER TABLE `onboarding_employees` MODIFY `self_gen_upfront_type` ENUM('per sale', 'per kw', 'percent') NULL;");

        DB::statement("ALTER TABLE `user_upfront_history` MODIFY `upfront_sale_type` ENUM('per sale', 'per kw', 'percent') NULL;");
        DB::statement("ALTER TABLE `user_upfront_history` MODIFY `old_upfront_sale_type` ENUM('per sale', 'per kw', 'percent') NULL;");

        DB::statement("ALTER TABLE `position_commission_upfronts` MODIFY `calculated_by` ENUM('per sale', 'per kw', 'percent') DEFAULT 'per kw';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
