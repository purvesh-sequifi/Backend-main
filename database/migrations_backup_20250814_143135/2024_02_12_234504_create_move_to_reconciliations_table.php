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
        Schema::create('move_to_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id')->nullable()->comment('payroll table id');
            $table->unsignedBigInteger('user_id')->nullable()->comment('user id');
            $table->string('pid')->nullable();
            $table->string('commission')->default('0');
            $table->string('override')->default('0');
            $table->string('clawback')->default('0');
            $table->string('status', 15)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
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
        Schema::dropIfExists('move_to_reconciliations');
    }
};
