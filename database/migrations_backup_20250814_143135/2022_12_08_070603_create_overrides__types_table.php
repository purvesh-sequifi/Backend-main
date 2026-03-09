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
        Schema::create('overrides__types', function (Blueprint $table) {
            $table->id();
            $table->string('overrides_type')->nullable();
            $table->string('lock_pay_out_type')->nullable();
            $table->float('max_limit')->nullable();
            $table->float('parsonnel_limit')->nullable();
            $table->string('min_position')->nullable();
            $table->integer('level')->nullable();
            $table->unsignedBigInteger('override_setting_id');
            $table->integer('is_check')->nullable();
            $table->timestamps();

            // $table->foreign('override_setting_id')->references('id')->on('override__settings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('overrides__types');
    }
};
