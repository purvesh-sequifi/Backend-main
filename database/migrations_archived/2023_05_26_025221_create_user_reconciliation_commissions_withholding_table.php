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
        Schema::create('user_reconciliation_commissions_withholding', function (Blueprint $table) {
            $table->id();
            $table->Integer('user_id')->nullable();
            $table->string('pid')->nullable();
            // $table->string('amount')->nullable();
            $table->double('amount', 8, 2)->default('0');
            $table->double('overrides', 8, 2)->default('0');
            $table->double('clawbacks', 8, 2)->default('0');
            $table->double('total_due', 8, 2)->default('0');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->integer('payroll_id')->default(0);
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
        Schema::dropIfExists('user_reconciliation_commissions_withholding');
    }
};
