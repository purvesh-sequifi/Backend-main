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
        Schema::create('emp_payroll_processing', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('commission')->nullable();
            $table->string('overrides')->nullable();
            $table->string('reimbursement')->nullable();
            $table->string('deductions')->nullable();
            $table->string('reconciliation')->nullable();
            $table->string('adjustment')->nullable();
            $table->string('net_pay')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')
                ->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payroll_processing');
    }
};
