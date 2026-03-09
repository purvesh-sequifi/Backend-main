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
        Schema::create('user_wages_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('updater_id')->nullable();
            $table->date('effective_date');
            $table->string('pay_type', 50)->nullable()->comment('Hourly, Salary');
            $table->string('old_pay_type', 50)->nullable()->comment('Hourly, Salary');
            $table->string('pay_rate', 50)->default(0)->nullable();
            $table->string('old_pay_rate', 50)->default(0)->nullable();
            $table->string('pay_rate_type', 50)->nullable()->comment('Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly');
            $table->string('old_pay_rate_type', 50)->nullable()->comment('Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly');
            $table->date('pto_hours_effective_date');
            $table->string('pto_hours', 50)->default('0')->nullable();
            $table->string('old_pto_hours', 50)->default('0')->nullable();
            $table->string('unused_pto_expires', 100)->nullable()->comment('Monthly, Annually, Accrues Continuously');
            $table->string('old_unused_pto_expires', 100)->nullable()->comment('Monthly, Annually, Accrues Continuously');
            $table->string('expected_weekly_hours', 50)->nullable();
            $table->string('old_expected_weekly_hours', 50)->nullable();
            $table->string('overtime_rate', 50)->nullable();
            $table->string('old_overtime_rate', 50)->nullable();
            $table->tinyInteger('action_item_status')->default('0')->comment('0 = Old, 1 = In Action Item');
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
        Schema::dropIfExists('user_wages_history');
    }
};
