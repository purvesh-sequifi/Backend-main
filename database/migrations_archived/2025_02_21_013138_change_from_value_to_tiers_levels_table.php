<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (! Schema::hasColumn('tiers_levels', 'from_value')) {
                $table->string('from_value')->nullable()->after('level');
            }
            if (! Schema::hasColumn('tiers_levels', 'to_value')) {
                $table->string('to_value')->nullable()->after('from_value');
            }
        });
        DB::statement('ALTER TABLE `tiers_levels` CHANGE `to_value` `to_value` VARCHAR(255) NULL DEFAULT NULL;');
        DB::statement('ALTER TABLE `tiers_levels` CHANGE `from_value` `from_value` VARCHAR(255) NULL DEFAULT NULL;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('value_to_tiers_levels', function (Blueprint $table) {
            //
        });
    }
};
