<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds missing custom sales field columns to various tables
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

        // User override history - needs three separate columns for direct/indirect/office
        $addColumnIfNotExists('user_override_history', 'direct_custom_sales_field_id');
        $addColumnIfNotExists('user_override_history', 'indirect_custom_sales_field_id');
        $addColumnIfNotExists('user_override_history', 'office_custom_sales_field_id');

        // Position commission overrides - needs three separate columns
        $addColumnIfNotExists('position_commission_overrides', 'direct_custom_sales_field_id');
        $addColumnIfNotExists('position_commission_overrides', 'indirect_custom_sales_field_id');
        $addColumnIfNotExists('position_commission_overrides', 'office_custom_sales_field_id');

        // Onboarding employees - add simple custom_sales_field_id if not exists
        $addColumnIfNotExists('onboarding_employees', 'custom_sales_field_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
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

        $dropColumnIfExists('user_override_history', [
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ]);

        $dropColumnIfExists('position_commission_overrides', [
            'direct_custom_sales_field_id',
            'indirect_custom_sales_field_id',
            'office_custom_sales_field_id',
        ]);

        $dropColumnIfExists('onboarding_employees', 'custom_sales_field_id');
    }
};
