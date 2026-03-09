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
        Schema::create('payroll_adjustment_details_lock', function (Blueprint $table) {
            $table->integer('id')->nullable();
            $table->integer('payroll_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('pid')->nullable();
            $table->string('payroll_type')->nullable();
            $table->string('type')->nullable();
            $table->string('amount')->default(0);
            $table->text('comment')->nullable();
            $table->integer('cost_center_id')->nullable();
            $table->text('pay_period_from')->nullable();
            $table->text('pay_period_to')->nullable();
            $table->tinyInteger('is_mark_paid')->default(0)->comment('0 for no, 1 for mark as paid');
            $table->tinyInteger('is_next_payroll')->default(0);
            $table->tinyInteger('status')->default(1);
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
        Schema::dropIfExists('payroll_adjustment_details_lock');
    }
};
