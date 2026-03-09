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
        Schema::create('user_attendance_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_attendance_id');
            $table->bigInteger('adjustment_id');
            $table->bigInteger('office_id');
            $table->string('type')->comment('clock in, lunch, end lunch, break, end break, clock out');
            $table->dateTime('attendance_date');
            $table->string('entry_type')->comment('Adjustment, User');
            $table->bigInteger('created_by');
            $table->bigInteger('updated_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_attendance_details');
    }
};
