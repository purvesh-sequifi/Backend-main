<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Sales API performance optimization.
     *
     * @return void
     */
    public function up()
    {
        // Add indexes to sale_masters table for better query performance
        Schema::table('sale_masters', function (Blueprint $table) {
            // Primary search and filter indexes
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_customer_signoff')) {
                $table->index('customer_signoff', 'idx_sale_masters_customer_signoff');
            }

            if (! $this->indexExists('sale_masters', 'idx_sale_masters_pid')) {
                $table->index('pid', 'idx_sale_masters_pid');
            }

            if (! $this->indexExists('sale_masters', 'idx_sale_masters_customer_name')) {
                $table->index('customer_name', 'idx_sale_masters_customer_name');
            }

            if (! $this->indexExists('sale_masters', 'idx_sale_masters_product_state')) {
                $table->index(['product_id', 'customer_state'], 'idx_sale_masters_product_state');
            }

            if (! $this->indexExists('sale_masters', 'idx_sale_masters_job_status')) {
                $table->index('job_status', 'idx_sale_masters_job_status');
            }

            if (! $this->indexExists('sale_masters', 'idx_sale_masters_date_cancelled')) {
                $table->index('date_cancelled', 'idx_sale_masters_date_cancelled');
            }

            // Composite indexes for common filter combinations
            if (! $this->indexExists('sale_masters', 'idx_sale_masters_signoff_product')) {
                $table->index(['customer_signoff', 'product_id'], 'idx_sale_masters_signoff_product');
            }

            if (! $this->indexExists('sale_masters', 'idx_sale_masters_install_partner')) {
                $table->index('install_partner', 'idx_sale_masters_install_partner');
            }
        });

        // Add indexes to sale_product_master table
        Schema::table('sale_product_master', function (Blueprint $table) {
            // Composite indexes for joins and aggregations
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_pid_type')) {
                $table->index(['pid', 'type'], 'idx_sale_product_pid_type');
            }

            if (! $this->indexExists('sale_product_master', 'idx_sale_product_milestone_date')) {
                $table->index('milestone_date', 'idx_sale_product_milestone_date');
            }

            if (! $this->indexExists('sale_product_master', 'idx_sale_product_schema_trigger')) {
                $table->index('milestone_schema_id', 'idx_sale_product_schema_trigger');
            }

            if (! $this->indexExists('sale_product_master', 'idx_sale_product_projected')) {
                $table->index('is_projected', 'idx_sale_product_projected');
            }

            // For milestone date range queries
            if (! $this->indexExists('sale_product_master', 'idx_sale_product_milestone_range')) {
                $table->index(['milestone_date', 'milestone_schema_id'], 'idx_sale_product_milestone_range');
            }
        });

        // Add indexes to sale_master_process table
        Schema::table('sale_master_process', function (Blueprint $table) {
            if (! $this->indexExists('sale_master_process', 'idx_sale_process_pid')) {
                $table->index('pid', 'idx_sale_process_pid');
            }

            if (! $this->indexExists('sale_master_process', 'idx_sale_process_users')) {
                $table->index(['closer1_id', 'closer2_id', 'setter1_id', 'setter2_id'], 'idx_sale_process_users');
            }

            if (! $this->indexExists('sale_master_process', 'idx_sale_process_status')) {
                $table->index('mark_account_status_id', 'idx_sale_process_status');
            }
        });

        // Add indexes to user_commission table for reconciliation queries
        Schema::table('user_commission', function (Blueprint $table) {
            if (! $this->indexExists('user_commission', 'idx_user_commission_pid_status')) {
                $table->index(['pid', 'status'], 'idx_user_commission_pid_status');
            }

            if (! $this->indexExists('user_commission', 'idx_user_commission_recon')) {
                $table->index(['pid', 'settlement_type'], 'idx_user_commission_recon');
            }
        });

        // Add indexes to users table for office-based filtering
        Schema::table('users', function (Blueprint $table) {
            if (! $this->indexExists('users', 'idx_users_office_id')) {
                $table->index('office_id', 'idx_users_office_id');
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
            $table->dropIndex('idx_sale_masters_customer_signoff');
            $table->dropIndex('idx_sale_masters_pid');
            $table->dropIndex('idx_sale_masters_customer_name');
            $table->dropIndex('idx_sale_masters_product_state');
            $table->dropIndex('idx_sale_masters_job_status');
            $table->dropIndex('idx_sale_masters_date_cancelled');
            $table->dropIndex('idx_sale_masters_signoff_product');
            $table->dropIndex('idx_sale_masters_install_partner');
        });

        Schema::table('sale_product_master', function (Blueprint $table) {
            $table->dropIndex('idx_sale_product_pid_type');
            $table->dropIndex('idx_sale_product_milestone_date');
            $table->dropIndex('idx_sale_product_schema_trigger');
            $table->dropIndex('idx_sale_product_projected');
            $table->dropIndex('idx_sale_product_milestone_range');
        });

        Schema::table('sale_master_process', function (Blueprint $table) {
            $table->dropIndex('idx_sale_process_pid');
            $table->dropIndex('idx_sale_process_users');
            $table->dropIndex('idx_sale_process_status');
        });

        Schema::table('user_commission', function (Blueprint $table) {
            $table->dropIndex('idx_user_commission_pid_status');
            $table->dropIndex('idx_user_commission_recon');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_office_id');
        });
    }

    /**
     * Check if an index exists on a table.
     *
     * @param  string  $table
     * @param  string  $index
     * @return bool
     */
    private function indexExists($table, $index)
    {
        try {
            $database = DB::connection()->getDatabaseName();

            // Use raw SQL query to check for index existence - more reliable across versions
            $result = DB::select(
                'SELECT COUNT(*) as count 
                 FROM information_schema.statistics 
                 WHERE table_schema = ? 
                 AND table_name = ? 
                 AND index_name = ?',
                [$database, $table, $index]
            );

            return $result[0]->count > 0;
        } catch (\Exception $e) {
            // If we can't check, assume index doesn't exist to be safe
            Log::warning("Could not check index existence for {$table}.{$index}: ".$e->getMessage());

            return false;
        }
    }
};
