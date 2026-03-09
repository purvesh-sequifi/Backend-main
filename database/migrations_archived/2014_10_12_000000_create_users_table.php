<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_uuid')->nullable()->comment('for users Gusto uuid');
            $table->string('aveyo_hs_id')->nullable();
            $table->string('jobnimbus_jnid')->nullable();
            $table->string('jobnimbus_number')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('everee_workerId')->nullable();
            $table->integer('self_gen_accounts')->nullable();
            $table->integer('self_gen_type')->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('sex', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('image')->default('Employee_profile/default-user.png');
            $table->string('zip_code')->nullable();
            $table->string('email')->unique();
            $table->string('work_email')->nullable();
            $table->string('device_token')->nullable();
            $table->rememberToken();
            $table->string('home_address')->nullable();
            $table->string('home_address_line_1')->nullable();
            $table->string('home_address_line_2')->nullable();
            $table->string('home_address_state')->nullable();
            $table->string('home_address_city')->nullable();
            $table->string('home_address_zip')->nullable();
            $table->string('home_address_lat')->nullable();
            $table->string('home_address_long')->nullable();
            $table->string('home_address_timezone')->nullable();
            $table->string('emergency_address_line_1')->nullable();
            $table->string('emergency_address_line_2')->nullable();
            $table->string('emergency_address_lat')->nullable();
            $table->string('emergency_address_long')->nullable();
            $table->string('emergency_address_timezone')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->string('emergrncy_contact_address')->nullable();
            $table->string('emergrncy_contact_zip_code')->nullable();
            $table->string('emergrncy_contact_state')->nullable();
            $table->string('emergrncy_contact_city')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('mobile_no')->unique()->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('employee_position_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->integer('team_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->integer('recruiter_id')->nullable();
            $table->integer('is_super_admin')->nullable()->default(0);
            $table->integer('is_manager')->nullable()->default(0);
            $table->integer('team_lead_status')->nullable()->default(0);
            $table->integer('stop_payroll')->nullable()->default(0);
            $table->integer('dismiss')->nullable()->default(0);
            $table->integer('disable_login')->nullable()->default(0);
            $table->integer('additional_recruiter_id1')->nullable();
            $table->integer('additional_recruiter_id2')->nullable();
            $table->string('additional_recruiter1_per_kw_amount')->nullable();
            $table->string('additional_recruiter2_per_kw_amount')->nullable();
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('sub_position_id');
            $table->integer('group_id')->nullable();
            $table->string('commission')->nullable();
            $table->enum('commission_type', ['percent', 'per kw'])->nullable();
            $table->integer('self_gen_commission')->default(0);
            $table->enum('self_gen_commission_type', ['percent', 'per kw'])->nullable();
            $table->string('redline')->nullable();
            $table->string('redline_amount_type')->nullable();
            $table->enum('redline_type', ['per sale', 'per watt'])->default('per watt');
            $table->string('self_gen_redline')->nullable();
            $table->string('self_gen_redline_amount_type')->nullable();
            $table->enum('self_gen_redline_type', ['per sale', 'per watt'])->nullable();
            $table->string('upfront_pay_amount')->nullable();
            $table->enum('upfront_sale_type', ['per sale', 'per KW'])->default('per sale');
            $table->double('self_gen_upfront_amount', 8, 2)->default('0.00');
            $table->enum('self_gen_upfront_type', ['per sale', 'per watt'])->nullable();
            $table->string('direct_overrides_amount')->nullable();
            $table->enum('direct_overrides_type', ['per sale', 'per kw'])->default('per kw');
            $table->string('indirect_overrides_amount')->nullable();
            $table->enum('indirect_overrides_type', ['per sale', 'per kw'])->default('per kw');
            $table->string('office_overrides_amount')->nullable();
            $table->enum('office_overrides_type', ['per sale', 'per kw'])->default('per kw');
            $table->longText('office_stack_overrides_amount')->nullable();
            $table->string('probation_period')->nullable();
            $table->string('hiring_bonus_amount')->nullable();
            $table->date('date_to_be_paid')->nullable();
            $table->date('period_of_agreement_start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('offer_expiry_date')->nullable();
            $table->enum('type', ['Manager'])->nullable();
            $table->string('api_token', 80)->nullable();
            $table->string('rent', 80)->nullable();
            $table->string('travel', 80)->nullable();
            $table->longText('employee_additional_fields')->nullable();
            $table->string('phone_Bill', 80)->nullable();
            // $table->string('remember_token',100)->nullable();
            $table->string('social_sequrity_no', 100)->nullable();
            $table->string('tax_information', 100)->nullable();
            $table->string('name_of_bank', 100)->nullable();
            $table->string('routing_no', 100)->nullable();
            $table->string('account_no', 100)->nullable();
            $table->string('account_name', 100)->nullable();
            $table->string('confirm_account_no', 100)->nullable();
            $table->string('type_of_account', 100)->nullable();
            $table->string('shirt_size', 100)->nullable();
            $table->string('hat_size', 100)->nullable();
            $table->longText('additional_info_for_employee_to_get_started')->nullable();
            $table->longText('employee_personal_detail')->nullable();
            $table->integer('onboardProcess')->nullable();
            $table->integer('office_id')->nullable();
            $table->decimal('withheld_amount', 8, 2)->nullable();
            $table->string('withheld_type')->nullable();
            $table->decimal('self_gen_withheld_amount', 8, 2)->nullable();
            $table->string('self_gen_withheld_type')->nullable();
            $table->date('commission_effective_date')->nullable();
            $table->date('self_gen_commission_effective_date')->nullable();
            $table->date('upfront_effective_date')->nullable();
            $table->date('self_gen_upfront_effective_date')->nullable();
            $table->date('withheld_effective_date')->nullable();
            $table->date('self_gen_withheld_effective_date')->nullable();
            $table->date('redline_effective_date')->nullable();
            $table->date('self_gen_redline_effective_date')->nullable();
            $table->date('override_effective_date')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('business_ein')->nullable();
            $table->tinyInteger('first_time_changed_password')->nullable()->comment(0 or 1);
            $table->enum('is_agreement_accepted', ['0', '1'])->nullable();
            $table->text('everee_json_response')->nullable();
            $table->timestamps();

            // $table->foreign('state_id')->references('id')->on('states')->onDelete('cascade');
            // $table->foreign('department_id')->references('id')
            // ->on('departments')->onDelete('cascade');
            // $table->foreign('employee_position_id')->references('id')
            // ->on('employee_positions')->onDelete('cascade');
            // $table->foreign('manager_id')->references('id')
            // ->on('users')->onDelete('cascade');
            // // $table->foreign('status_id')->references('id')
            // // ->on('status')->onDelete('cascade');
            // $table->foreign('position_id')->references('id')
            // ->on('positions')->onDelete('cascade');
            // $table->foreign('city_id')->references('id')
            // ->on('cities')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
