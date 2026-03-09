<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom_sales_field_id to position-related tables
     * Uses hasColumn checks to avoid duplicate column errors
     * Note: The actual table name is 'position_commission_overrides' not 'position_overrides'
     */
    public function up(): void
    {
        // Extend position_commissions
        if (Schema::hasTable('position_commissions') && !Schema::hasColumn('position_commissions', 'custom_sales_field_id')) {
            Schema::table('position_commissions', function (Blueprint $table) {
                $table->unsignedBigInteger('custom_sales_field_id')
                    ->nullable()
                    ->after('commission_amount_type');
                $table->foreign('custom_sales_field_id')
                    ->references('id')
                    ->on('crmsale_custom_field');
            });
        }

        // Extend position_commission_overrides (the actual override table)
        if (Schema::hasTable('position_commission_overrides')) {
            $overrideColumns = [
                'direct_custom_sales_field_id',
                'indirect_custom_sales_field_id',
                'office_custom_sales_field_id',
            ];
            foreach ($overrideColumns as $column) {
                if (!Schema::hasColumn('position_commission_overrides', $column)) {
                    Schema::table('position_commission_overrides', function (Blueprint $table) use ($column) {
                        $table->unsignedBigInteger($column)->nullable();
                    });
                }
            }
        }

        // Extend position_commission_upfronts
        if (Schema::hasTable('position_commission_upfronts') && !Schema::hasColumn('position_commission_upfronts', 'custom_sales_field_id')) {
            Schema::table('position_commission_upfronts', function (Blueprint $table) {
                $table->unsignedBigInteger('custom_sales_field_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('position_commissions') && Schema::hasColumn('position_commissions', 'custom_sales_field_id')) {
            Schema::table('position_commissions', function (Blueprint $table) {
                $table->dropForeign(['custom_sales_field_id']);
                $table->dropColumn('custom_sales_field_id');
            });
        }

        if (Schema::hasTable('position_commission_overrides')) {
            $columns = ['direct_custom_sales_field_id', 'indirect_custom_sales_field_id', 'office_custom_sales_field_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('position_commission_overrides', $column)) {
                    Schema::table('position_commission_overrides', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('position_commission_upfronts') && Schema::hasColumn('position_commission_upfronts', 'custom_sales_field_id')) {
            Schema::table('position_commission_upfronts', function (Blueprint $table) {
                $table->dropColumn('custom_sales_field_id');
            });
        }
    }
};
