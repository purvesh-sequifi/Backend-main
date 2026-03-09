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
        Schema::create('sale_master_process', function (Blueprint $table) {
            $table->id();
            $table->string('sale_master_id')->nullable();
            $table->string('pid')->nullable(); // PID
            $table->unsignedBigInteger('weekly_sheet_id')->nullable(); // PID
            $table->unsignedBigInteger('closer1_id')->nullable();
            $table->unsignedBigInteger('closer2_id')->nullable();
            $table->unsignedBigInteger('setter1_id')->nullable();
            $table->unsignedBigInteger('setter2_id')->nullable();

            $table->string('closer1_m1')->nullable();
            $table->string('closer2_m1')->nullable();
            $table->string('setter1_m1')->nullable();
            $table->string('setter2_m1')->nullable();

            $table->string('closer1_m2')->nullable();
            $table->string('closer2_m2')->nullable();
            $table->string('setter1_m2')->nullable();
            $table->string('setter2_m2')->nullable();

            $table->string('closer1_commission')->nullable();
            $table->string('closer2_commission')->nullable();
            $table->string('setter1_commission')->nullable();
            $table->string('setter2_commission')->nullable();

            $table->string('closer1_m1_paid_status')->nullable();
            $table->string('closer2_m1_paid_status')->nullable();
            $table->string('setter1_m1_paid_status')->nullable();
            $table->string('setter2_m1_paid_status')->nullable();

            $table->string('closer1_m2_paid_status')->nullable();
            $table->string('closer2_m2_paid_status')->nullable();
            $table->string('setter1_m2_paid_status')->nullable();
            $table->string('setter2_m2_paid_status')->nullable();

            $table->string('closer1_m1_paid_date')->nullable();
            $table->string('closer2_m1_paid_date')->nullable();
            $table->string('setter1_m1_paid_date')->nullable();
            $table->string('setter2_m1_paid_date')->nullable();

            $table->string('closer1_m2_paid_date')->nullable();
            $table->string('closer2_m2_paid_date')->nullable();
            $table->string('setter1_m2_paid_date')->nullable();
            $table->string('setter2_m2_paid_date')->nullable();
            $table->unsignedBigInteger('mark_account_status_id')->nullable();
            $table->string('pid_status')->nullable();
            $table->string('data_source_type')->nullable();
            $table->string('job_status')->nullable();

            $table->timestamps();
            // $table->foreign('mark_account_status_id')->references('id')
            //  ->on('mark_account_status')->onDelete('cascade');

            // $table->string('redline')->nullable();
            // $table->string('kw')->nullable();
            // $table->string('total_in_period')->nullable();
            // $table->timestamps();
            // $table->foreign('closer_id')->references('id')
            // ->on('users')->onDelete('cascade');
            // $table->foreign('setter_id')->references('id')
            // ->on('users')->onDelete('cascade');
            // $table->foreign('mark_account_status_id')->references('id')
            // ->on('mark_account_status')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sale_master_process');
    }
};
