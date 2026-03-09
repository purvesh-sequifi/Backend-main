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
        Schema::create('paystub_employee', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('user_employee_id')->nullable();
            $table->string('user_first_name')->nullable();
            $table->string('user_middle_name')->nullable();
            $table->string('user_last_name')->nullable();
            $table->string('user_zip_code')->nullable();
            $table->string('user_email')->nullable();
            $table->string('user_work_email')->nullable();
            $table->string('user_home_address')->nullable();
            $table->bigInteger('user_position_id')->nullable();
            $table->string('user_entity_type', 25)->nullable()->default('individual');
            $table->string('user_social_sequrity_no')->nullable();
            $table->string('user_business_name')->nullable();
            $table->string('user_business_ein')->nullable();
            $table->string('user_business_type')->nullable();
            $table->string('user_name_of_bank')->nullable();
            $table->string('user_routing_no')->nullable();
            $table->string('user_account_no')->nullable();
            $table->string('user_type_of_account')->nullable();
            $table->date('pay_period_from')->nullable();
            $table->date('pay_period_to')->nullable();
            $table->string('company_zeal_id')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_address')->nullable();
            $table->string('company_website')->nullable();
            $table->string('company_phone_number')->nullable();
            $table->string('company_type', 255)->default('Pest');
            $table->string('company_email')->nullable();
            $table->string('company_business_name')->nullable();
            $table->string('company_mailing_address')->nullable();
            $table->string('company_business_ein')->nullable();
            $table->string('company_business_phone')->nullable();
            $table->text('company_business_address')->nullable();
            $table->string('company_business_city')->nullable();
            $table->string('company_business_state')->nullable();
            $table->string('company_business_zip')->nullable();
            $table->string('company_mailing_state')->nullable();
            $table->string('company_mailing_city')->nullable();
            $table->string('company_mailing_zip')->nullable();
            $table->string('company_time_zone')->nullable();
            $table->string('company_business_address_1')->nullable();
            $table->string('company_business_address_2')->nullable();
            $table->string('company_business_lat')->nullable();
            $table->string('company_business_long')->nullable();
            $table->string('company_mailing_address_1')->nullable();
            $table->string('company_mailing_address_2')->nullable();
            $table->string('company_mailing_lat')->nullable();
            $table->string('company_mailing_long')->nullable();
            $table->string('company_business_address_time_zone')->nullable();
            $table->string('company_mailing_address_time_zone')->nullable();
            $table->string('company_margin')->nullable();
            $table->string('company_country')->nullable();
            $table->string('company_logo')->nullable();
            $table->decimal('company_lat', 10, 8)->nullable();
            $table->decimal('company_lng', 11, 8)->nullable();
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
        Schema::dropIfExists('paystub_employee');
    }
};
