<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tiers_position_upfronts', function (Blueprint $table) {
            // Check if each column exists before dropping to prevent errors
            if (Schema::hasColumn('tiers_position_upfronts', 'tiers_advancement')) {
                $table->dropColumn('tiers_advancement');
            }
            if (Schema::hasColumn('tiers_position_upfronts', 'to_dealer_fee')) {
                $table->dropColumn('to_dealer_fee');
            }
            if (Schema::hasColumn('tiers_position_upfronts', 'from_dealer_fee')) {
                $table->dropColumn('from_dealer_fee');
            }

            if (Schema::hasColumn('tiers_position_upfronts', 'to_value') && ! Schema::hasColumn('tiers_position_upfronts', 'to_dealer_fee')) {
                $table->dropColumn('to_value');
            }

            if (Schema::hasColumn('tiers_position_upfronts', 'from_value') && ! Schema::hasColumn('tiers_position_upfronts', 'from_dealer_fee')) {
                $table->dropColumn('from_value');
            }
        });
    }

    public function down()
    {
        Schema::table('tiers_position_upfronts', function (Blueprint $table) {
            // Add the columns back if rolling back the migration
            if (! Schema::hasColumn('tiers_position_upfronts', 'tiers_advancement')) {
                $table->decimal('tiers_advancement', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('tiers_position_upfronts', 'to_dealer_fee')) {
                $table->decimal('to_dealer_fee', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('tiers_position_upfronts', 'from_dealer_fee')) {
                $table->decimal('from_dealer_fee', 10, 2)->nullable();
            }
        });
    }
};
