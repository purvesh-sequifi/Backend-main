<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SIM-2283: Add index for external_user_email to support employee document lookup
 *
 * This index optimizes queries that find documents sent to external recipients
 * who were later hired as employees (matching by email).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Composite index for external email lookups with is_active filter
        if (! $this->indexExists('new_sequi_docs_documents', 'idx_new_sequi_docs_external_email')) {
            DB::statement('CREATE INDEX idx_new_sequi_docs_external_email ON new_sequi_docs_documents(external_user_email, is_external_recipient, is_active)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_new_sequi_docs_external_email ON new_sequi_docs_documents');
    }

    /**
     * Check if index exists on table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return ! empty($result);
    }
};
