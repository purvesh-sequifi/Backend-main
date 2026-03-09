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
        Schema::create('one_time_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('req_id')->nullable();
            $table->unsignedBigInteger('pay_by')->nullable();
            $table->string('req_no')->nullable();
            $table->string('everee_external_id')->nullable();
            $table->string('everee_payment_req_id')->nullable();
            $table->unsignedBigInteger('adjustment_type_id')->nullable();
            $table->double('amount')->nullable();
            $table->text('description')->nullable();
            $table->date('pay_date')->nullable();
            $table->integer('everee_status')->default(0)->comment('0-disabled 1-enabled');
            $table->integer('payment_status')->default(0)->nullable();
            $table->text('everee_json_response')->nullable();
            $table->text('everee_webhook_response')->nullable();
            $table->integer('everee_payment_status')->default(0)->comment('0-unpaid 1-paid');
            $table->string('everee_paymentId', 200)->nullable();
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
        Schema::dropIfExists('one_time_payments');
    }
};
