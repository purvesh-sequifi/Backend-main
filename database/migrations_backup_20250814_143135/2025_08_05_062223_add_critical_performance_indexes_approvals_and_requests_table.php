<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            // Critical composite indexes for dashboard queries
            $table->index(['status', 'action_item_status'], 'idx_status_action_item');
            $table->index(['manager_id', 'status', 'action_item_status'], 'idx_manager_status_action');
            $table->index(['user_id', 'action_item_status'], 'idx_user_action_item');
            $table->index(['user_id', 'req_no', 'action_item_status'], 'idx_user_req_action');

            // Additional performance indexes
            $table->index(['adjustment_type_id', 'status'], 'idx_adjustment_type_status');
            $table->index(['pay_period_from', 'pay_period_to', 'status'], 'idx_pay_period_status');
            $table->index(['payroll_id', 'status'], 'idx_payroll_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            $table->dropIndex('idx_status_action_item');
            $table->dropIndex('idx_manager_status_action');
            $table->dropIndex('idx_user_action_item');
            $table->dropIndex('idx_user_req_action');
            $table->dropIndex('idx_adjustment_type_status');
            $table->dropIndex('idx_pay_period_status');
            $table->dropIndex('idx_payroll_status');
        });
    }
};
