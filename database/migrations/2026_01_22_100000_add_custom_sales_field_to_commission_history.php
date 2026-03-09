<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom sales field columns to user_commission_history table
     * This enables tracking of custom field IDs for audit log display
     */
    public function up(): void
    {
        // User commission history - for audit log display of custom field names
        if (Schema::hasTable('user_commission_history')) {
            if (!Schema::hasColumn('user_commission_history', 'custom_sales_field_id')) {
                Schema::table('user_commission_history', function (Blueprint $table) {
                    $table->unsignedBigInteger('custom_sales_field_id')->nullable()->after('tiers_id');
                });
            }
            if (!Schema::hasColumn('user_commission_history', 'old_custom_sales_field_id')) {
                Schema::table('user_commission_history', function (Blueprint $table) {
                    $table->unsignedBigInteger('old_custom_sales_field_id')->nullable()->after('custom_sales_field_id');
                });
            }
        }

        // User commission table - for calculation tracking
        if (Schema::hasTable('user_commission')) {
            if (!Schema::hasColumn('user_commission', 'custom_sales_field_id')) {
                Schema::table('user_commission', function (Blueprint $table) {
                    $table->unsignedBigInteger('custom_sales_field_id')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_commission_history')) {
            if (Schema::hasColumn('user_commission_history', 'custom_sales_field_id')) {
                Schema::table('user_commission_history', function (Blueprint $table) {
                    $table->dropColumn('custom_sales_field_id');
                });
            }
            if (Schema::hasColumn('user_commission_history', 'old_custom_sales_field_id')) {
                Schema::table('user_commission_history', function (Blueprint $table) {
                    $table->dropColumn('old_custom_sales_field_id');
                });
            }
        }

        if (Schema::hasTable('user_commission')) {
            if (Schema::hasColumn('user_commission', 'custom_sales_field_id')) {
                Schema::table('user_commission', function (Blueprint $table) {
                    $table->dropColumn('custom_sales_field_id');
                });
            }
        }
    }
};
