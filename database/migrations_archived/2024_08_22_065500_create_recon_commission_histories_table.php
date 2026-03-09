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
        Schema::create('recon_commission_histories', function (Blueprint $table) {
            $table->id();
            $table->string('pid', 200);
            $table->string('user_id', 200);
            $table->string('status', 200);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('sent_count')->nullable()->comment('send to payroll count');
            $table->string('finalize_count')->nullable()->comment('finalize data count');
            $table->string('total_amount')->nullable();
            $table->string('paid_amount')->nullable();
            $table->string('payout')->nullable();
            $table->string('payroll_execute_status')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->integer('payroll_id')->nullable()->default(0)->comment('payroll table id');
            $table->tinyInteger('is_next_payroll')->nullable();
            $table->tinyInteger('is_mark_paid')->nullable();
            $table->tinyInteger('is_displayed')->default(1)->nullable();
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
        Schema::dropIfExists('recon_commission_histories');
    }
};
