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
        $positionId = DB::table('positions')->where('position_name', 'Super Admin')->value('id');
        if (! $positionId) {
            $position_id = DB::table('positions')->insertGetId([
                'position_name' => 'Super Admin',
                'worker_type' => '1099',
                'department_id' => 2,
                'parent_id' => 2,
                'org_parent_id' => null,
                'group_id' => 1,
                'is_manager' => 1,
                'is_stack' => null,
                'is_selfgen' => 2,
                'order_by' => null,
                'offer_letter_template_id' => null,
                'created_at' => NOW(),
                'updated_at' => NOW(),
                'setup_status' => 1,
                'worker_id' => '1099',
                'can_act_as_both_setter_and_closer' => 0,
                'applied_for_users' => null,
            ]);

            DB::statement("INSERT INTO `position_commissions` (`position_id`, `core_position_id`, `product_id`, `tiers_id`, `self_gen_user`, `commission_parentage`, `commission_amount_type`, `commission_status`, `commission_parentag_hiring_locked`, `commission_amount_type_locked`, `tiers_hiring_locked`, `tiers_advancement`, `commission_structure_type`, `commission_parentag_type_hiring_locked`, `created_at`, `updated_at`) VALUES
            ($position_id, NULL, 1, 0, 0, 0, 'percent', 0, 0, 0, 0, NULL, NULL, 0, NOW(), NOW())");

            DB::statement("INSERT INTO `positions_duduction_limits` (`deduction_setting_id`, `position_id`, `status`, `limit_type`, `limit_ammount`, `limit`, `created_at`, `updated_at`) VALUES
            (NULL, $position_id, 0, '', NULL, NULL,  NOW(),  NOW())");

            DB::statement("INSERT INTO `position_commission_deduction_settings` (`name`, `position_id`, `status`, `deducation_locked`, `created_at`, `updated_at`) VALUES
            (NULL, $position_id, 0, NULL, NOW(), NOW())");

            DB::statement("INSERT INTO `position_commission_overrides` (`position_id`, `core_position_id`, `product_id`, `tiers_id`, `override_id`, `settlement_id`, `override_ammount`, `override_ammount_locked`, `type`, `override_type_locked`, `tiers_hiring_locked`, `status`, `created_at`, `updated_at`) VALUES
            ($position_id, NULL, 1, 0, 1, 0, NULL, 0, NULL, 0, 0, 0, NOW(), NOW()),
            ($position_id, NULL, 1, 0, 2, 0, NULL, 0, NULL, 0, 0, 0, NOW(), NOW()),
            ($position_id, NULL, 1, 0, 3, 0, NULL, 0, NULL, 0, 0, 0, NOW(), NOW()),
            ($position_id, NULL, 1, 0, 4, 0, NULL, 0, NULL, 0, 0, 0, NOW(), NOW())");

            DB::statement("INSERT INTO `position_commission_upfronts` (`position_id`, `core_position_id`, `product_id`, `tiers_id`, `milestone_schema_id`, `milestone_schema_trigger_id`, `self_gen_user`, `status_id`, `upfront_ammount`, `upfront_ammount_locked`, `calculated_by`, `calculated_locked`, `upfront_status`, `upfront_system`, `upfront_system_locked`, `tiers_hiring_locked`, `tiers_advancement`, `upfront_limit`, `created_at`, `updated_at`) VALUES
            ($position_id, NULL, 1, 0, NULL, NULL, 0, 0, 0.00, 0, 'per kw', 0, 0, 'Fixed', 0, 0, NULL, NULL, NOW(), NOW())");

            DB::statement("INSERT INTO `position_products` (`position_id`, `product_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
            ($position_id, 1, NOW(), NOW(), NULL)");

            DB::statement("INSERT INTO `position_wages` (`position_id`, `pay_type`, `pay_type_lock`, `pay_rate`, `pay_rate_type`, `pay_rate_lock`, `pto_hours`, `pto_hours_lock`, `unused_pto_expires`, `unused_pto_expires_lock`, `expected_weekly_hours`, `expected_weekly_hours_lock`, `overtime_rate`, `overtime_rate_lock`, `wages_status`, `created_at`, `updated_at`) VALUES
            ($position_id, NULL, 0, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, 0, NOW(), NOW())");

            $frequency_type_id = DB::table('frequency_types')->where('status', 1)->first();
            if ($frequency_type_id) {
                DB::statement("INSERT INTO `position_pay_frequencies` (`position_id`, `frequency_type_id`, `first_months`, `first_day`, `day_of_week`, `day_of_months`, `pay_period`, `monthly_per_days`, `first_day_pay_of_manths`, `second_pay_day_of_month`, `deadline_to_run_payroll`, `first_pay_period_ends_on`, `created_at`, `updated_at`) VALUES
                ($position_id, $frequency_type_id->id, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())");
            }

        }

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
