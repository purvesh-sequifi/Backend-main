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
            if (! Schema::hasColumn('tiers_position_overrides', 'override_type')) {
                $table->string('override_type')->nullable()->after('override_value');
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
