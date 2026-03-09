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
        Schema::create('tiers_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tier_setting_id');
            $table->integer('is_check')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->foreign('tier_setting_id')->references('id')
                ->on('tier_settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tier_level_names');
    }
};
