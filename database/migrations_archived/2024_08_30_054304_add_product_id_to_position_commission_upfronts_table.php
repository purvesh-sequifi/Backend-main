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
        Schema::table('position_commission_upfronts', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('position_id');
            $table->unsignedBigInteger('milestone_schema_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('milestone_schema_trigger_id')->nullable()->after('milestone_schema_id');
            $table->tinyInteger('self_gen_user')->comment('0 = Not SelfGen, 1 = SelfGen')->default('0')->after('milestone_schema_trigger_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('position_commission_upfronts', function (Blueprint $table) {
            //
        });
    }
};
