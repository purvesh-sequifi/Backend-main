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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_id');
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('work_site_id', 200)->nullable();
            $table->string('installation_partner')->nullable();
            $table->string('general_code')->nullable();
            $table->float('redline_min')->nullable();
            $table->float('redline_standard')->nullable();
            $table->float('redline_max')->nullable();
            // $table->integer('marketing_deal_person_id')->nullable();
            $table->enum('type', ['Redline', 'Office'])->default('Redline');
            $table->integer('created_by')->nullable();
            $table->date('date_effective')->nullable();
            $table->string('office_name')->nullable();
            $table->string('business_address')->nullable();
            $table->integer('office_status')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_state')->nullable();
            $table->string('business_zip')->nullable();
            $table->string('mailing_address')->nullable();
            $table->string('mailing_state')->nullable();
            $table->string('mailing_city')->nullable();
            $table->string('mailing_zip')->nullable();
            $table->double('lat')->nullable();
            $table->double('long')->nullable();
            $table->string('time_zone')->nullable();
            $table->integer('everee_location_id')->nullable();
            $table->text('everee_json_response')->nullable();
            $table->timestamps();

            $table->foreign('state_id')->references('id')
                ->on('states')->onDelete('cascade');
            $table->foreign('city_id')->references('id')
                ->on('cities')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locations');
    }
};
