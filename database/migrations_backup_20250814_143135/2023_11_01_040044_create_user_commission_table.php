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
        Schema::create('user_commission', function (Blueprint $table) {
            $table->id();
            $table->integer('payroll_id')->nullable()->default(0)->comment('payroll table id');
            $table->integer('user_id')->nullable();
            $table->integer('position_id')->nullable();
            $table->string('pid')->nullable();
            $table->enum('amount_type', ['m1', 'm2', 'm2 update'])->nullable();
            $table->double('amount', 11, 2)->default('0');
            $table->string('redline', 100)->nullable();
            $table->enum('redline_type', ['Fixed', 'Shift based on Location'])->nullable();
            $table->float('net_epc')->nullable();
            $table->string('kw')->nullable();
            $table->date('date')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->date('customer_signoff')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_mark_paid')->default(0);
            $table->tinyInteger('is_next_payroll')->default(0);
            $table->tinyInteger('is_stop_payroll')->default(0);
            $table->integer('ref_id')->nullable()->default('0');
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
        Schema::dropIfExists('user_commission');
    }
};
