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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('position_id')->nullable();
            $table->string('everee_external_id')->nullable();
            $table->float('commission')->nullable();
            $table->float('override')->nullable();
            $table->float('reimbursement')->nullable();
            $table->float('clawback')->nullable();
            $table->float('deduction')->nullable();
            $table->float('adjustment')->nullable();
            $table->float('reconciliation')->nullable();
            $table->string('net_pay')->nullable();
            $table->text('comment')->nullable();
            $table->text('pay_period_from')->nullable();
            $table->text('pay_period_to')->nullable();
            $table->integer('status')->nullable();
            $table->tinyInteger('is_mark_paid')->default(0)->comment('0 for no, 1 for mark as paid');
            $table->tinyInteger('is_next_payroll')->default(0);
            $table->tinyInteger('is_stop_payroll')->default(0);
            $table->integer('finalize_status')->default(0)->comment('1 = finalising , 2 = finaliized , 3 = user-not-on-third-party');
            $table->string('everee_message')->nullable();
            $table->longText('deduction_details')->nullable();
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
        Schema::dropIfExists('payrolls');
    }
};
