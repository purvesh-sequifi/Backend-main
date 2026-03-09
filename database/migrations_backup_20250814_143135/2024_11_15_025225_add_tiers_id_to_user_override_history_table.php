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
        Schema::table('user_override_history', function (Blueprint $table) {
            $table->unsignedBigInteger('direct_tiers_id')->after('office_stack_overrides_amount')->nullable();
            $table->unsignedBigInteger('old_direct_tiers_id')->after('direct_tiers_id')->nullable();
            $table->unsignedBigInteger('indirect_tiers_id')->after('old_direct_tiers_id')->nullable();
            $table->unsignedBigInteger('old_indirect_tiers_id')->after('indirect_tiers_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_override_history', function (Blueprint $table) {
            //
        });
    }
};
