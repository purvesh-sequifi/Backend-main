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
        Schema::table('sale_masters', function (Blueprint $table) {
            $table->string('total_commission')->default('0')->after('action_item_status');
            $table->tinyInteger('projected_commission')->default(1)->comment('0 = Non Projected, 1 = Projected')->after('total_commission');
            $table->string('total_override')->default('0')->after('projected_commission');
            $table->tinyInteger('projected_override')->default(1)->comment('0 = Non Projected, 1 = Projected')->after('total_override');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_masters', function (Blueprint $table) {
            //
        });
    }
};
