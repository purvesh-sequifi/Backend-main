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
        // First drop the table if it exists
        Schema::dropIfExists('fr_employee_data');

        // Then create it with the proper structure
        Schema::create('fr_employee_data', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable(false);
            $table->string('office_id')->nullable();
            $table->string('office_name')->nullable();
            $table->unsignedBigInteger('sequifi_id')->nullable();
            $table->tinyInteger('active')->default(1);
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('initials')->nullable();
            $table->string('nickname')->nullable();
            $table->string('type')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('experience')->nullable();
            $table->string('pic')->nullable();
            $table->string('employee_link')->nullable();
            $table->string('license_number')->nullable();
            $table->string('supervisor_id')->nullable();
            $table->tinyInteger('roaming_rep')->default(0);
            $table->text('regional_manager_office_ids')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->string('status')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('roles')->nullable();
            $table->text('permissions')->nullable();
            $table->text('additional_data')->nullable();
            $table->string('primary_team')->nullable();
            $table->string('access_control_profile_id')->nullable();
            $table->string('start_address')->nullable();
            $table->string('start_city')->nullable();
            $table->string('start_state')->nullable();
            $table->string('start_zip')->nullable();
            $table->decimal('start_lat', 10, 6)->nullable();
            $table->decimal('start_lng', 10, 6)->nullable();
            $table->string('end_address')->nullable();
            $table->string('end_city')->nullable();
            $table->string('end_state')->nullable();
            $table->string('end_zip')->nullable();
            $table->decimal('end_lat', 10, 6)->nullable();
            $table->decimal('end_lng', 10, 6)->nullable();
            $table->text('skills')->nullable();
            $table->text('access_control')->nullable();
            $table->string('access_control_profile_name')->nullable();
            $table->timestamp('date_updated')->nullable();
            $table->boolean('two_factor_required')->default(false);
            $table->date('two_factor_config_due_date')->nullable();
            $table->timestamps();

            // Note: Deliberately NOT adding any unique constraints
            // to allow the same employee_id to exist in different offices
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fr_employee_data');
    }
};
