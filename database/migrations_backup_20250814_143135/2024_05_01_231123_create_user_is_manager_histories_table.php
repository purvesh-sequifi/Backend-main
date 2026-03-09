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
        Schema::create('user_is_manager_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('updater_id')->nullable();
            $table->date('effective_date')->nullable();
            $table->unsignedBigInteger('is_manager')->nullable();
            $table->unsignedBigInteger('old_is_manager')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->unsignedBigInteger('old_position_id')->nullable();
            $table->unsignedBigInteger('sub_position_id')->nullable();
            $table->unsignedBigInteger('old_sub_position_id')->nullable();
            $table->tinyInteger('action_item_status')->default('0')->comment('0 = Old, 1 = In Action Item');
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
        Schema::dropIfExists('user_is_manager_histories');
    }
};
