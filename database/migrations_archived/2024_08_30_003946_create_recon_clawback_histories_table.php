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
        Schema::create('recon_clawback_histories', function (Blueprint $table) {
            $table->id();
            $table->string('pid', 200)->nullable();
            $table->string('user_id', 200)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 200)->nullable();
            $table->string('type', 200)->nullable();
            $table->string('move_from_payroll', 200)->nullable();
            $table->string('sent_count', 200)->nullable();
            $table->string('finalize_count', 200)->nullable();
            $table->string('total_amount', 200)->nullable();
            $table->string('paid_amount', 200)->nullable();
            $table->string('payout', 200)->nullable();
            $table->string('payroll_execute_status', 200)->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('payroll_id')->nullable();
            $table->string('ref_id')->nullable();
            $table->tinyInteger('is_next_payroll')->default(0)->nullable();
            $table->tinyInteger('is_mark_paid')->default(0)->nullable();
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
        Schema::dropIfExists('recon_clawback_histories');
    }
};
