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
        Schema::table('projection_user_commissions', function (Blueprint $table) {
            // Check and add individual indexes for user_id and pid columns only if they don't exist
            if (! $this->indexExists('projection_user_commissions', 'idx_projection_user_commissions_user_id')) {
                $table->index('user_id', 'idx_projection_user_commissions_user_id');
            }

            if (! $this->indexExists('projection_user_commissions', 'idx_projection_user_commissions_pid')) {
                $table->index('pid', 'idx_projection_user_commissions_pid');
            }

            // Add composite index for queries that filter by both columns only if it doesn't exist
            if (! $this->indexExists('projection_user_commissions', 'idx_projection_user_commissions_user_id_pid')) {
                $table->index(['user_id', 'pid'], 'idx_projection_user_commissions_user_id_pid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projection_user_commissions', function (Blueprint $table) {
            // Drop indexes in reverse order (composite first, then individual) only if they exist
            if ($this->indexExists('projection_user_commissions', 'idx_projection_user_commissions_user_id_pid')) {
                $table->dropIndex('idx_projection_user_commissions_user_id_pid');
            }

            if ($this->indexExists('projection_user_commissions', 'idx_projection_user_commissions_pid')) {
                $table->dropIndex('idx_projection_user_commissions_pid');
            }

            if ($this->indexExists('projection_user_commissions', 'idx_projection_user_commissions_user_id')) {
                $table->dropIndex('idx_projection_user_commissions_user_id');
            }
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");

        foreach ($indexes as $indexInfo) {
            if ($indexInfo->Key_name === $index) {
                return true;
            }
        }

        return false;
    }
};
