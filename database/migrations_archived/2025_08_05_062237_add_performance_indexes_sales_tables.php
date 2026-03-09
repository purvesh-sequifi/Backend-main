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
        // Sale Master Process table indexes
        Schema::table('sale_master_process', function (Blueprint $table) {
            // Critical covering indexes for dashboard queries
            $table->index(['closer1_id', 'setter1_id', 'pid'], 'idx_closer_setter_pid');
            $table->index(['closer2_id', 'setter2_id', 'pid'], 'idx_closer2_setter2_pid');

            // Individual role indexes
            $table->index(['closer1_id', 'pid'], 'idx_closer1_pid');
            $table->index(['closer2_id', 'pid'], 'idx_closer2_pid');
            $table->index(['setter1_id', 'pid'], 'idx_setter1_pid');
            $table->index(['setter2_id', 'pid'], 'idx_setter2_pid');
        });

        // Sale Masters table indexes
        Schema::table('sale_masters', function (Blueprint $table) {
            // Critical indexes for dashboard and sales queries
            $table->index(['action_item_status', 'data_source_type'], 'idx_action_data_source');
            $table->index(['pid', 'action_item_status'], 'idx_pid_action_status');
            $table->index(['customer_signoff', 'action_item_status'], 'idx_signoff_action');

            // Additional performance indexes
            $table->index(['data_source_type', 'created_at'], 'idx_data_source_created');
            $table->index(['action_item_status', 'updated_at'], 'idx_action_updated');
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
            $table->dropIndex('idx_closer_setter_pid');
            $table->dropIndex('idx_closer2_setter2_pid');
            $table->dropIndex('idx_closer1_pid');
            $table->dropIndex('idx_closer2_pid');
            $table->dropIndex('idx_setter1_pid');
            $table->dropIndex('idx_setter2_pid');
        });

        Schema::table('sale_masters', function (Blueprint $table) {
            $table->dropIndex('idx_action_data_source');
            $table->dropIndex('idx_pid_action_status');
            $table->dropIndex('idx_signoff_action');
            $table->dropIndex('idx_data_source_created');
            $table->dropIndex('idx_action_updated');
        });
    }
};
