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
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->Integer('payroll_id')->nullable();
            $table->Integer('user_id')->nullable();
            $table->string('commission_type')->default('commission');
            $table->double('commission_amount', 6, 2)->nullable();
            $table->string('overrides_type')->default('overrides');
            $table->double('overrides_amount', 6, 2)->nullable();
            $table->string('adjustments_type')->default('adjustments');
            $table->double('adjustments_amount', 6, 2)->nullable();
            $table->string('reimbursements_type')->default('reimbursements');
            $table->double('reimbursements_amount', 6, 2)->nullable();
            $table->string('deductions_type')->default('deductions');
            $table->double('deductions_amount', 6, 2)->nullable();
            $table->string('reconciliations_type')->default('reconciliations');
            $table->double('reconciliations_amount', 6, 2)->nullable();
            $table->string('clawbacks_type')->default('clawbacks');
            $table->double('clawbacks_amount', 6, 2)->nullable();
            $table->string('amount')->nullable();
            $table->text('comment')->nullable();
            $table->text('pay_period_from')->nullable();
            $table->text('pay_period_to')->nullable();
            $table->tinyInteger('is_mark_paid')->default(0)->comment('0 for no, 1 for mark as paid');
            $table->tinyInteger('is_next_payroll')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->integer('ref_id')->nullable()->default('0');
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
        Schema::dropIfExists('payroll_adjustments');
    }
};
