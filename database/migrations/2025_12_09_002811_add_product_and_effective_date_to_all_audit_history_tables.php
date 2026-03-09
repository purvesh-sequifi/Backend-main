<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'user_redlines_audit_history',
            'user_upfront_audit_history',
            'user_withheld_audit_history',
            'user_selfgen_commission_audit_history',
            'user_override_audit_history',
            'user_organization_audit_history',
            'user_transfer_audit_history',
            'user_wages_audit_history',
            'user_deduction_audit_history',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    // Add product_id if not exists (for product-based tables)
                    if (!Schema::hasColumn($table, 'product_id')) {
                        $blueprint->unsignedBigInteger('product_id')->nullable()->after('user_id')->index();
                    }
                    // Add position_name if not exists
                    if (!Schema::hasColumn($table, 'position_name')) {
                        $blueprint->string('position_name', 255)->nullable()->after('product_id');
                    }
                    // Add effective_date if not exists
                    if (!Schema::hasColumn($table, 'effective_date')) {
                        $blueprint->date('effective_date')->nullable()->after('position_name')->index();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'user_redlines_audit_history',
            'user_upfront_audit_history',
            'user_withheld_audit_history',
            'user_selfgen_commission_audit_history',
            'user_override_audit_history',
            'user_organization_audit_history',
            'user_transfer_audit_history',
            'user_wages_audit_history',
            'user_deduction_audit_history',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (Schema::hasColumn($table, 'product_id')) {
                        $blueprint->dropColumn('product_id');
                    }
                    if (Schema::hasColumn($table, 'position_name')) {
                        $blueprint->dropColumn('position_name');
                    }
                    if (Schema::hasColumn($table, 'effective_date')) {
                        $blueprint->dropColumn('effective_date');
                    }
                });
            }
        }
    }
};
