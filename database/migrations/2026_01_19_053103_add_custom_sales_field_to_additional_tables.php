<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom sales field columns to additional location and history tables
     * Uses hasColumn checks to avoid duplicate column errors
     */
    public function up(): void
    {
        $tables = [
            'additional_locations',
            'user_additional_office_override_history',
            'onboarding_employee_locations',
            'onboarding_employee_additional_overrides',
            'onboarding_user_redline',
            'user_overrides_lock',
            'override_archive',
            'projection_user_overrides',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'custom_sales_field_id')) {
                Schema::table($tableName, function (Blueprint $table) {
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
        $tables = [
            'additional_locations',
            'user_additional_office_override_history',
            'onboarding_employee_locations',
            'onboarding_employee_additional_overrides',
            'onboarding_user_redline',
            'user_overrides_lock',
            'override_archive',
            'projection_user_overrides',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('custom_sales_field_id');
            });
        }
    }
};
