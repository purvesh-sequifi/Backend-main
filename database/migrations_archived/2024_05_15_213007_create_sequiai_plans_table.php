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
        Schema::create('sequiai_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('price', 255);
            $table->string('min_request');
            $table->string('additional_price', 255);
            $table->string('additional_min_request', 255);
            $table->tinyInteger('status')->default(1);
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
        Schema::dropIfExists('sequiai_plans');
    }
};
