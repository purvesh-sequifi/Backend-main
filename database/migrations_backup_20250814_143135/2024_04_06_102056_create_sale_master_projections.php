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
        Schema::create('sale_master_projections', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable();
            $table->unsignedBigInteger('closer1_id')->nullable();
            $table->unsignedBigInteger('closer2_id')->nullable();
            $table->unsignedBigInteger('setter1_id')->nullable();
            $table->unsignedBigInteger('setter2_id')->nullable();
            $table->string('closer1_m1')->default('0');
            $table->string('closer2_m1')->default('0');
            $table->string('setter1_m1')->default('0');
            $table->string('setter2_m1')->default('0');
            $table->string('closer1_m2')->default('0');
            $table->string('closer2_m2')->default('0');
            $table->string('setter1_m2')->default('0');
            $table->string('setter2_m2')->default('0');
            $table->string('closer1_commission')->default('0');
            $table->string('closer2_commission')->default('0');
            $table->string('setter1_commission')->default('0');
            $table->string('setter2_commission')->default('0');
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
        Schema::dropIfExists('sale_master_projections');
    }
};
