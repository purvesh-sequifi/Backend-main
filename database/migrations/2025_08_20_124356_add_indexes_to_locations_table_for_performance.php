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
        Schema::table('locations', function (Blueprint $table) {
            // Composite index for state_id, type, and archived_at for the main query
            $table->index(['state_id', 'type', 'archived_at'], 'idx_locations_state_type_archived');

            // Index for type and archived_at for filtering offices
            $table->index(['type', 'archived_at'], 'idx_locations_type_archived');

            // Index for office_name for sorting
            $table->index('office_name', 'idx_locations_office_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('idx_locations_state_type_archived');
            $table->dropIndex('idx_locations_type_archived');
            $table->dropIndex('idx_locations_office_name');
        });
    }
};
