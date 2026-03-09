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
        Schema::create('onboarding_employees', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable();
            $table->string('aveyo_hs_id')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('middle_name')->nullable();
            $table->enum('sex', ['male', 'female', 'other'])->nullable();
            $table->date('dob')->nullable();
            $table->string('image')->default('Employee_profile/default-user.png');
            $table->string('zip_code')->nullable();
            $table->string('work_email')->nullable();
            $table->string('home_address')->nullable();
            $table->string('mobile_no')->unique()->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('location')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('employee_position_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->integer('team_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->date('status_date')->nullable();
            $table->integer('recruiter_id')->nullable();
            $table->integer('additional_recruiter_id1')->nullable();
            $table->integer('additional_recruiter_id2')->nullable();
            $table->integer('position_id')->nullable();
            $table->integer('sub_position_id')->nullable();
            $table->integer('is_manager')->nullable();
            $table->integer('self_gen_accounts')->nullable();
            $table->integer('self_gen_type')->nullable();
            $table->string('commission')->nullable();
            $table->enum('commission_type', ['percent', 'per kw'])->nullable();
            $table->integer('self_gen_commission')->default('0');
            $table->enum('self_gen_commission_type', ['percent', 'per kw'])->nullable();
            $table->string('redline')->nullable();
            $table->string('redline_amount_type')->nullable();
            $table->enum('redline_type', ['per sale', 'per watt'])->default('per watt');
            $table->string('upfront_pay_amount')->nullable();
            $table->enum('upfront_sale_type', ['per sale', 'per KW'])->default('per sale');
            $table->decimal('withheld_amount', 8, 2)->nullable();
            $table->enum('withheld_type', ['per sale', 'per KW'])->nullable();
            $table->decimal('self_gen_withheld_amount', 8, 2)->nullable();
            $table->enum('self_gen_withheld_type', ['per sale', 'per KW'])->nullable();
            $table->double('self_gen_upfront_amount', 8, 2)->default(0);
            $table->enum('self_gen_upfront_type', ['per sale', 'per KW'])->nullable();
            $table->string('direct_overrides_amount')->nullable();
            $table->enum('direct_overrides_type', ['per sale', 'per kw'])->default('per kw');
            $table->string('indirect_overrides_amount')->nullable();
            $table->enum('indirect_overrides_type', ['per sale', 'per kw'])->default('per kw');
            $table->string('office_overrides_amount')->nullable();
            $table->enum('office_overrides_type', ['per sale', 'per kw'])->default('per kw');
            $table->string('office_stack_overrides_amount')->nullable();
            $table->string('probation_period')->nullable();
            $table->string('hiring_bonus_amount')->nullable();
            $table->date('date_to_be_paid')->nullable();
            $table->date('period_of_agreement_start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('offer_expiry_date')->nullable();
            $table->string('user_offer_letter')->nullable();
            $table->string('document_id')->nullable();
            $table->text('response')->nullable();
            $table->integer('hired_by_uid')->nullable();
            $table->enum('type', ['Manager'])->nullable();
            $table->enum('hiring_type', ['Directly'])->nullable();
            $table->enum('offer_include_bonus', [1, 0])->default(1);
            $table->unsignedBigInteger('office_id')->nullable();
            $table->string('self_gen_redline')->nullable();
            $table->string('self_gen_redline_amount_type')->nullable();
            $table->enum('self_gen_redline_type', ['per sale', 'per watt'])->nullable();
            $table->date('commission_selfgen_effective_date')->nullable();
            $table->double('commission_selfgen', 8, 2)->default('0.00');
            $table->enum('commission_selfgen_type', ['percent', 'per kw'])->nullable();

            // $table->foreign('state_id')->references('id')
            // ->on('states')->onDelete('cascade');
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
        Schema::dropIfExists('onboarding_employees');
    }
};
