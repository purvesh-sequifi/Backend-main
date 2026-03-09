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
        Schema::table('onboarding_employees', function (Blueprint $table) {
            DB::statement("ALTER TABLE `onboarding_employees` CHANGE `direct_overrides_type` `direct_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT 'per kw'");
            DB::statement("ALTER TABLE `onboarding_employees` CHANGE `indirect_overrides_type` `indirect_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT 'per kw'");
            DB::statement("ALTER TABLE `onboarding_employees` CHANGE `office_overrides_type` `office_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT 'per kw'");
        });

        Schema::table('onboarding_employee_override', function (Blueprint $table) {
            DB::statement("ALTER TABLE `onboarding_employee_override` CHANGE `direct_overrides_type` `direct_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `onboarding_employee_override` CHANGE `indirect_overrides_type` `indirect_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
            DB::statement("ALTER TABLE `onboarding_employee_override` CHANGE `office_overrides_type` `office_overrides_type` ENUM('per sale','per kw','percent') NULL DEFAULT NULL");
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
