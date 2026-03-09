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
        Schema::create('management_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('team_lead_id')->nullable();
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->timestamps();
            $table->foreign('team_member_id')->references('id')
                ->on('users')->onDelete('cascade');
            $table->foreign('team_lead_id')->references('team_lead_id')
                ->on('management_teams')->onDelete('cascade');
            $table->foreign('team_id')->references('id')
                ->on('management_teams')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('management_team_members');
    }
};
