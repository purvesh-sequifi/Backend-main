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
        Schema::create('position_wages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('position_id');
            $table->string('pay_type', 50)->nullable()->comment('Hourly, Salary');
            $table->tinyInteger('pay_type_lock')->comment('0 = Locked, 1 = Un-Locked');
            $table->string('pay_rate', 50)->default(0)->nullable();
            $table->string('pay_rate_type', 50)->nullable()->comment('Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly');
            $table->tinyInteger('pay_rate_lock')->comment('0 = Locked, 1 = Un-Locked');
            $table->string('pto_hours', 50)->default('0')->nullable();
            $table->tinyInteger('pto_hours_lock')->comment('0 = Locked, 1 = Un-Locked');
            $table->string('unused_pto_expires', 100)->nullable()->comment('Monthly, Annually, Accrues Continuously');
            $table->tinyInteger('unused_pto_expires_lock')->comment('0 = Locked, 1 = Un-Locked');
            $table->string('expected_weekly_hours', 50)->nullable();
            $table->tinyInteger('expected_weekly_hours_lock')->comment('0 = Locked, 1 = Un-Locked');
            $table->string('overtime_rate', 50)->nullable();
            $table->tinyInteger('overtime_rate_lock')->comment('0 = Locked, 1 = Un-Locked');
            $table->tinyInteger('wages_status')->default(0)->comment('0 = Disabled, 1 = Enable');
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
        Schema::dropIfExists('position_wages');
    }
};
