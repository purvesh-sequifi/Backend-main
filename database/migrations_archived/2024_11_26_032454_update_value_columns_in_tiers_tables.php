<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('tiers_levels', function (Blueprint $table) {
            $table->decimal('to_dealer_fee', 40, 2)->change();
            $table->decimal('from_dealer_fee', 40, 2)->change();
        });
        $tables = ['user_additional_office_override_history_tiers_ranges', 'user_commission_history_tiers_ranges', 'user_direct_override_history_tiers_ranges', 'user_indirect_override_history_tiers_ranges', 'user_office_override_history_tiers_ranges', 'user_upfront_history_tiers_ranges', 'onboarding_commission_tiers_level_range', 'onboarding_employee_direct_override_tiers_range', 'onboarding_employee_indirect_override_tiers_range', 'onboarding_employee_office_override_tiers_range', 'onboarding_employee_upfronts_tiers_range', 'onboarding_override_office_tiers_ranges']; // List of tables to update

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->decimal('value', 40, 2)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tiers_tables', function (Blueprint $table) {
            //
        });
    }
};
