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
        Schema::table('position_commission_overrides', function (Blueprint $table) {
            $table->unsignedBigInteger('tiers_id')->after('product_id')->nullable();
            $table->tinyInteger('tiers_hiring_locked')->after('override_type_locked')->default(0);
            $table->string('tiers_advancement')->after('tiers_hiring_locked')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position_commission_overrides', function (Blueprint $table) {
            //
        });
    }
};
