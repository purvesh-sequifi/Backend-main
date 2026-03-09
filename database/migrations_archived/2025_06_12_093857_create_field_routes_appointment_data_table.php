<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('FieldRoutes_Appointment_Data', function (Blueprint $table) {
            $table->id();

            // === PRIMARY IDENTIFIERS ===
            $table->unsignedBigInteger('appointment_id')->index(); // FieldRoutes appointmentID
            $table->unsignedBigInteger('office_id_fr')->nullable(); // FieldRoutes office ID
            $table->string('office_id')->nullable()->index(); // Our integration office identifier
            $table->string('office_name')->nullable()->index();

            // === RELATIONSHIP LINKS (CRITICAL) ===
            $table->unsignedBigInteger('customer_id')->nullable()->index(); // Link to customers
            $table->unsignedBigInteger('subscription_id')->nullable()->index(); // Link to subscriptions
            $table->unsignedBigInteger('original_appointment_id')->nullable(); // If rescheduled

            // === APPOINTMENT STATUS & CORE DATA ===
            $table->tinyInteger('status')->index(); // 0=Pending, 1=Completed, 2=No Show, -2=Rescheduled, -1=Cancelled
            $table->string('status_text')->nullable(); // Human readable status
            $table->boolean('sales_anchor')->default(false)->index(); // First appointment for subscription

            // === SCHEDULING INFORMATION ===
            $table->date('scheduled_date')->nullable()->index(); // Date appointment is scheduled for
            $table->time('scheduled_time')->nullable(); // Time appointment is scheduled for
            $table->timestamp('date_added')->nullable()->index(); // When appointment was created
            $table->timestamp('date_completed')->nullable()->index(); // When marked as serviced
            $table->timestamp('date_cancelled')->nullable(); // When cancelled
            $table->timestamp('date_updated_fr')->nullable()->index(); // Last updated in FieldRoutes

            // === SERVICE INFORMATION ===
            $table->unsignedBigInteger('service_id')->nullable()->index(); // Service type ID
            $table->string('service_type')->nullable(); // Service type name
            $table->json('target_pests')->nullable(); // Array of target pest IDs

            // === ROUTE & LOCATION ===
            $table->unsignedBigInteger('route_id')->nullable()->index(); // Route assignment
            $table->unsignedBigInteger('spot_id')->nullable(); // Spot on route

            // === EMPLOYEE/TECHNICIAN ASSIGNMENTS ===
            $table->string('employee_id')->nullable()->index(); // Employee who owns appointment
            $table->string('employee_name')->nullable();
            $table->unsignedBigInteger('sequifi_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_tech')->nullable()->index(); // Assigned technician
            $table->string('assigned_tech_name')->nullable();
            $table->unsignedBigInteger('serviced_by')->nullable()->index(); // Technician who performed service
            $table->string('serviced_by_name')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable(); // User who marked complete
            $table->string('completed_by_name')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable(); // User who cancelled
            $table->string('cancelled_by_name')->nullable();
            $table->json('additional_techs')->nullable(); // Array of additional technician IDs

            // === SALES INFORMATION ===
            $table->unsignedBigInteger('sales_team_id')->nullable()->index(); // Sales team that sold
            $table->string('sales_team_name')->nullable();

            // === SERVICE DETAILS ===
            $table->text('service_notes')->nullable(); // Service notes/comments
            $table->decimal('service_amount', 10, 2)->nullable(); // Service charge
            $table->json('products_used')->nullable(); // Products/chemicals used
            $table->integer('duration_minutes')->nullable(); // Service duration

            // === APPOINTMENT OUTCOMES ===
            $table->text('completion_notes')->nullable(); // Notes on completion
            $table->text('cancellation_notes')->nullable(); // Cancellation reason
            $table->boolean('customer_present')->nullable(); // Was customer home
            $table->tinyInteger('customer_satisfaction')->nullable(); // 1-5 rating

            // === RAW DATA STORAGE ===
            $table->longText('appointment_data')->nullable(); // JSON storage of full API response

            // === SYNC METADATA ===
            $table->string('sync_status')->default('pending')->index(); // pending, completed, failed
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('last_modified')->nullable()->index(); // Track actual data changes
            $table->string('sync_batch_id')->nullable()->index();
            $table->text('sync_notes')->nullable(); // Error messages, warnings, etc.

            // === TIMESTAMPS ===
            $table->timestamps();

            // === INDEXES FOR PERFORMANCE ===
            $table->unique(['appointment_id', 'office_id'], 'unique_appointment_office');
            $table->index(['subscription_id', 'status'], 'subscription_appointments');
            $table->index(['customer_id', 'status'], 'customer_appointments');
            $table->index(['office_name', 'status'], 'office_appointments');
            $table->index(['scheduled_date', 'status'], 'daily_appointments');
            $table->index(['assigned_tech', 'scheduled_date'], 'tech_schedule');
            $table->index(['serviced_by', 'date_completed'], 'tech_completed');
            $table->index(['route_id', 'scheduled_date'], 'route_schedule');
            $table->index(['service_id', 'status'], 'service_appointments');
            $table->index(['date_updated_fr', 'status'], 'updated_appointments');
            $table->index(['last_synced_at', 'sync_status'], 'sync_tracking');
            $table->index(['sales_team_id', 'sales_anchor'], 'sales_tracking');
            $table->index(['employee_id', 'status'], 'employee_appointments');
            $table->index('sequifi_id', 'sequifi_mapping');
            $table->index(['status', 'scheduled_date'], 'status_schedule');
            $table->index(['date_added', 'office_name'], 'office_added_tracking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('FieldRoutes_Appointment_Data');
    }
};
