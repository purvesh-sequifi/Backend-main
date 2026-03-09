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
        Schema::create('onboarding_employee_deduction', function (Blueprint $table) {
            $table->id();
            $table->enum('deduction_type', ['%', '$'])->nullable();
            $table->string('cost_center_name')->nullable();
            $table->string('cost_center_id')->nullable();
            $table->string('ammount_par_paycheck')->nullable();
            $table->Integer('deduction_setting_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
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
        Schema::dropIfExists('onboarding_employee_deduction');
    }
};
