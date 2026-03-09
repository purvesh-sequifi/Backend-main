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
        Schema::create('sale_product_master', function (Blueprint $table) {
            $table->id();
            $table->string('pid');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('milestone_id');
            $table->unsignedBigInteger('milestone_schema_id');
            $table->date('milestone_date')->nullable();
            $table->tinyInteger('is_last_date')->default(0)->comment('Default 0, 1 = When last date hits')->nullable();
            $table->unsignedBigInteger('setter1_id')->nullable();
            $table->unsignedBigInteger('setter2_id')->nullable();
            $table->unsignedBigInteger('closer1_id')->nullable();
            $table->unsignedBigInteger('closer2_id')->nullable();
            $table->double('amount', 11, 2)->default(0);
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
        Schema::dropIfExists('sale_product_master');
    }
};
