<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Critical indexes for sales export performance optimization
     * These indexes are specifically designed to optimize the sales export queries
     * 
     * @return void
     */
    public function up()
    {
        // Sales Masters - Core export table indexes
        Schema::table('sale_masters', function (Blueprint $table) {
            // Critical for date range filtering in exports
            $table->index('customer_signoff', 'sm_customer_signoff_idx');
            
            // Composite index for common filter combinations
            $table->index(['customer_signoff', 'product_id'], 'sm_signoff_product_idx');
            $table->index(['customer_signoff', 'customer_state'], 'sm_signoff_state_idx');
            $table->index(['customer_signoff', 'install_partner'], 'sm_signoff_installer_idx');
            $table->index(['customer_signoff', 'job_status'], 'sm_signoff_status_idx');
            
            // For search functionality
            $table->index(['customer_name', 'customer_signoff'], 'sm_name_signoff_idx');
            $table->index(['closer1_name', 'customer_signoff'], 'sm_closer1_signoff_idx');
            $table->index(['setter1_name', 'customer_signoff'], 'sm_setter1_signoff_idx');
            
            // For sorting optimization
            $table->index(['customer_signoff', 'total_commission'], 'sm_signoff_commission_idx');
            $table->index(['customer_signoff', 'total_override'], 'sm_signoff_override_idx');
            $table->index(['customer_signoff', 'kw'], 'sm_signoff_kw_idx');
            $table->index(['customer_signoff', 'epc'], 'sm_signoff_epc_idx');
            $table->index(['customer_signoff', 'net_epc'], 'sm_signoff_net_epc_idx');
        });

        // User Commission - Critical for export performance
        Schema::table('user_commission', function (Blueprint $table) {
            // For commission data lookup in exports
            $table->index(['pid', 'status'], 'uc_pid_status_idx');
            $table->index(['pid', 'settlement_type'], 'uc_pid_settlement_idx');
            
            // Composite index for reconciliation queries
            $table->index(['settlement_type', 'pid'], 'uc_settlement_pid_idx');
        });

        // Sale Product Master - For milestone data
        Schema::table('sale_product_master', function (Blueprint $table) {
            // Critical for milestone queries in exports
            $table->index(['pid', 'type'], 'spm_pid_type_idx');
            $table->index(['pid', 'milestone_date'], 'spm_pid_milestone_idx');
            $table->index(['milestone_schema_id', 'pid'], 'spm_schema_pid_idx');
        });

        // Clawback Settlement - For job status determination
        Schema::table('clawback_settlements', function (Blueprint $table) {
            // For efficient clawback PID lookup
            $table->index('pid', 'cs_pid_idx');
        });

        // Sale Master Process - For user relationship queries
        Schema::table('sale_master_process', function (Blueprint $table) {
            // Enhanced indexes for user lookups
            $table->index(['closer1_id', 'pid'], 'smp_closer1_pid_idx');
            $table->index(['closer2_id', 'pid'], 'smp_closer2_pid_idx');
            $table->index(['setter1_id', 'pid'], 'smp_setter1_pid_idx');
            $table->index(['setter2_id', 'pid'], 'smp_setter2_pid_idx');
        });

        // Users - For office filtering in exports
        Schema::table('users', function (Blueprint $table) {
            // For office-based filtering
            $table->index(['office_id', 'id'], 'users_office_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_office_id_idx');
        });

        Schema::table('sale_master_process', function (Blueprint $table) {
            $table->dropIndex('smp_closer1_pid_idx');
            $table->dropIndex('smp_closer2_pid_idx');
            $table->dropIndex('smp_setter1_pid_idx');
            $table->dropIndex('smp_setter2_pid_idx');
        });

        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->dropIndex('cs_pid_idx');
        });

        Schema::table('sale_product_master', function (Blueprint $table) {
            $table->dropIndex('spm_pid_type_idx');
            $table->dropIndex('spm_pid_milestone_idx');
            $table->dropIndex('spm_schema_pid_idx');
        });

        Schema::table('user_commission', function (Blueprint $table) {
            $table->dropIndex('uc_pid_status_idx');
            $table->dropIndex('uc_pid_settlement_idx');
            $table->dropIndex('uc_settlement_pid_idx');
        });

        Schema::table('sale_masters', function (Blueprint $table) {
            $table->dropIndex('sm_customer_signoff_idx');
            $table->dropIndex('sm_signoff_product_idx');
            $table->dropIndex('sm_signoff_state_idx');
            $table->dropIndex('sm_signoff_installer_idx');
            $table->dropIndex('sm_signoff_status_idx');
            $table->dropIndex('sm_name_signoff_idx');
            $table->dropIndex('sm_closer1_signoff_idx');
            $table->dropIndex('sm_setter1_signoff_idx');
            $table->dropIndex('sm_signoff_commission_idx');
            $table->dropIndex('sm_signoff_override_idx');
            $table->dropIndex('sm_signoff_kw_idx');
            $table->dropIndex('sm_signoff_epc_idx');
            $table->dropIndex('sm_signoff_net_epc_idx');
        });
    }
};