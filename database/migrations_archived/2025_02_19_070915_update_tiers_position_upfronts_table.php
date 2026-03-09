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
        Schema::table('tiers_position_upfronts', function (Blueprint $table) {
            if (Schema::hasColumn('tiers_position_upfronts', 'to_dealer_fee') && ! Schema::hasColumn('tiers_position_upfronts', 'to_value')) {
                $table->dropColumn('to_dealer_fee');
            }

            if (Schema::hasColumn('tiers_position_upfronts', 'to_value') && ! Schema::hasColumn('tiers_position_upfronts', 'to_dealer_fee')) {
                $table->dropColumn('to_value');
            }

            if (Schema::hasColumn('tiers_position_upfronts', 'from_dealer_fee') && ! Schema::hasColumn('tiers_position_upfronts', 'from_value')) {
                $table->dropColumn('from_dealer_fee');
            }

            if (Schema::hasColumn('tiers_position_upfronts', 'from_value') && ! Schema::hasColumn('tiers_position_upfronts', 'from_dealer_fee')) {
                $table->dropColumn('from_value');
            }

            if (! Schema::hasColumn('tiers_position_upfronts', 'upfront_type')) {
                $table->string('upfront_type', 255)->nullable()->after('upfront_value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiers_position_upfronts', function (Blueprint $table) {
            // Reverse renaming only if necessary
            if (Schema::hasColumn('tiers_position_upfronts', 'to_value') && ! Schema::hasColumn('tiers_position_upfronts', 'to_dealer_fee')) {
                $table->renameColumn('to_value', 'to_dealer_fee');
            }

            if (Schema::hasColumn('tiers_position_upfronts', 'from_value') && ! Schema::hasColumn('tiers_position_upfronts', 'from_dealer_fee')) {
                $table->renameColumn('from_value', 'from_dealer_fee');
            }

            // Drop the added column only if it exists
            if (Schema::hasColumn('tiers_position_upfronts', 'upfront_type')) {
                $table->dropColumn('upfront_type');
            }
        });
    }
};
