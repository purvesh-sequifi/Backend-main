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
        Schema::create('reconciliation_finalize_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('pid')->nullable();
            $table->string('office_id');
            $table->string('position_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('executed_on')->nullable();
            // $table->double('office_overrides_amount', 8, 2)->nullable();
            $table->double('commission', 8, 2)->nullable();
            $table->double('override', 8, 2)->nullable();
            $table->string('paid_commission')->nullable();
            $table->string('paid_override')->nullable();
            $table->double('clawback', 8, 2)->nullable();
            $table->double('adjustments', 8, 2)->nullable();
            $table->double('gross_amount', 8, 2)->nullable();
            $table->string('payout')->nullable();
            $table->string('net_amount')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->bigInteger('payroll_id')->nullable();
            $table->bigInteger('sent_count')->default(0);
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
        Schema::dropIfExists('reconciliationfinalize_history');
    }
};
