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
        Schema::create('company_billing_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('company_website')->nullable();
            $table->string('company_email')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_ein')->nullable();
            $table->string('business_phone')->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_address_1', 100)->nullable();
            $table->string('business_address_2', 100)->nullable();
            $table->string('country')->nullable();
            $table->string('business_state')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_zip')->nullable();
            $table->string('business_address_time_zone')->nullable();
            $table->string('business_lat', 100)->nullable();
            $table->string('business_long', 100)->nullable();
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
        Schema::dropIfExists('company_billing_addresses');
    }
};
