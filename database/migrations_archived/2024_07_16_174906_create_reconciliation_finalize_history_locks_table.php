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
        Schema::create('reconciliation_finalize_history_locks', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->string('pid')->nullable();
            $table->string('office_id');
            $table->string('position_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('executed_on')->nullable();
            $table->double('commission', 8, 2)->nullable();
            $table->double('override', 8, 2)->nullable();
            $table->string('paid_commission')->nullable();
            $table->string('paid_override')->nullable();
            $table->double('clawback', 8, 2)->nullable();
            $table->double('adjustments', 8, 2)->nullable();
            $table->double('deductions', 8, 2)->nullable();
            $table->double('gross_amount', 8, 2)->nullable();
            $table->string('payout')->nullable();
            $table->string('net_amount')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->bigInteger('payroll_id')->nullable();
            $table->bigInteger('sent_count')->default(0);
            $table->boolean('is_mark_paid')->default(false);
            $table->boolean('is_next_payroll')->default(false);
            $table->boolean('is_stop_payroll')->default(false);
            $table->bigInteger('ref_id')->nullable();
            $table->bigInteger('move_from_payroll_row_id')->nullable();
            $table->boolean('move_from_payroll_flag')->default(false);
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
        Schema::dropIfExists('reconciliation_finalize_history_locks');
    }
};
