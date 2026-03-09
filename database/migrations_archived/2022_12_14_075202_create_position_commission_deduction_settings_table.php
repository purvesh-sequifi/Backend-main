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
        Schema::create('position_commission_deduction_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('position_id')->nullable();
            $table->integer('status')->nullable();
            $table->integer('deducation_locked')->nullable();
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
        Schema::dropIfExists('compensation_plan_deduction_settings');
    }
};
