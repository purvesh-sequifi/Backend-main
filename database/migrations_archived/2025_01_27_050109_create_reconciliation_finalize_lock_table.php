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
        Schema::create('reconciliation_finalize_lock', function (Blueprint $table) {
            $table->id();
            $table->tinyText('office_id')->nullable();
            $table->tinyText('position_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('executed_on')->nullable();
            $table->double('commissions')->default(0);
            $table->double('overrides')->default(0);
            $table->double('total_due')->default(0);
            $table->double('clawbacks')->default(0);
            $table->double('adjustments')->default(0);
            $table->double('deductions')->default(0);
            $table->double('remaining')->default(0);
            $table->integer('payout_percentage');
            $table->double('net_amount')->default(0);
            $table->string('status')->default('finalize')->comment('finalize, payroll');
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
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
        Schema::dropIfExists('reconciliation_finalize_lock');
    }
};
