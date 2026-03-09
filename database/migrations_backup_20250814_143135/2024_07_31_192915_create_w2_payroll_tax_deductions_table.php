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
        Schema::create('w2_payroll_tax_deductions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('payroll_id');
            $table->bigInteger('user_id');
            $table->double('fica_tax', 8, 2)->default('0')->nullable();
            $table->double('medicare_withholding', 8, 2)->default('0')->nullable();
            $table->double('social_security_withholding', 8, 2)->default('0')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('payment_id')->nullable();
            $table->text('response')->nullable();
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
        Schema::dropIfExists('w2_payroll_tax_deductions');
    }
};
