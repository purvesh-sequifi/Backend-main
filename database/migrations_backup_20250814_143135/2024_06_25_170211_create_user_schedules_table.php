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
        Schema::create('user_schedules', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('scheduled_by')->nullable();
            $table->tinyInteger('is_flexible')->default(0)->comment('0 = Not Flexible, 1 = Flexible');
            $table->tinyInteger('is_repeat')->default(0)->comment('0 = No Repeat, 1 = Repeat');
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
        Schema::dropIfExists('user_schedules');
    }
};
