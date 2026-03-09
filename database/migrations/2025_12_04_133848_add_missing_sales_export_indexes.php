<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing sales export performance indexes
     * These 2 indexes were missing from the schema dump and are needed for optimal export performance
     *
     * @return void
     */
    public function up(): void
    {
        // User Commission - Add reverse order index for settlement type filtering
        Schema::table('user_commission', function (Blueprint $table) {
            // For reconciliation queries that filter by settlement_type first
            // Note: We already have (pid, settlement_type), this adds (settlement_type, pid)
            if (!$this->indexExists('user_commission', 'uc_settlement_pid_idx')) {
                $table->index(['settlement_type', 'pid'], 'uc_settlement_pid_idx');
            }
        });

        // Sale Product Master - Add composite index with milestone_schema_id and pid
        Schema::table('sale_product_master', function (Blueprint $table) {
            // For milestone schema queries that also need PID filtering
            // Note: Schema has milestone_schema_id alone, this adds the composite
            if (!$this->indexExists('sale_product_master', 'spm_schema_pid_idx')) {
                $table->index(['milestone_schema_id', 'pid'], 'spm_schema_pid_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            if ($this->indexExists('sale_product_master', 'spm_schema_pid_idx')) {
                $table->dropIndex('spm_schema_pid_idx');
            }
        });

        Schema::table('user_commission', function (Blueprint $table) {
            if ($this->indexExists('user_commission', 'uc_settlement_pid_idx')) {
                $table->dropIndex('uc_settlement_pid_idx');
            }
        });
    }

    /**
     * Check if an index exists on a table
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);

        return !empty($indexes);
    }
};

