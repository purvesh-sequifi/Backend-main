<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check if an index exists on a table
     */
    private function indexExists($table, $indexName)
    {
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table);

        return array_key_exists($indexName, $indexes);
    }

    /**
     * Run the migrations.
     *
     * Performance optimization for Dashboard APIs - Sale Master Process table indexes
     * Optimizes user-role filtering in sales performance calculations
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_master_process', function (Blueprint $table) {
            // Skip individual user role indexes - similar indexes already exist in main branch
            // Main branch has: smp_closer1_pid_idx, smp_closer2_pid_idx, etc.
            // These are composite indexes (role_id, pid) which are more efficient than single column indexes

            // Skip PID index - may already exist
            if (! $this->indexExists('sale_master_process', 'idx_sale_master_process_pid')) {
                $table->index('pid', 'idx_sale_master_process_pid');
            }

            // NEW: Composite indexes for complex OR queries in dashboard - these are UNIQUE and needed
            if (! $this->indexExists('sale_master_process', 'idx_sale_master_process_closers')) {
                $table->index(['closer1_id', 'closer2_id'], 'idx_sale_master_process_closers');
            }
            if (! $this->indexExists('sale_master_process', 'idx_sale_master_process_setters')) {
                $table->index(['setter1_id', 'setter2_id'], 'idx_sale_master_process_setters');
            }

            // NEW: Complex composite index for the most common dashboard query pattern
            // Covers: whereIn('closer1_id',$userIds)->orWhereIn('closer2_id',$userIds)
            //         ->orWhereIn('setter1_id',$userIds)->orWhereIn('setter2_id',$userIds)
            if (! $this->indexExists('sale_master_process', 'idx_sale_master_process_all_users')) {
                $table->index(['pid', 'closer1_id', 'closer2_id', 'setter1_id', 'setter2_id'], 'idx_sale_master_process_all_users');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_master_process', function (Blueprint $table) {
            // Skip dropping individual user role indexes - we didn't create them

            // Drop PID index if we created it
            if ($this->indexExists('sale_master_process', 'idx_sale_master_process_pid')) {
                $table->dropIndex('idx_sale_master_process_pid');
            }

            // Drop composite indexes
            if ($this->indexExists('sale_master_process', 'idx_sale_master_process_closers')) {
                $table->dropIndex('idx_sale_master_process_closers');
            }
            if ($this->indexExists('sale_master_process', 'idx_sale_master_process_setters')) {
                $table->dropIndex('idx_sale_master_process_setters');
            }
            if ($this->indexExists('sale_master_process', 'idx_sale_master_process_all_users')) {
                $table->dropIndex('idx_sale_master_process_all_users');
            }
        });
    }
};
