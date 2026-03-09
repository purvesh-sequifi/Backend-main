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
        Schema::create('users_tiers_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('tier_schema_id')->nullable();
            $table->unsignedBigInteger('tier_schema_level_id')->nullable();
            $table->unsignedBigInteger('next_tier_schema_level_id')->nullable();
            $table->string('tiers_type')->comment('Progressive, Retroactive')->nullable();
            $table->string('type')->comment('Commission, Upfront, Override')->nullable();
            $table->string('sub_type')->comment('Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)')->nullable();
            $table->string('current_value')->nullable();
            $table->string('remaining_value')->nullable();
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
        Schema::dropIfExists('users_tiers_histories');
    }
};
