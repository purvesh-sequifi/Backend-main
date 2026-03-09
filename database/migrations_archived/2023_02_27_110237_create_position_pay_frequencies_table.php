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
        Schema::create('position_pay_frequencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('frequency_type_id');
            $table->string('first_months')->nullable();
            $table->string('first_day')->nullable();
            $table->string('day_of_week')->nullable();
            $table->string('day_of_months')->nullable();
            $table->string('pay_period')->nullable();
            $table->string('monthly_per_days')->nullable();
            $table->string('first_day_pay_of_manths')->nullable();
            $table->string('second_pay_day_of_month')->nullable();
            $table->string('deadline_to_run_payroll')->nullable();
            $table->string('first_pay_period_ends_on')->nullable();
            $table->timestamps();

            $table->foreign('frequency_type_id')->references('id')
                ->on('frequency_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('position_pay_frequencies');
    }
};
