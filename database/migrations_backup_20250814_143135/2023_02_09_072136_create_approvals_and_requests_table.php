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
        Schema::create('approvals_and_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('payroll_id')->nullable()->default(0)->comment('payroll table id');
            $table->string('req_no')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('adjustment_type_id')->nullable();
            $table->string('pay_period')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('dispute_type')->nullable();
            $table->string('customer_pid')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cost_tracking_id')->nullable();
            $table->string('emi')->nullable();
            $table->date('cost_date')->nullable();
            $table->string('txn_id')->nullable();
            $table->date('request_date')->nullable();
            $table->string('amount')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->nullable();
            $table->tinyInteger('is_mark_paid')->default('0');
            $table->tinyInteger('is_next_payroll')->default('0');
            $table->integer('ref_id')->nullable()->default('0');
            $table->string('declined_at')->nullable();
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
        Schema::dropIfExists('approvals_and_requests');
    }
};
