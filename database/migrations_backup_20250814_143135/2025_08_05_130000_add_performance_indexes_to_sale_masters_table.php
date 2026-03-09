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
     * Performance optimization for Dashboard APIs
     * Addresses critical performance issues in adminDashboardOfficePerformanceSelesKw API
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            // Core performance indexes for date-based filtering
            // NOTE: customer_signoff may already exist as 'sm_customer_signoff_idx' - check first
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_customer_signoff') && ! $this->indexExists('sale_masters', 'sm_customer_signoff_idx')) {
                $table->index('customer_signoff', 'idx_sale_masters_customer_signoff');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_date_cancelled')) {
                $table->index('date_cancelled', 'idx_sale_masters_date_cancelled');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_m2_date')) {
                $table->index('m2_date', 'idx_sale_masters_m2_date');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_m1_date')) {
                $table->index('m1_date', 'idx_sale_masters_m1_date');
            }

            // Composite indexes for common query patterns
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_signoff_cancelled')) {
                $table->index(['customer_signoff', 'date_cancelled'], 'idx_sale_masters_signoff_cancelled');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_signoff_m2_cancelled')) {
                $table->index(['customer_signoff', 'm2_date', 'date_cancelled'], 'idx_sale_masters_signoff_m2_cancelled');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_signoff_m1')) {
                $table->index(['customer_signoff', 'm1_date'], 'idx_sale_masters_signoff_m1');
            }

            // PID-based filtering optimization
            // NOTE: pid already has unique index (sale_masters_pid_unique)
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_pid_signoff')) {
                $table->index(['pid', 'customer_signoff'], 'idx_sale_masters_pid_signoff');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_pid_m2')) {
                $table->index(['pid', 'm2_date'], 'idx_sale_masters_pid_m2');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_pid_m1')) {
                $table->index(['pid', 'm1_date'], 'idx_sale_masters_pid_m1');
            }
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_pid_cancelled')) {
                $table->index(['pid', 'date_cancelled'], 'idx_sale_masters_pid_cancelled');
            }

            // Complex query optimization for dashboard APIs
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_dashboard_complex')) {
                $table->index(['customer_signoff', 'pid', 'm2_date', 'date_cancelled'], 'idx_sale_masters_dashboard_complex');
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
        Schema::table('sale_masters', function (Blueprint $table) {
            // Drop core performance indexes only if we created them
            if ($this->indexExists('sale_masters', 'idx_sale_masters_customer_signoff')) {
                $table->dropIndex('idx_sale_masters_customer_signoff');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_date_cancelled')) {
                $table->dropIndex('idx_sale_masters_date_cancelled');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_m2_date')) {
                $table->dropIndex('idx_sale_masters_m2_date');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_m1_date')) {
                $table->dropIndex('idx_sale_masters_m1_date');
            }

            // Drop composite indexes
            if ($this->indexExists('sale_masters', 'idx_sale_masters_signoff_cancelled')) {
                $table->dropIndex('idx_sale_masters_signoff_cancelled');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_signoff_m2_cancelled')) {
                $table->dropIndex('idx_sale_masters_signoff_m2_cancelled');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_signoff_m1')) {
                $table->dropIndex('idx_sale_masters_signoff_m1');
            }

            // Drop PID-based indexes
            if ($this->indexExists('sale_masters', 'idx_sale_masters_pid_signoff')) {
                $table->dropIndex('idx_sale_masters_pid_signoff');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_pid_m2')) {
                $table->dropIndex('idx_sale_masters_pid_m2');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_pid_m1')) {
                $table->dropIndex('idx_sale_masters_pid_m1');
            }
            if ($this->indexExists('sale_masters', 'idx_sale_masters_pid_cancelled')) {
                $table->dropIndex('idx_sale_masters_pid_cancelled');
            }

            // Drop complex query index
            if ($this->indexExists('sale_masters', 'idx_sale_masters_dashboard_complex')) {
                $table->dropIndex('idx_sale_masters_dashboard_complex');
            }
        });
    }
};
