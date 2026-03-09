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
        Schema::create('position_commission_upfronts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('status_id');
            $table->integer('upfront_status')->default('1');
            $table->float('upfront_ammount')->nullable();
            $table->integer('upfront_ammount_locked')->nullable();
            $table->enum('calculated_by', ['per kw', 'per sale'])->default('per kw');
            $table->integer('calculated_locked')->nullable();
            $table->enum('upfront_system', ['Tiered', 'Fixed'])->default('Tiered');
            $table->integer('upfront_system_locked')->nullable();
            $table->float('upfront_limit')->nullable();
            $table->timestamps();

            // $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            // $table->foreign('status_id')->references('id')->on('position_upfront_settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('compenstion_plan_upfronts');
    }
};
