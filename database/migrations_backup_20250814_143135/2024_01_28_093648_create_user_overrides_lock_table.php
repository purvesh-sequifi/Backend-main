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
        Schema::create('user_overrides_lock', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->integer('payroll_id')->nullable()->default(0)->comment('payroll table id');
            $table->Integer('user_id')->nullable();
            $table->string('type')->nullable();
            $table->Integer('sale_user_id')->nullable();
            $table->string('pid')->nullable();
            $table->float('net_epc')->nullable();
            $table->string('kw')->nullable();
            $table->string('amount')->nullable();
            $table->string('overrides_amount')->nullable();
            $table->string('adjustment_amount')->nullable();
            $table->string('comment')->nullable();
            $table->enum('overrides_type', ['per sale', 'per kw'])->nullable();
            $table->string('calculated_redline')->nullable();
            $table->enum('overrides_settlement_type', ['reconciliation', 'during_m2'])->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('is_mark_paid')->nullable();
            $table->tinyInteger('is_next_payroll')->nullable();
            $table->tinyInteger('is_stop_payroll')->nullable();
            $table->Integer('office_id')->nullable();
            $table->integer('status')->nullable();
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
        Schema::dropIfExists('user_overrides_lock');
    }
};
