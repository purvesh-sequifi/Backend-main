<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds 'custom field' option to various enum columns for Custom Sales Fields feature
     */
    public function up(): void
    {
        // user_commission_history.commission_type
        DB::statement("ALTER TABLE `user_commission_history` MODIFY `commission_type` ENUM('percent','per kw','per sale','custom field')");

        // user_upfront_history.upfront_sale_type
        DB::statement("ALTER TABLE `user_upfront_history` MODIFY `upfront_sale_type` ENUM('percent','per kw','per sale','custom field')");

        // user_override_history - three override type columns
        DB::statement("ALTER TABLE `user_override_history` MODIFY `direct_overrides_type` ENUM('per sale','per kw','percent','custom field')");
        DB::statement("ALTER TABLE `user_override_history` MODIFY `indirect_overrides_type` ENUM('per sale','per kw','percent','custom field')");
        DB::statement("ALTER TABLE `user_override_history` MODIFY `office_overrides_type` ENUM('per sale','per kw','percent','custom field')");

        // position_commissions.commission_amount_type
        DB::statement("ALTER TABLE `position_commissions` MODIFY `commission_amount_type` ENUM('percent','per kw','per sale','custom field')");

        // position_commission_upfronts.calculated_by
        DB::statement("ALTER TABLE `position_commission_upfronts` MODIFY `calculated_by` ENUM('per sale','per kw','percent','custom field')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting enum changes could cause data loss if 'custom field' values exist
        // Only revert if no records use 'custom field' value
        
        // user_commission_history.commission_type
        DB::statement("ALTER TABLE `user_commission_history` MODIFY `commission_type` ENUM('percent','per kw','per sale')");

        // user_upfront_history.upfront_sale_type
        DB::statement("ALTER TABLE `user_upfront_history` MODIFY `upfront_sale_type` ENUM('percent','per kw','per sale')");

        // user_override_history
        DB::statement("ALTER TABLE `user_override_history` MODIFY `direct_overrides_type` ENUM('per sale','per kw','percent')");
        DB::statement("ALTER TABLE `user_override_history` MODIFY `indirect_overrides_type` ENUM('per sale','per kw','percent')");
        DB::statement("ALTER TABLE `user_override_history` MODIFY `office_overrides_type` ENUM('per sale','per kw','percent')");

        // position_commissions.commission_amount_type
        DB::statement("ALTER TABLE `position_commissions` MODIFY `commission_amount_type` ENUM('percent','per kw','per sale')");

        // position_commission_upfronts.calculated_by
        DB::statement("ALTER TABLE `position_commission_upfronts` MODIFY `calculated_by` ENUM('per sale','per kw','percent')");
    }
};
