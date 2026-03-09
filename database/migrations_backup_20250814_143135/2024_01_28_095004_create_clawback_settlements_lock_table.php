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
        Schema::create('clawback_settlements_lock', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->integer('payroll_id')->nullable()->default(0)->comment('payroll table id');
            $table->unsignedBigInteger('user_id');
            $table->integer('position_id');
            $table->integer('sale_user_id')->nullable();
            $table->double('clawback_amount', 8, 2)->nullable();
            $table->enum('clawback_type', ['reconciliation', 'next payroll', 'm2 update'])->nullable();
            $table->string('pid')->nullable();
            $table->tinyInteger('status')->default('1');
            $table->tinyInteger('action_status')->default('0');
            $table->string('type', 100)->default('commission');
            $table->string('adders_type')->nullable();
            $table->tinyInteger('is_mark_paid')->default('0');
            $table->tinyInteger('is_next_payroll')->default('0');
            $table->tinyInteger('is_stop_payroll')->default('0');
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
        Schema::dropIfExists('clawback_settlements_lock');
    }
};
