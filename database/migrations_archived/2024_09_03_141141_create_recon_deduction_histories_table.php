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
        Schema::create('recon_deduction_histories', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_id', 200)->nullable();
            $table->string('user_id', 200)->nullable();
            $table->string('cost_center_id', 200)->nullable();
            $table->string('amount', 200)->nullable();
            $table->string('limit', 200)->nullable();
            $table->string('total', 200)->nullable();
            $table->string('outstanding', 200)->nullable();
            $table->string('subtotal', 200)->nullable();
            $table->date('start_date', 200)->nullable();
            $table->date('end_date', 200)->nullable();
            $table->string('status')->nullable();
            $table->string('finalize_count', 200)->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('payroll_executed_status')->nullable();
            $table->tinyInteger('is_mark_paid')->nullable();
            $table->tinyInteger('is_next_payroll')->nullable();
            $table->tinyInteger('is_stop_payroll')->nullable();
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
        Schema::dropIfExists('recon_deduction_histories');
    }
};
