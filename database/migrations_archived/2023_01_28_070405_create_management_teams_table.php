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
        Schema::create('management_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_lead_id')->nullable();
            $table->unsignedSmallInteger('location_id')->nullable();
            $table->integer('office_id')->nullable();
            $table->string('team_name')->nullable();
            $table->enum('type', ['lead'])->default('lead');
            $table->timestamps();
            $table->foreign('team_lead_id')->references('id')
                ->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('management_teams');
    }
};
