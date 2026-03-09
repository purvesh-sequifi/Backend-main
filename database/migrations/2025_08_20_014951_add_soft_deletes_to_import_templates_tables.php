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
            'fiber_sales_import_templates',
            'solar_sales_import_templates',
            'turf_sales_import_templates',
            'roofing_sales_import_templates',
            'mortgage_sales_import_templates',
            'pest_sales_import_templates',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes();
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
            'fiber_sales_import_templates',
            'solar_sales_import_templates',
            'turf_sales_import_templates',
            'roofing_sales_import_templates',
            'mortgage_sales_import_templates',
            'pest_sales_import_templates',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }
    }
};
