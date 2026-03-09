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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->text('comments')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('interview_date')->nullable();
            $table->string('interview_time')->nullable();
            $table->dateTime('last_hired_date')->nullable();
            $table->double('conversion_rate')->nullable();
            $table->unsignedBigInteger('interview_schedule_by_id')->nullable();
            $table->string('action_status')->nullable();
            $table->unsignedBigInteger('recruiter_id')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->unsignedBigInteger('assign_by_id')->nullable();
            $table->unsignedBigInteger('reporting_manager_id')->nullable();
            $table->string('type')->nullable();
            $table->integer('pipeline_status_id')->nullable();
            $table->date('pipeline_status_date')->nullable();
            $table->timestamps();
            $table->foreign('state_id')->references('id')
                ->on('states')->onDelete('cascade');

            $table->foreign('recruiter_id')->references('id')
                ->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leads');
    }
};
