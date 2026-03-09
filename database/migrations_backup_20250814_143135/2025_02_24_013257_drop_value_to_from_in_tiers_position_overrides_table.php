<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tiers_position_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('tiers_position_overrides', 'tiers_advancement')) {
                $table->dropColumn('tiers_advancement');
            }

            if (Schema::hasColumn('tiers_position_overrides', 'to_value')) {
                $table->dropColumn('to_value');
            }

            if (Schema::hasColumn('tiers_position_overrides', 'from_value')) {
                $table->dropColumn('from_value');
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
            //
        });
    }
};
