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
        Schema::create('payroll_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('position_id')->nullable();
            $table->integer('everee_status')->default(0);
            $table->string('everee_external_id')->nullable();
            $table->bigInteger('everee_payment_requestId')->nullable();
            $table->string('everee_paymentId', 200)->nullable();
            $table->float('commission')->nullable();
            $table->float('override')->nullable();
            $table->float('reimbursement')->nullable();
            $table->float('clawback')->nullable();
            $table->float('deduction')->nullable();
            $table->float('adjustment')->nullable();
            $table->float('reconciliation')->nullable();
            $table->string('net_pay')->nullable();
            $table->date('pay_frequency_date')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->text('comment')->nullable();
            $table->integer('status')->nullable();
            $table->text('everee_json_response')->nullable();
            $table->integer('everee_payment_status')->nullable();
            $table->text('everee_webhook_json')->nullable();
            $table->string('pay_type')->nullable();
            $table->timestamps();
            $table->index(['payroll_id', 'user_id', 'position_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_history');
    }
};
