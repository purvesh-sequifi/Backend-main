<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateTablesForPrimaryKeyAndUniqueConstraintsNew extends Migration
{
    public function up()
    {
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
            'recon_adjustment_locks',
            'recon_clawback_history_locks',
            'recon_commission_history_locks',
            'recon_deduction_history_locks',
            'recon_override_history_locks',
            'reconciliation_finalize_history_locks',
            'reconciliation_finalize_lock',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'id')) {
                // Drop primary key if it exists
                try {
                    DB::statement("ALTER TABLE `$table` DROP PRIMARY KEY");
                } catch (\Exception $e) {
                    // Handle exception if no primary key exists
                }

                // Remove AUTO_INCREMENT from 'id'
                DB::statement("ALTER TABLE `$table` MODIFY `id` BIGINT UNSIGNED NOT NULL");
            }

            // Check if unique constraint already exists
            $uniqueExists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', 'unique_id_payroll_combination')
                ->exists();

            if (! $uniqueExists) {
                if (
                    Schema::hasColumn($table, 'id') &&
                    Schema::hasColumn($table, 'payroll_id') &&
                    Schema::hasColumn($table, 'user_id') &&
                    Schema::hasColumn($table, 'pay_period_from') &&
                    Schema::hasColumn($table, 'pay_period_to')
                ) {
                    Schema::table($table, function (Blueprint $table) {
                        $table->unique(
                            ['id', 'payroll_id', 'user_id', 'pay_period_from', 'pay_period_to'],
                            'unique_id_payroll_combination'
                        );
                    });
                }
            }
        }
    }

    public function down()
    {
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
            'recon_adjustment_locks',
            'recon_clawback_history_locks',
            'recon_commission_history_locks',
            'recon_deduction_history_locks',
            'recon_override_history_locks',
            'reconciliation_finalize_history_locks',
            'reconciliation_finalize_lock',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Drop unique constraint if it exists
            $uniqueExists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', 'unique_id_payroll_combination')
                ->exists();

            if ($uniqueExists) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropUnique('unique_id_payroll_combination');
                });
            }

            // Restore auto-increment and primary key on 'id'
            DB::statement("ALTER TABLE `$table` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            DB::statement("ALTER TABLE `$table` ADD PRIMARY KEY (`id`)");
        }
    }
}
