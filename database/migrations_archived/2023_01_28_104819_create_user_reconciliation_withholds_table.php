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
        Schema::create('user_reconciliation_withholds', function (Blueprint $table) {
            $table->id();
            $table->string('pid')->nullable();
            $table->unsignedBigInteger('closer_id')->nullable();
            $table->unsignedBigInteger('setter_id')->nullable();
            $table->string('payroll_id')->nullable();
            $table->string('adjustment_amount')->nullable();
            $table->string('withhold_amount')->nullable();
            // $table->string('adjustment_amount')->nullable();
            $table->string('comment')->nullable();
            $table->string('status')->nullable()->default('unpaid');
            // $table->string('comment')->nullable();
            $table->string('finalize_status')->nullable();
            $table->string('payroll_to_recon_status')->nullable();
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
        Schema::dropIfExists('user_reconciliation_withholds');
    }
};
