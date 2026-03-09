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
        Schema::table('tiers_position_commisions', function (Blueprint $table) {
            if (! Schema::hasColumn('tiers_position_commisions', 'tiers_advancement')) {
                $table->string('tiers_advancement', 255)->nullable()->after('commission_type');
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
        Schema::table('tiers_position_commisions', function (Blueprint $table) {
            //
        });
    }
};
