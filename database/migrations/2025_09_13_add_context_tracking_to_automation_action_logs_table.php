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
            // Status transition tracking for onboarding automation
            $table->unsignedBigInteger('from_status_id')->nullable()->after('onboarding_id')
                ->comment('Previous status ID for onboarding automation context');
            $table->unsignedBigInteger('to_status_id')->nullable()->after('from_status_id')
                ->comment('New status ID for onboarding automation context');

            // Context hash for intelligent duplicate prevention
            $table->string('context_hash', 64)->nullable()->after('to_status_id')
                ->comment('Unique hash of automation context for duplicate prevention');

            // Additional context data for debugging and tracking
            $table->json('trigger_context')->nullable()->after('context_hash')
                ->comment('JSON data containing detailed trigger context information');

            // Indexes for fast duplicate checking and context lookups
            $table->index(['context_hash'], 'automation_context_hash_index');
            $table->index(['onboarding_id', 'automation_rule_id', 'from_status_id', 'to_status_id'],
                'automation_context_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_action_logs', function (Blueprint $table) {
            $table->dropIndex('automation_context_hash_index');
            $table->dropIndex('automation_context_lookup_index');
            $table->dropColumn(['from_status_id', 'to_status_id', 'context_hash', 'trigger_context']);
        });
    }
};
