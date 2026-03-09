<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Primary performance indexes for leads table

            // Index for type and status filtering (most common query)
            if (! $this->indexExists('leads', 'idx_leads_type_status')) {
                $table->index(['type', 'status'], 'idx_leads_type_status');
            }

            // Index for recruiter_id (for non-super admin users)
            if (! $this->indexExists('leads', 'idx_leads_recruiter_id')) {
                $table->index('recruiter_id', 'idx_leads_recruiter_id');
            }

            // Index for office_id (for manager filtering)
            if (! $this->indexExists('leads', 'idx_leads_office_id')) {
                $table->index('office_id', 'idx_leads_office_id');
            }

            // Index for pipeline_status_id (for status filtering)
            if (! $this->indexExists('leads', 'idx_leads_pipeline_status_id')) {
                $table->index('pipeline_status_id', 'idx_leads_pipeline_status_id');
            }

            // Index for state_id (for state filtering)
            if (! $this->indexExists('leads', 'idx_leads_state_id')) {
                $table->index('state_id', 'idx_leads_state_id');
            }

            // Index for reporting_manager_id
            if (! $this->indexExists('leads', 'idx_leads_reporting_manager_id')) {
                $table->index('reporting_manager_id', 'idx_leads_reporting_manager_id');
            }

            // Composite index for ordering and filtering
            if (! $this->indexExists('leads', 'idx_leads_type_status_id')) {
                $table->index(['type', 'status', 'id'], 'idx_leads_type_status_id');
            }

            // Index for pipeline_status_date (for days calculation)
            if (! $this->indexExists('leads', 'idx_leads_pipeline_status_date')) {
                $table->index('pipeline_status_date', 'idx_leads_pipeline_status_date');
            }

            // Index for overall_rating (for rating filtering)
            if (! $this->indexExists('leads', 'idx_leads_overall_rating')) {
                $table->index('overall_rating', 'idx_leads_overall_rating');
            }
        });

        // Add indexes to related tables

        // Additional locations table
        if (! $this->indexExists('additional_locations', 'idx_additional_locations_user_office')) {
            DB::statement('CREATE INDEX idx_additional_locations_user_office ON additional_locations(user_id, office_id)');
        }

        // Pipeline sub task complete by leads table
        if (! $this->indexExists('pipeline_sub_task_complete_by_leads', 'idx_pipeline_sub_task_complete_lead_status')) {
            DB::statement('CREATE INDEX idx_pipeline_sub_task_complete_lead_status ON pipeline_sub_task_complete_by_leads(lead_id, pipeline_lead_status_id, completed)');
        }

        // Pipeline sub tasks table
        if (! $this->indexExists('pipeline_sub_tasks', 'idx_pipeline_sub_tasks_status_id')) {
            DB::statement('CREATE INDEX idx_pipeline_sub_tasks_status_id ON pipeline_sub_tasks(pipeline_lead_status_id)');
        }

        // Additional custom fields table
        if (! $this->indexExists('additional_custom_fields', 'idx_additional_custom_fields_type_deleted')) {
            DB::statement('CREATE INDEX idx_additional_custom_fields_type_deleted ON additional_custom_fields(type, is_deleted)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_type_status');
            $table->dropIndex('idx_leads_recruiter_id');
            $table->dropIndex('idx_leads_office_id');
            $table->dropIndex('idx_leads_pipeline_status_id');
            $table->dropIndex('idx_leads_state_id');
            $table->dropIndex('idx_leads_reporting_manager_id');
            $table->dropIndex('idx_leads_type_status_id');
            $table->dropIndex('idx_leads_pipeline_status_date');
            $table->dropIndex('idx_leads_overall_rating');
        });

        DB::statement('DROP INDEX IF EXISTS idx_additional_locations_user_office ON additional_locations');
        DB::statement('DROP INDEX IF EXISTS idx_pipeline_sub_task_complete_lead_status ON pipeline_sub_task_complete_by_leads');
        DB::statement('DROP INDEX IF EXISTS idx_pipeline_sub_tasks_status_id ON pipeline_sub_tasks');
        DB::statement('DROP INDEX IF EXISTS idx_additional_custom_fields_type_deleted ON additional_custom_fields');
    }

    /**
     * Check if index exists
     */
    private function indexExists($table, $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return ! empty($indexes);
    }
};
