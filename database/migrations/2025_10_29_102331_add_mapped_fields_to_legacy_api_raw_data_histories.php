<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add mapped_fields JSON column to store which fields were mapped in the import template.
     * This allows us to distinguish between:
     * - Fields that were mapped and should be updated (even if null)
     * - Fields that were not mapped and should not be touched
     */
    public function up(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->json('mapped_fields')->nullable()->after('template_id')
                ->comment('JSON array of field names that were mapped in the import template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legacy_api_raw_data_histories', function (Blueprint $table) {
            $table->dropColumn('mapped_fields');
        });
    }
};
