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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('zeal_id')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('company_website')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('company_type')->nullable()->default('Pest');
            $table->string('company_email')->nullable()->unique();
            $table->string('business_name')->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('business_ein')->nullable();
            $table->string('business_phone')->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_state')->nullable();
            $table->string('business_zip')->nullable();
            $table->string('mailing_state')->nullable();
            $table->string('mailing_city')->nullable();
            $table->string('mailing_zip')->nullable();
            $table->string('time_zone')->nullable();
            $table->string('business_address_1', 100)->nullable();
            $table->string('business_address_2', 100)->nullable();
            $table->string('business_lat', 100)->nullable();
            $table->string('business_long', 100)->nullable();
            $table->string('mailing_address_1', 100)->nullable();
            $table->string('mailing_address_2', 100)->nullable();
            $table->string('mailing_lat', 100)->nullable();
            $table->string('mailing_long', 100)->nullable();
            $table->string('business_address_time_zone')->nullable();
            $table->string('mailing_address_time_zone')->nullable();
            $table->string('company_margin')->nullable();
            $table->string('country')->nullable();
            $table->string('logo');
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 11, 8)->nullable();
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
        Schema::dropIfExists('company_profiles');
    }
};
