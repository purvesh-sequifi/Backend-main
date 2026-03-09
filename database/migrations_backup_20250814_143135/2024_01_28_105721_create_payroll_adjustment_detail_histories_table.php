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
        Schema::create('payroll_adjustment_detail_lock', function (Blueprint $table) {
            $table->id();
            $table->integer('payroll_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('pid')->nullable();
            $table->string('payroll_type')->nullable();
            $table->string('type')->nullable();
            $table->string('amount')->default(0);
            $table->text('comment')->nullable();
            $table->integer('cost_center_id')->nullable();
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
        Schema::dropIfExists('payroll_adjustment_detail_lock');
    }
};
