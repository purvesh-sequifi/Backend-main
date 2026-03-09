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
        Schema::create('add_on_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('rack_price');
            $table->string('rack_price_type', 100);
            $table->string('discount_type', 100);
            $table->string('discount_price', 100);
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
        Schema::dropIfExists('add_on_plans');
    }
};
