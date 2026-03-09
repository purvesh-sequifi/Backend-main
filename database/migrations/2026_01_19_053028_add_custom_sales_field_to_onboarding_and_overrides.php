<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom sales field columns to onboarding and override-related tables
     * Uses hasColumn and hasTable checks to avoid duplicate column errors
     * Note: Table is 'onboarding_employee_override' (singular)
     */
    public function up(): void
    {
        // Helper function to add column if table exists and column doesn't
        $addColumnIfNotExists = function (string $table, string $column) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->unsignedBigInteger($column)->nullable();
                });
            }
        };

        // Onboarding employees
        $onboardingEmployeeColumns = [
            'commission_custom_sales_field_id',
            'self_gen_commission_custom_sales_field_id',
            'upfront_custom_sales_field_id',
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ];
        foreach ($onboardingEmployeeColumns as $column) {
            $addColumnIfNotExists('onboarding_employees', $column);
        }

        // Onboarding employee override (singular, not plural)
        $overrideColumns = ['direct_custom_sales_field_id', 'indirect_custom_sales_field_id', 'office_custom_sales_field_id'];
        foreach ($overrideColumns as $column) {
            $addColumnIfNotExists('onboarding_employee_override', $column);
        }

        // Onboarding employee upfronts
        $addColumnIfNotExists('onboarding_employee_upfronts', 'custom_sales_field_id');

        // Manual overrides
        $addColumnIfNotExists('manual_overrides', 'custom_sales_field_id');

        // Manual overrides history
        $addColumnIfNotExists('manual_overrides_history', 'custom_sales_field_id');
        $addColumnIfNotExists('manual_overrides_history', 'old_custom_sales_field_id');

        // User overrides
        $addColumnIfNotExists('user_overrides', 'custom_sales_field_id');

        // User override history
        $addColumnIfNotExists('user_override_history', 'custom_sales_field_id');

        // User upfront history
        $addColumnIfNotExists('user_upfront_history', 'custom_sales_field_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Helper to drop column if exists
        $dropColumnIfExists = function (string $table, $columns) {
            if (Schema::hasTable($table)) {
                $cols = is_array($columns) ? $columns : [$columns];
                foreach ($cols as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        Schema::table($table, function (Blueprint $t) use ($col) {
                            $t->dropColumn($col);
                        });
                    }
                }
            }
        };

        $dropColumnIfExists('onboarding_employees', [
            'commission_custom_sales_field_id',
            'self_gen_commission_custom_sales_field_id',
            'upfront_custom_sales_field_id',
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ]);

        $dropColumnIfExists('onboarding_employee_override', [
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ]);

        $dropColumnIfExists('onboarding_employee_upfronts', 'custom_sales_field_id');
        $dropColumnIfExists('manual_overrides', 'custom_sales_field_id');
        $dropColumnIfExists('manual_overrides_history', ['custom_sales_field_id', 'old_custom_sales_field_id']);
        $dropColumnIfExists('user_overrides', 'custom_sales_field_id');
        $dropColumnIfExists('user_override_history', 'custom_sales_field_id');
        $dropColumnIfExists('user_upfront_history', 'custom_sales_field_id');
    }
};
