<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fix: Gross Revenue capping at $10,000 due to decimal(8,4) precision limit.
     *
     * Problem:
     * - legacy_api_raw_data_histories.epc was decimal(8,4) → max 9,999.9999
     * - sale_masters.epc was float (imprecise)
     * - For mortgage companies, epc = "Gross Revenue" (loan amount × fee %)
     * - Example: $504,000 × 2.75% = $13,860 was capped at $9,999.99
     *
     * Solution:
     * - Change epc to decimal(16,4) in all tables
     * - Max value: 999,999,999,999.9999 (handles loans up to trillions)
     * - Maintains 4 decimal precision for accurate calculations
     */
    public function up(): void
    {
        // Table 1: legacy_api_raw_data_histories
        // This is where Excel import data is first stored
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->decimal('epc', 16, 4)->nullable()->change();
        });

        // Table 2: legacy_api_raw_data_histories_log
        // Historical log of import data
        Schema::table('legacy_api_raw_data_histories_log', function (Blueprint $table) {
            $table->decimal('epc', 16, 4)->nullable()->change();
        });

        // Table 3: sale_masters
        // Main sales table where epc is displayed as gross_revenue in API
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->decimal('epc', 16, 4)->nullable()->change();
        });

        // Table 4: legacy_api_null_data
        // Null/missing data tracking table
        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->decimal('epc', 16, 4)->nullable()->change();
        });

        // Table 5: legacy_api_row_data
        // Raw data storage table
        Schema::table('legacy_api_raw_data', function (Blueprint $table) {
            $table->decimal('epc', 16, 4)->nullable()->change();
        });

        // Table 6: sale_masters_excluded
        // Excluded sales tracking table
        Schema::table('sale_masters_excluded', function (Blueprint $table) {
            $table->decimal('epc', 16, 4)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original types
        // WARNING: This will cause data loss for values > 9,999.9999!

        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->decimal('epc', 8, 4)->nullable()->change();
        });

        Schema::table('legacy_api_raw_data_histories_log', function (Blueprint $table) {
            $table->decimal('epc', 8, 4)->nullable()->change();
        });

        Schema::table('sale_masters', function (Blueprint $table) {
            $table->float('epc')->nullable()->change();
        });

        Schema::table('legacy_api_data_null', function (Blueprint $table) {
            $table->float('epc')->nullable()->change();
        });

        Schema::table('legacy_api_raw_data', function (Blueprint $table) {
            $table->float('epc')->nullable()->change();
        });

        Schema::table('sale_masters_excluded', function (Blueprint $table) {
            $table->float('epc')->nullable()->change();
        });
    }
};
