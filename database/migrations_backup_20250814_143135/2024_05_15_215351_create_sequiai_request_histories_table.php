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
        Schema::create('sequiai_request_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('subscription_billing_history_id')->nullable();
            $table->text('user_prompt_type');
            $table->text('user_prompt');
            // $table->text('response');
            $table->tinyInteger('status')->default(0)->comment('0=>Not get in billing, 1=>Billing done');
            $table->integer('sequiai_plan_id')->nullable();
            $table->date('billing_date')->nullable();
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
        Schema::dropIfExists('sequiai_request_histories');
    }
};
