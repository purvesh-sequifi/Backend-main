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
        Schema::create('event_calendars', function (Blueprint $table) {
            $table->id();
            $table->date('event_date')->nullable();
            $table->string('event_time')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('event_name')->nullable();
            $table->enum('type', ['meeting', 'Interview', 'Career', 'Fair', 'Company', 'Event', 'Training', 'Hired'])->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->integer('office_id')->nullable();
            $table->text('description')->nullable();
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
        Schema::dropIfExists('event_calendars');
    }
};
