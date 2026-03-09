<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateTablesForPrimaryKeyAndUniqueConstraints extends Migration
{
    public function up()
    {
        // Tables to modify
        $tables = [
            'payroll_deduction_locks',
            'payroll_hourly_salary_lock',
            'payroll_overtimes_lock',
            'approvals_and_requests_lock',
            'clawback_settlements_lock',
            'payroll_adjustments_lock',
            'payroll_adjustment_details_lock',
            'user_commission_lock',
            'user_overrides_lock',
            'user_reconciliation_commissions_lock',
        ];

        foreach ($tables as $table) {

            if (! Schema::hasTable($table)) {
                continue;
            }
            // Remove primary key and auto-increment
            if (Schema::hasColumn($table, 'id')) {
                try {
                    Schema::table($table, function (Blueprint $table) {
                        // Try to drop primary key if it exists
                        $table->dropPrimary(['id']);
                    });
                } catch (\Exception $e) {
                    // Catch exception if no primary key exists and continue
                }

                // Remove auto-increment
                Schema::table($table, function (Blueprint $table) {
                    $table->unsignedBigInteger('id')->change(); // Remove auto-increment
                });
            }

            $uniqueExists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName()) // Ensure correct DB
                ->where('table_name', $table)
                ->where('index_name', 'unique_id_payroll_combination')
                ->exists();

            if (! $uniqueExists) {

                Schema::table($table, function (Blueprint $table) {
                    $table->unique(['id', 'payroll_id', 'user_id', 'pay_period_from', 'pay_period_to'], 'unique_id_payroll_combination');
                });
            }
        }
    }

    public function down()
    {
        // Remove unique constraint and restore primary key and auto-increment
        $tables = [
            'payroll_deduction_locks',
            'payroll_hourly_salary_lock',
            'payroll_overtimes_lock',
            'approvals_and_requests_lock',
            'clawback_settlements_lock',
            'payroll_adjustments_lock',
            'payroll_adjustment_details_lock',
            'user_commission_lock',
            'user_overrides_lock',
            'user_reconciliation_commissions_lock',
        ];

        foreach ($tables as $table) {
            // Remove unique constraint
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique('unique_id_payroll_combination');
            });

            // Restore primary key and auto-increment on 'id' column
            Schema::table($table, function (Blueprint $table) {
                $table->id()->change(); // Add auto-increment back to 'id'
                $table->primary('id');
            });
        }
    }
}
