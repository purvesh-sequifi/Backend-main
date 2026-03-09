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
        Schema::create('pay_frequency_setting', function (Blueprint $table) {
            $table->id();
            $table->integer('frequency_type_id')->nullable();
            $table->string('first_months')->nullable();
            $table->string('first_day', 100)->nullable();
            $table->string('day_of_week')->nullable();
            $table->string('day_of_months')->nullable();
            $table->string('pay_period')->nullable();
            $table->string('monthly_pay_type')->nullable();
            $table->string('monthly_per_days')->nullable();
            $table->string('first_day_pay_of_manths')->nullable();
            $table->string('second_pay_day_of_month')->nullable();
            $table->string('deadline_to_run_payroll')->nullable();
            $table->string('first_pay_period_ends_on')->nullable();
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
        Schema::dropIfExists('pay_frequency_setting');
    }
};
