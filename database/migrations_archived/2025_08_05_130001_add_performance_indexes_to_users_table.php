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
     * Performance optimization for Dashboard APIs - Users table indexes
     * Optimizes office filtering in dashboard performance APIs
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Skip office_id index - already exists as 'idx_office_id' in main branch
            // $table->index('office_id', 'idx_users_office_id'); // REDUNDANT - SKIPPED

            // Admin and role-based filtering - NEW indexes
            if (! $this->indexExists('users', 'idx_users_is_super_admin')) {
                $table->index('is_super_admin', 'idx_users_is_super_admin');
            }
            if (! $this->indexExists('users', 'idx_users_is_manager')) {
                $table->index('is_manager', 'idx_users_is_manager');
            }

            // Composite indexes for common dashboard queries - NEW indexes
            if (! $this->indexExists('users', 'idx_users_office_admin')) {
                $table->index(['office_id', 'is_super_admin'], 'idx_users_office_admin');
            }
            if (! $this->indexExists('users', 'idx_users_manager_office')) {
                $table->index(['manager_id', 'office_id'], 'idx_users_manager_office');
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
        Schema::table('users', function (Blueprint $table) {
            // Skip dropping office_id index - we didn't create it
            // $table->dropIndex('idx_users_office_id'); // SKIPPED

            // Drop admin and role indexes
            if ($this->indexExists('users', 'idx_users_is_super_admin')) {
                $table->dropIndex('idx_users_is_super_admin');
            }
            if ($this->indexExists('users', 'idx_users_is_manager')) {
                $table->dropIndex('idx_users_is_manager');
            }

            // Drop composite indexes
            if ($this->indexExists('users', 'idx_users_office_admin')) {
                $table->dropIndex('idx_users_office_admin');
            }
            if ($this->indexExists('users', 'idx_users_manager_office')) {
                $table->dropIndex('idx_users_manager_office');
            }
        });
    }
};
