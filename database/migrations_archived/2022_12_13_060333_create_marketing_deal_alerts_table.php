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
        Schema::create('marketing_deal_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alert_id');
            $table->enum('alert_type', ['alert', 'limit'])->default('alert');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('position_id');
            $table->integer('personnel_id')->nullable();
            $table->float('amount')->nullable();
            $table->timestamps();

            $table->foreign('alert_id')->references('id')
                ->on('alerts')->onDelete('cascade');
            $table->foreign('department_id')->references('id')
                ->on('departments')->onDelete('cascade');
            $table->foreign('position_id')->references('id')
                ->on('employee_positions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketing_deal_alerts');
    }
};
