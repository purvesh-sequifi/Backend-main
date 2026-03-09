<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automation_action_logs', function (Blueprint $table) {
            $table->tinyInteger('is_new_contract')->nullable()->after('email_sent')
                ->comment('0=initial hire, 1=contract renewal, null=legacy');
            $table->string('context_type', 20)->nullable()->after('is_new_contract')
                ->comment('initial_onboarding, contract_renewal, null=legacy');

            // Add indexes for better query performance
            $table->index('is_new_contract', 'idx_automation_logs_new_contract');
            $table->index('context_type', 'idx_automation_logs_context_type');
            $table->index(['is_new_contract', 'created_at'], 'idx_automation_logs_contract_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_action_logs', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_automation_logs_new_contract');
            $table->dropIndex('idx_automation_logs_context_type');
            $table->dropIndex('idx_automation_logs_contract_date');

            // Drop columns
            $table->dropColumn(['is_new_contract', 'context_type']);
        });
    }
};
