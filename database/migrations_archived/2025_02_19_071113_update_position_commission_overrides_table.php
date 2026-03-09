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
        Schema::table('position_commission_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('position_commission_overrides', 'tiers_advancement')) {
                $table->dropColumn('tiers_advancement');
            }
            if (! Schema::hasColumn('position_commission_overrides', 'override_limit')) {
                $table->string('override_limit', 255)->nullable()->after('tiers_hiring_locked');
            }
            if (! Schema::hasColumn('position_commission_overrides', 'effective_date')) {
                $table->date('effective_date')->nullable()->after('override_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_commission_overrides', function (Blueprint $table) {
            if (! Schema::hasColumn('position_commission_overrides', 'tiers_advancement')) {
                $table->string('tiers_advancement', 255)->nullable();
            }
            if (Schema::hasColumn('position_commission_overrides', 'override_limit')) {
                $table->dropColumn('override_limit');
            }
            if (Schema::hasColumn('position_commission_overrides', 'effective_date')) {
                $table->dropColumn('effective_date');
            }
        });
    }
};
