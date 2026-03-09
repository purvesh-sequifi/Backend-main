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
        Schema::create('payroll_shift_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('payroll_id')->nullable();
            $table->integer('moved_by')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->date('new_pay_period_from')->nullable();
            $table->date('new_pay_period_to')->nullable();
            $table->tinyInteger('is_undo_done')->nullable()->default(1)->comment('check undo step');
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
        Schema::dropIfExists('payroll_shift_histrories');
    }
};
