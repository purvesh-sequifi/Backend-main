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
        Schema::table('user_upfront_history', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('updater_id');
            $table->unsignedBigInteger('old_product_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('milestone_schema_id')->nullable()->after('old_product_id');
            $table->unsignedBigInteger('old_milestone_schema_id')->nullable()->after('milestone_schema_id');
            $table->unsignedBigInteger('milestone_schema_trigger_id')->nullable()->after('old_milestone_schema_id');
            $table->unsignedBigInteger('old_milestone_schema_trigger_id')->nullable()->after('milestone_schema_trigger_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_upfront_history', function (Blueprint $table) {
            //
        });
    }
};
