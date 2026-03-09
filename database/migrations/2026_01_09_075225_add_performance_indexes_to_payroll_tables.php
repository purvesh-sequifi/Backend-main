<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add performance indexes to payroll-related tables.
     * Drops existing indexes if they exist before creating new ones.
     *
     * @return void
     */
    public function up(): void
    {
        // position_wages: idx_position_effective (position_id, effective_date)
        Schema::table('position_wages', function (Blueprint $table) {
            if ($this->indexExists('position_wages', 'idx_position_effective')) {
                $table->dropIndex('idx_position_effective');
            }
            $table->index(['position_id', 'effective_date'], 'idx_position_effective');
        });

        // payroll_hourly_salary: idx_user_period_status
        Schema::table('payroll_hourly_salary', function (Blueprint $table) {
            if ($this->indexExists('payroll_hourly_salary', 'idx_user_period_status')) {
                $table->dropIndex('idx_user_period_status');
            }
            $table->index([
                'user_id',
                'pay_period_from',
                'pay_period_to',
                'pay_frequency',
                'user_worker_type',
                'status'
            ], 'idx_user_period_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('position_wages', function (Blueprint $table) {
            if ($this->indexExists('position_wages', 'idx_position_effective')) {
                $table->dropIndex('idx_position_effective');
            }
        });

        Schema::table('payroll_hourly_salary', function (Blueprint $table) {
            if ($this->indexExists('payroll_hourly_salary', 'idx_user_period_status')) {
                $table->dropIndex('idx_user_period_status');
            }
        });
    }

    /**
     * Check if an index exists on a table.
     *
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return !empty($indexes);
    }
};
