<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTiersPositionOverridesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tiers_position_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('tiers_position_overrides', 'override_type')) {
                $table->string('override_type', 255)->change();
            }
            if (Schema::hasColumn('tiers_position_overrides', 'to_dealer_fee')) {
                $table->dropColumn('to_dealer_fee');
            }
            if (Schema::hasColumn('tiers_position_overrides', 'from_dealer_fee')) {
                $table->dropColumn('from_dealer_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tiers_position_overrides', function (Blueprint $table) {
            $table->decimal('to_dealer_fee', 15, 2)->nullable();
            $table->decimal('from_dealer_fee', 15, 2)->nullable();
            $table->string('override_type', 100)->change();
        });
    }
}
