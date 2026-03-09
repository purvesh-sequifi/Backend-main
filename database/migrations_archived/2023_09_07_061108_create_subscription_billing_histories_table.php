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
        Schema::create('subscription_billing_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('subscription_id')->default(0);
            $table->double('amount')->default(0);
            $table->integer('paid_status')->default(0);
            $table->string('invoice_no')->nullable();
            $table->dateTime('billing_date')->nullable();
            $table->integer('plan_id')->nullable();
            $table->string('plan_name')->nullable();
            $table->string('unique_pid_rack_price')->nullable();
            $table->string('unique_pid_discount_price')->nullable();
            $table->string('m2_rack_price')->nullable();
            $table->string('m2_discount_price')->nullable();
            $table->integer('billing_id')->nullable();
            $table->text('client_secret')->nullable();
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
        Schema::dropIfExists('subscription_billing_histories');
    }
};
