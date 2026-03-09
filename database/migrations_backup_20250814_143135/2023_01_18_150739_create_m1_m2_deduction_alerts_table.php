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
        Schema::create('deduction_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable(); // PID
            $table->Integer('user_id')->nullable();
            $table->Integer('position_id')->nullable();
            $table->double('amount', 11, 3)->nullable();
            $table->string('status')->nullable();
            $table->tinyInteger('action_status')->default('0');

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
        Schema::dropIfExists('deduction_alerts');
    }
};
