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
        Schema::table('everee_transections_log', function (Blueprint $table) {
            // Add composite index for better query performance
            // This index optimizes the query: WHERE user_id = ? AND api_name IN (...) ORDER BY id DESC
            $table->index(['user_id', 'api_name', 'id'], 'idx_user_api_latest');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('everee_transections_log', function (Blueprint $table) {
            // Drop the composite index
            $table->dropIndex('idx_user_api_latest');
        });
    }
};
