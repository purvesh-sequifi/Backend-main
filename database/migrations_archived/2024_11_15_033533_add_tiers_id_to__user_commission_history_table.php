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
        Schema::table('user_commission_history', function (Blueprint $table) {
            $table->unsignedBigInteger('tiers_id')->after('action_item_status')->nullable();
            $table->unsignedBigInteger('old_tiers_id')->after('tiers_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_commission_history', function (Blueprint $table) {
            //
        });
    }
};
