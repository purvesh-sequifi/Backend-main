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
        Schema::create('position_tier_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->integer('tier_status')->nullable();
            $table->enum('sliding_scale', ['Fixed', 'Tiered'])->nullable();
            $table->integer('sliding_scale_locked')->nullable();
            $table->enum('levels', ['Multiple', 'Single'])->nullable();
            $table->integer('level_locked')->nullable();
            $table->timestamps();

            $table->foreign('position_id')->references('id')
                ->on('positions')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('position_tier_overrides');
    }
};
