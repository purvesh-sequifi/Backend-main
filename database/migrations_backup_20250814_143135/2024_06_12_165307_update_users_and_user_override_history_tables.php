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
        Schema::table('users', function (Blueprint $table) {
            DB::statement("ALTER TABLE `users` CHANGE `direct_overrides_type` `direct_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT 'per kw'");
            DB::statement("ALTER TABLE `users` CHANGE `indirect_overrides_type` `indirect_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT 'per kw'");
            DB::statement("ALTER TABLE `users` CHANGE `office_overrides_type` `office_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT 'per kw'");
        });

        Schema::table('user_override_history', function (Blueprint $table) {
            DB::statement("ALTER TABLE `user_override_history` CHANGE `direct_overrides_type` `direct_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `user_override_history` CHANGE `indirect_overrides_type` `indirect_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `user_override_history` CHANGE `office_overrides_type` `office_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `user_override_history` CHANGE `old_direct_overrides_type` `old_direct_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `user_override_history` CHANGE `old_indirect_overrides_type` `old_indirect_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `user_override_history` CHANGE `old_office_overrides_type` `old_office_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
        });
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
