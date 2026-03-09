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
        Schema::create('payroll_overtimes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('payroll_id');
            $table->bigInteger('user_id');
            $table->integer('position_id')->nullable();
            $table->date('date')->nullable();
            $table->double('overtime_rate', 8, 2)->default('0')->nullable();
            $table->string('overtime')->nullable();
            $table->double('total', 8, 2)->default('0')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_mark_paid')->default(0);
            $table->tinyInteger('is_next_payroll')->default(0);
            $table->tinyInteger('is_stop_payroll')->default(0);
            $table->tinyInteger('is_move_to_recon')->default(0)->nullable();
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
        Schema::dropIfExists('payroll_overtimes');
    }
};
