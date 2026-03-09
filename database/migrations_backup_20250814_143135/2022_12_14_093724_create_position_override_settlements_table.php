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
        Schema::create('position_override_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('override_id');
            $table->enum('sattlement_type', ['Reconciliation', 'During M2'])->default('Reconciliation');
            $table->timestamps();

            // $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            // $table->foreign('override_id')->references('id')->on('overrides__types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('position_override_settlements');
    }
};
