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
        Schema::create('tiers_worker_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_lead_id');
            $table->unsignedBigInteger('tier_schema_id')->nullable();
            $table->unsignedBigInteger('tier_schema_level_id')->nullable();
            $table->string('tiers_type')->nullable()->comment('Progressive, Retroactive');
            $table->string('tiers_metrics')->nullable();
            $table->string('type')->nullable()->comment('User, Lead, Manager');
            $table->dateTime('reset_date_time')->nullable();
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
        Schema::dropIfExists('tiers_worker_histories');
    }
};
