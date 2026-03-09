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
        Schema::create('business_address', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('company_website', 255)->nullable();
            $table->string('phone_number', 255)->nullable();
            $table->string('company_type', 255)->nullable();
            $table->string('company_email', 255)->nullable();
            $table->string('business_name', 255)->nullable();
            $table->string('mailing_address', 255)->nullable();
            $table->string('business_ein', 255)->nullable();
            $table->integer('business_phone')->default(0)->nullable();
            $table->text('business_address')->nullable();
            $table->string('business_city', 255)->nullable();
            $table->string('business_state', 255)->nullable();
            $table->string('business_zip', 255)->nullable();
            $table->string('mailing_state', 255)->nullable();
            $table->string('mailing_city', 255)->nullable();
            $table->string('mailing_zip', 255)->nullable();
            $table->string('mailing_ein', 255)->nullable();
            $table->string('time_zone', 255)->nullable();
            $table->string('country', 255)->nullable();
            $table->string('logo', 255)->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('lng', 10, 8)->nullable();
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
        Schema::dropIfExists('business_addresses');
    }
};
