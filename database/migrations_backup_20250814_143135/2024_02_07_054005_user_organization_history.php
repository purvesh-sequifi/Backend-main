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
        Schema::create('user_organization_history', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->integer('user_id');
            $table->integer('updater_id');
            $table->integer('old_manager_id')->nullable();
            $table->integer('manager_id')->nullable();
            $table->integer('old_team_id')->nullable();
            $table->integer('team_id')->nullable();
            $table->date('effective_date');
            $table->integer('position_id');
            $table->integer('sub_position_id')->nullable();
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
        Schema::dropIfExists('user_organization_history');
    }
};
