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
        Schema::table('tiers_levels', function (Blueprint $table) {
            if (Schema::hasColumn('tiers_levels', 'to_dealer_fee')) {
                $table->renameColumn('to_dealer_fee', 'to_value');
            }
            if (Schema::hasColumn('tiers_levels', 'from_dealer_fee')) {
                $table->renameColumn('from_dealer_fee', 'from_value');
            }
            if (! Schema::hasColumn('tiers_levels', 'level')) {
                $table->string('level')->nullable()->after('tiers_schema_id');
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
        Schema::table('tiers_levels', function (Blueprint $table) {
            if (Schema::hasColumn('tiers_levels', 'to_value')) {
                $table->renameColumn('to_value', 'to_dealer_fee');
            }
            if (Schema::hasColumn('tiers_levels', 'from_value')) {
                $table->renameColumn('from_value', 'from_dealer_fee');
            }
        });
    }
};
