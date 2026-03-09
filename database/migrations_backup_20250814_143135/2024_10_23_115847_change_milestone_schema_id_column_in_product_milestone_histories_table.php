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
        Schema::table('product_milestone_histories', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->unsignedBigInteger('milestone_schema_id')->nullable()->change();
            $table->unsignedBigInteger('clawback_exempt_on_ms_trigger_id')->nullable()->change();
            $table->dropForeign('product_milestone_histories_product_id_foreign');
            $table->dropForeign('product_milestone_histories_milestone_schema_id_foreign');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_milestone_histories', function (Blueprint $table) {
            //
        });
    }
};
