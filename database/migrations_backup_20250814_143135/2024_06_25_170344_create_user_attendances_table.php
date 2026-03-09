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
        Schema::create('user_attendances', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->time('current_time')->nullable();
            $table->time('lunch_time')->nullable();
            $table->time('break_time')->nullable();
            $table->tinyInteger('is_synced')->default(0)->comment('0 = Noy Synced, 1 = Synced');
            $table->date('date');
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
        Schema::dropIfExists('user_attendances');
    }
};
