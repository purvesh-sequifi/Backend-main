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
        // Data seeding moved to HiringStatusDataSeeder
        // This migration now only handles structure changes (none needed)
        // The 3 new hiring statuses are added via seeder for proper separation of concerns
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No structure changes to reverse
        // Data is managed by HiringStatusDataSeeder, not this migration
        // Migration rollback should NOT delete seeder data
    }
};
