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
        Schema::create('payroll_deduction_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('limit', 10, 2)->nullable();
            $table->decimal('total', 10, 2)->nullable();
            $table->decimal('outstanding', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('status')->nullable()->default(1);
            $table->tinyInteger('is_mark_paid')->nullable()->default(0);
            $table->tinyInteger('is_next_payroll')->nullable()->default(0);
            $table->integer('ref_id')->nullable()->default(0);
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
        Schema::dropIfExists('payroll_deduction_locks');
    }
};
