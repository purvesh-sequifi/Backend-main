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
        Schema::table('user_upfront_history_tiers_ranges', function (Blueprint $table) {
            if (! Schema::hasColumn('user_upfront_history_tiers_ranges', 'tiers_schema_id')) {
                $table->unsignedBigInteger('tiers_schema_id')->nullable()->after('user_upfront_history_id');
            }
            if (! Schema::hasColumn('user_upfront_history_tiers_ranges', 'value_type')) {
                $table->string('value_type', 255)->nullable()->after('value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_upfront_history_tiers_ranges', function (Blueprint $table) {
            if (Schema::hasColumn('user_upfront_history_tiers_ranges', 'tiers_schema_id')) {
                $table->dropColumn('tiers_schema_id');
            }
            if (Schema::hasColumn('user_upfront_history_tiers_ranges', 'value_type')) {
                $table->dropColumn('value_type');
            }
        });
    }
};
