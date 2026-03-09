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
        Schema::create('users_business_address', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('business_address')->nullable();
            $table->string('business_address_line_1')->nullable();
            $table->string('business_address_line_2')->nullable();
            $table->string('business_address_state')->nullable();
            $table->string('business_address_city')->nullable();
            $table->string('business_address_zip')->nullable();
            $table->string('business_address_lat')->nullable();
            $table->string('business_address_long')->nullable();
            $table->string('business_address_timezone')->nullable();
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
        //
    }
};
