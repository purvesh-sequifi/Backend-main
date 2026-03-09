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
        Schema::table('onboarding_commission_tiers_level_range', function (Blueprint $table) {
            if (! Schema::hasColumn('onboarding_commission_tiers_level_range', 'tiers_schema_id')) {
                $table->unsignedBigInteger('tiers_schema_id')->nullable()->after('onboarding_commission_id');
            }
            if (! Schema::hasColumn('onboarding_commission_tiers_level_range', 'value_type')) {
                $table->string('value_type', 255)->nullable()->after('value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_commission_tiers_level_range', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_commission_tiers_level_range', 'tiers_schema_id')) {
                $table->dropColumn('tiers_schema_id');
            }
            if (Schema::hasColumn('onboarding_commission_tiers_level_range', 'value_type')) {
                $table->dropColumn('value_type');
            }
        });
    }
};
