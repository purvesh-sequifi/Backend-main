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
        Schema::table('clawback_settlements', function (Blueprint $table) {
            $table->bigInteger('product_id')->after('sale_user_id')->nullable();
            $table->string('product_code')->after('pid')->nullable();
        });

        Schema::table('clawback_settlements_lock', function (Blueprint $table) {
            $table->bigInteger('product_id')->after('sale_user_id')->nullable();
            $table->string('product_code')->after('pid')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clawback_settlements', function (Blueprint $table) {
            //
        });
    }
};
