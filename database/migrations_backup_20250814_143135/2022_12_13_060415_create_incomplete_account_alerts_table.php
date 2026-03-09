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
        Schema::create('incomplete_account_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alert_id');
            $table->string('alert_type')->nullable();
            $table->integer('number')->nullable();
            $table->enum('type', ['day', 'week', 'months'])->default('day');
            $table->unsignedBigInteger('department_id');
            $table->tinyInteger('status')->nullable();
            $table->unsignedBigInteger('position_id');
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
        Schema::dropIfExists('incomplete_account_alerts');
    }
};
