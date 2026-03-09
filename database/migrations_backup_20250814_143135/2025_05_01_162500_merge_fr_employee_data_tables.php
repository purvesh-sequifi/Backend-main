<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only create the table if it doesn't exist
        if (! Schema::hasTable('fr_employee_data')) {
            Schema::create('fr_employee_data', function (Blueprint $table) {
                $table->id();

                // Basic Employee Info
                $table->string('employee_id')->unique();
                $table->string('office_id')->nullable();
                $table->string('office_name')->nullable();
                $table->unsignedBigInteger('sequifi_id')->nullable();
                $table->boolean('active')->default(true);
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

                // Employee Links and Relationships
                $table->json('linked_employee_ids')->nullable();
                $table->string('employee_link')->nullable();
                $table->string('license_number')->nullable();
                $table->string('supervisor_id')->nullable();
                $table->boolean('roaming_rep')->default(false);
                $table->json('regional_manager_office_ids')->nullable();

                // Login and Team Info
                $table->timestamp('last_login')->nullable();
                $table->json('team_ids')->nullable();
                $table->string('primary_team')->nullable();
                $table->string('access_control_profile_id')->nullable();

                // Start Location
                $table->string('start_address')->nullable();
                $table->string('start_city')->nullable();
                $table->string('start_state')->nullable();
                $table->string('start_zip')->nullable();
                $table->decimal('start_lat', 10, 6)->nullable();
                $table->decimal('start_lng', 10, 6)->nullable();

                // End Location
                $table->string('end_address')->nullable();
                $table->string('end_city')->nullable();
                $table->string('end_state')->nullable();
                $table->string('end_zip')->nullable();
                $table->decimal('end_lat', 10, 6)->nullable();
                $table->decimal('end_lng', 10, 6)->nullable();

                // Address
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('zip')->nullable();
                $table->string('country')->nullable();

                // Employment Details
                $table->string('status')->nullable();
                $table->string('hire_date')->nullable();
                $table->string('termination_date')->nullable();

                // Roles and Permissions
                $table->json('roles')->nullable();
                $table->json('permissions')->nullable();
                $table->json('additional_data')->nullable();

                // Security and Updates
                $table->boolean('two_factor_required')->default(false);
                $table->timestamp('two_factor_config_due_date')->nullable();
                $table->json('skills')->nullable();

                // Access Control
                $table->json('access_control')->nullable();
                $table->string('access_control_profile_name')->nullable();
                $table->string('access_control_profile_description')->nullable();

                // Timestamps
                $table->timestamp('date_updated')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('sequifi_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');

                // Indexes
                $table->index('employee_id');
                $table->index('office_id');
                $table->index('email');
                $table->index('username');
                $table->index('supervisor_id');
                $table->index('active');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('fr_employee_data');
    }
};
