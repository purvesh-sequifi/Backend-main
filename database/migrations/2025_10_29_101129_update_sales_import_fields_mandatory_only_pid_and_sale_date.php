<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Update all *SalesImportField tables to make only PID and Sale Date mandatory.
     * All other fields become optional.
     */
    public function up(): void
    {
        $tables = [
            'turf_sales_import_fields',
            'solar_sales_import_fields',
            'roofing_sales_import_fields',
            'pest_sales_import_fields',
            'mortgage_sales_import_fields',
            'fiber_sales_import_fields',
        ];

        foreach ($tables as $table) {
            // First, set all fields to non-mandatory
            DB::table($table)->update(['is_mandatory' => 0]);

            // Then, set only PID and Sale Date (customer_signoff) as mandatory
            DB::table($table)
                ->whereIn('name', ['pid', 'customer_signoff'])
                ->update(['is_mandatory' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This rollback sets all fields back to non-mandatory.
     * Original mandatory field states are not preserved.
     */
    public function down(): void
    {
        $tables = [
            'turf_sales_import_fields',
            'solar_sales_import_fields',
            'roofing_sales_import_fields',
            'pest_sales_import_fields',
            'mortgage_sales_import_fields',
            'fiber_sales_import_fields',
        ];

        foreach ($tables as $table) {
            // Revert: Set common mandatory fields back to mandatory
            // (pid, customer_name, customer_signoff were typically mandatory)
            DB::table($table)
                ->whereIn('name', ['pid', 'customer_name', 'customer_signoff'])
                ->update(['is_mandatory' => 1]);
        }
    }
};
