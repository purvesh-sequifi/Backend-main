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
        Schema::table('FieldRoutes_Raw_Data', function (Blueprint $table) {
            $table->timestamp('last_modified')->nullable()->after('last_synced_at')
                ->comment('Timestamp when record data was actually changed (not just synced)');

            // Add index for efficient querying of recently modified records
            $table->index('last_modified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('FieldRoutes_Raw_Data', function (Blueprint $table) {
            $table->dropIndex(['last_modified']);
            $table->dropColumn('last_modified');
        });
    }
};
