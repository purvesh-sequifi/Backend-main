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
        Schema::create('sale_tiers_master', function (Blueprint $table) {
            $table->id();
            $table->string('pid');
            $table->unsignedBigInteger('tier_schema_id')->nullable();
            $table->unsignedBigInteger('tier_schema_level_id')->nullable();
            $table->unsignedBigInteger('setter1_id')->nullable();
            $table->unsignedBigInteger('setter2_id')->nullable();
            $table->unsignedBigInteger('closer1_id')->nullable();
            $table->unsignedBigInteger('closer2_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sale_product_master', function (Blueprint $table) {
            //
        });
    }
};
