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
        Schema::create('position_commission_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('override_id');
            $table->unsignedBigInteger('settlement_id');
            $table->float('override_ammount')->nullable();
            $table->integer('override_ammount_locked')->nullable();
            $table->text('type')->nullable();
            $table->integer('override_type_locked')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();

            $table->foreign('position_id')->references('id')
                ->on('positions')->onDelete('cascade');
            $table->foreign('override_id')->references('id')
                ->on('overrides__types')->onDelete('cascade');
            // $table->foreign('settlement_id')->references('id')->on('position_override_settlements')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('compenstion_plan_overrides');
    }
};
