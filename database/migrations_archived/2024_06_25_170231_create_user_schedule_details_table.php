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
        Schema::create('user_schedule_details', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('schedule_id');
            $table->bigInteger('office_id');
            $table->datetime('schedule_from')->nullable();
            $table->datetime('schedule_to')->nullable();
            $table->string('lunch_duration')->nullable();
            $table->string('work_days')->nullable();
            $table->integer('repeated_batch')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type', 50)->nullable()->comment('system, manually');
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
        Schema::dropIfExists('user_schedule_details');
    }
};
