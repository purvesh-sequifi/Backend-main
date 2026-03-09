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
        Schema::create('tiers_configure', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tier_type_id');
            $table->float('installs_to')->nullable();
            $table->float('redline_shift')->nullable();
            $table->float('installs_from')->nullable();
            $table->timestamps();

            $table->foreign('tier_type_id')->references('id')
                ->on('tiers_type')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configure_tiers');
    }
};
