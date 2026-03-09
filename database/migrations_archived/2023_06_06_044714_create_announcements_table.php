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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('positions')->nullable();
            $table->string('office')->nullable();
            $table->string('link')->nullable();
            $table->date('start_date')->nullable();
            $table->string('durations')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('pin_to_top')->nullable();
            $table->string('file')->nullable();
            $table->integer('disable')->default(0);

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
        Schema::dropIfExists('announcements');
    }
};
