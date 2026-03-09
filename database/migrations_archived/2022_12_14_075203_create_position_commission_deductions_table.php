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
        Schema::create('position_commission_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deduction_setting_id');
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('cost_center_id');
            $table->enum('deduction_type', ['$', '%'])->nullable();
            $table->unsignedBigInteger('position_commission_deductions')->nullable();
            $table->double('ammount_par_paycheck', 8, 2)->nullable();
            $table->timestamps();

            $table->foreign('position_id')->references('id')
                ->on('positions')->onDelete('cascade');
            $table->foreign('deduction_setting_id')->references('id')
                ->on('position_commission_deduction_settings')->onDelete('cascade');
            $table->foreign('cost_center_id')->references('id')
                ->on('cost_centers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('compenstion_plan_deductions');
    }
};
