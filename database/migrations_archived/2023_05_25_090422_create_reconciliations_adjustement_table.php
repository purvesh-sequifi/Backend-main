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
        Schema::create('reconciliations_adjustement', function (Blueprint $table) {
            $table->id();
            $table->Integer('user_id')->nullable();
            $table->Integer('reconciliation_id')->nullable();
            $table->Integer('payroll_id')->nullable();
            $table->Integer('sent_count')->nullable();
            $table->string('pid')->nullable();
            $table->string('adjustment_type')->default('reconciliations')->nullable();
            $table->string('override_type')->nullable();
            $table->string('payroll_move_status')->nullable();
            $table->float('commission_due')->default('0')->nullable();
            $table->float('overrides_due')->default('0')->nullable();
            $table->float('reimbursement')->default('0')->nullable();
            $table->float('deduction')->default('0')->nullable();
            $table->float('adjustment')->default('0')->nullable();
            $table->float('reconciliation')->default('0')->nullable();
            $table->float('clawback_due')->default('0')->nullable();
            $table->string('payroll_status')->nullable();
            $table->string('start_date')->nullable();
            $table->string('end_date')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->text('comment')->nullable();
            // $table->string('type')->nullable();
            $table->timestamps();
            $table->string('type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reconciliations_adjustement');
    }
};
