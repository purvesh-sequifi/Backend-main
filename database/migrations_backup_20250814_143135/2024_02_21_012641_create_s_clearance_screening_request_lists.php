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
        Schema::create('s_clearance_screening_request_lists', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_type_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('office_id')->nullable();
            $table->unsignedBigInteger('applicant_id')->nullable();
            $table->unsignedBigInteger('screening_request_id')->nullable();
            $table->unsignedBigInteger('screening_request_applicant_id')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->tinyInteger('is_report_generated')->default(0);
            $table->tinyInteger('is_manual_verification')->default(0);
            $table->date('date_sent')->nullable();
            $table->date('report_date')->nullable();
            $table->unsignedBigInteger('approved_declined_by')->nullable();
            $table->string('status')->nullable();
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
        Schema::dropIfExists('s_clearance_request_lists');
    }
};
