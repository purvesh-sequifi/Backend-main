<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Priority 1: Most frequently queried combinations for SequiDocs performance

        // Visible signatures optimization - document_id and document_signer_id combination
        if (! $this->indexExists('visible_signatures', 'idx_visible_signatures_document_signer')) {
            DB::statement('CREATE INDEX idx_visible_signatures_document_signer ON visible_signatures(document_id, document_signer_id)');
        }

        // Envelope documents optimization - id and status (MySQL compatible)
        if (! $this->indexExists('envelope_documents', 'idx_envelope_documents_id_status')) {
            DB::statement('CREATE INDEX idx_envelope_documents_id_status ON envelope_documents(id, status, deleted_at)');
        }

        // New sequi docs documents - signature_request_document_id lookup
        if (! $this->indexExists('new_sequi_docs_documents', 'idx_new_sequi_docs_signature_request')) {
            DB::statement('CREATE INDEX idx_new_sequi_docs_signature_request ON new_sequi_docs_documents(signature_request_document_id)');
        }

        // Priority 2: Supporting indexes for employee hiring logic

        // Onboarding employees optimization - id, status_id, user_id combination
        if (! $this->indexExists('onboarding_employees', 'idx_onboarding_employees_status_user')) {
            DB::statement('CREATE INDEX idx_onboarding_employees_status_user ON onboarding_employees(id, status_id, user_id)');
        }

        // Envelope documents template optimization - template processing
        if (! $this->indexExists('envelope_documents', 'idx_envelope_documents_template')) {
            DB::statement('CREATE INDEX idx_envelope_documents_template ON envelope_documents(id, template_name, is_pdf)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        DB::statement('DROP INDEX IF EXISTS idx_envelope_documents_template ON envelope_documents');
        DB::statement('DROP INDEX IF EXISTS idx_onboarding_employees_status_user ON onboarding_employees');
        DB::statement('DROP INDEX IF EXISTS idx_new_sequi_docs_signature_request ON new_sequi_docs_documents');
        DB::statement('DROP INDEX IF EXISTS idx_envelope_documents_id_status ON envelope_documents');
        DB::statement('DROP INDEX IF EXISTS idx_visible_signatures_document_signer ON visible_signatures');
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
