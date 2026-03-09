<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Critical indexes for dashboard performance optimization
     *
     * @return void
     */
    public function up()
    {
        // User Override History - Critical for dashboard queries (Skip - already exists)
        // Schema::table('user_override_history', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // User Redlines - Dashboard queries (Skip - already exists)
        // Schema::table('user_redlines', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // User Commission History (Skip - already exists)
        // Schema::table('user_commission_history', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // User Upfront History (Skip - already exists)
        // Schema::table('user_upfront_history', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // User Withheld History (Skip - already exists)
        // Schema::table('user_withheld_history', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // User Organization History (Skip - already exists)
        // Schema::table('user_organization_history', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // Onboarding Employees (Skip - already exists)
        // Schema::table('onboarding_employees', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // Approvals And Requests (Skip - already exists)
        // Schema::table('approvals_and_requests', function (Blueprint $table) {
        //     // These indexes already exist from our optimization migration
        // });

        // Documents (Skip - already exists as idx_user_doc_action)
        // Schema::table('documents', function (Blueprint $table) {
        //     // This index already exists from our optimization migration
        // });

        // Users (Skip - social_security_no is TEXT column, manager_id already exists)
        // Schema::table('users', function (Blueprint $table) {
        //     // social_sequrity_no is TEXT column - can't index without key length
        //     // manager_id already exists as foreign key index
        // });

        // Sale Master Process - Critical for joins
        Schema::table('sale_master_process', function (Blueprint $table) {
            $table->index('pid', 'smp_pid_idx');
            $table->index(['closer1_id', 'setter1_id'], 'smp_closer1_setter1_idx');
        });

        // Sale Masters
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->index('pid', 'sm_pid_idx');
            $table->index(['action_item_status', 'data_source_type'], 'sm_action_datasource_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes in reverse order
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->dropIndex('sm_pid_idx');
            $table->dropIndex('sm_action_datasource_idx');
        });

        Schema::table('sale_master_process', function (Blueprint $table) {
            $table->dropIndex('smp_pid_idx');
            $table->dropIndex('smp_closer1_setter1_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_ssn_action_idx');
            $table->dropIndex('users_manager_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('doc_user_response_idx');
        });

        Schema::table('approvals_and_requests', function (Blueprint $table) {
            $table->dropIndex('aar_status_action_idx');
            $table->dropIndex('aar_manager_status_idx');
            $table->dropIndex('aar_user_req_idx');
        });

        Schema::table('onboarding_employees', function (Blueprint $table) {
            $table->dropIndex('oe_action_status_idx');
            $table->dropIndex('oe_hired_status_idx');
        });

        Schema::table('user_organization_history', function (Blueprint $table) {
            $table->dropIndex('uorgh_action_status_idx');
            $table->dropIndex('uorgh_user_action_idx');
        });

        Schema::table('user_withheld_history', function (Blueprint $table) {
            $table->dropIndex('uwh_action_status_idx');
            $table->dropIndex('uwh_user_action_idx');
            $table->dropIndex('uwh_withheld_amount_idx');
        });

        Schema::table('user_upfront_history', function (Blueprint $table) {
            $table->dropIndex('uuh_action_status_idx');
            $table->dropIndex('uuh_user_action_idx');
            $table->dropIndex('uuh_upfront_amount_idx');
        });

        Schema::table('user_commission_history', function (Blueprint $table) {
            $table->dropIndex('uch_action_status_idx');
            $table->dropIndex('uch_user_action_idx');
            $table->dropIndex('uch_old_commission_idx');
        });

        Schema::table('user_redlines', function (Blueprint $table) {
            $table->dropIndex('ur_action_status_idx');
            $table->dropIndex('ur_user_action_idx');
            $table->dropIndex('ur_redline_type_idx');
            $table->dropIndex('ur_old_redline_idx');
        });

        Schema::table('user_override_history', function (Blueprint $table) {
            $table->dropIndex('uoh_action_status_idx');
            $table->dropIndex('uoh_user_action_idx');
            $table->dropIndex('uoh_direct_override_idx');
            $table->dropIndex('uoh_indirect_override_idx');
            $table->dropIndex('uoh_office_override_idx');
            $table->dropIndex('uoh_stack_override_idx');
        });
    }
};
