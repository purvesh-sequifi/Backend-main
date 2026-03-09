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
     * Performance optimization for Dashboard APIs - Sale Product Master table indexes
     * Optimizes whereHas('salesProductMasterDetails') queries in dashboard APIs
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            // Milestone-based filtering optimization (for PEST and other company types)
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_milestone_date')) {
                $table->index('milestone_date', 'idx_sale_product_master_milestone_date');
            }
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_is_last_date')) {
                $table->index('is_last_date', 'idx_sale_product_master_is_last_date');
            }

            // CRITICAL: Composite indexes for whereHas subqueries optimization
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_milestone')) {
                $table->index(['milestone_date', 'is_last_date'], 'idx_sale_product_master_milestone');
            }
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_last_milestone')) {
                $table->index(['is_last_date', 'milestone_date'], 'idx_sale_product_master_last_milestone');
            }

            // PID-based relationship optimization
            // NOTE: pid already has index (idx_sale_product_master_pid)
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_pid_last')) {
                $table->index(['pid', 'is_last_date'], 'idx_sale_product_master_pid_last');
            }
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_pid_milestone')) {
                $table->index(['pid', 'milestone_date'], 'idx_sale_product_master_pid_milestone');
            }
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_pid_milestone_composite')) {
                $table->index(['pid', 'milestone_date', 'is_last_date'], 'idx_sale_product_master_pid_milestone_composite');
            }

            // Complex composite for the most common dashboard query pattern
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_dashboard_complex')) {
                $table->index(['pid', 'is_last_date', 'milestone_date'], 'idx_sale_product_master_dashboard_complex');
            }

            // Additional optimization for product-based filtering
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_product_id')) {
                $table->index('product_id', 'idx_sale_product_master_product_id');
            }
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_master_product_milestone')) {
                $table->index(['product_id', 'milestone_date'], 'idx_sale_product_master_product_milestone');
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
        Schema::table('sale_product_master', function (Blueprint $table) {
            // Drop milestone-based indexes
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_milestone_date')) {
                $table->dropIndex('idx_sale_product_master_milestone_date');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_is_last_date')) {
                $table->dropIndex('idx_sale_product_master_is_last_date');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_milestone')) {
                $table->dropIndex('idx_sale_product_master_milestone');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_last_milestone')) {
                $table->dropIndex('idx_sale_product_master_last_milestone');
            }

            // Drop PID-based indexes
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_pid_last')) {
                $table->dropIndex('idx_sale_product_master_pid_last');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_pid_milestone')) {
                $table->dropIndex('idx_sale_product_master_pid_milestone');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_pid_milestone_composite')) {
                $table->dropIndex('idx_sale_product_master_pid_milestone_composite');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_dashboard_complex')) {
                $table->dropIndex('idx_sale_product_master_dashboard_complex');
            }

            // Drop product-specific indexes
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_product_milestone')) {
                $table->dropIndex('idx_sale_product_master_product_milestone');
            }
            if ($this->indexExists('sale_product_master', 'idx_sale_product_master_product_id')) {
                $table->dropIndex('idx_sale_product_master_product_id');
            }
        });
    }
};
