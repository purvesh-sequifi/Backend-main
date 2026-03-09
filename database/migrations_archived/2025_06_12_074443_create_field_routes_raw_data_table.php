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
        Schema::create('FieldRoutes_Raw_Data', function (Blueprint $table) {
            $table->id();

            // === PRIMARY IDENTIFIERS ===
            $table->unsignedBigInteger('subscription_id')->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('bill_to_account_id')->nullable();
            $table->unsignedBigInteger('office_id_fr')->nullable(); // FieldRoutes office ID
            $table->string('office_id')->nullable()->index(); // Our integration office identifier
            $table->string('office_name')->nullable()->index();

            // === EMPLOYEE/REP DATA ===
            $table->string('employee_id')->nullable()->index(); // Rep who sold
            $table->string('employee_name')->nullable();
            $table->unsignedBigInteger('sequifi_id')->nullable()->index();
            $table->unsignedBigInteger('sold_by')->nullable()->index(); // Primary sales rep
            $table->unsignedBigInteger('sold_by_2')->nullable(); // Secondary sales rep
            $table->unsignedBigInteger('sold_by_3')->nullable(); // Tertiary sales rep
            $table->unsignedBigInteger('preferred_tech')->nullable();
            $table->unsignedBigInteger('added_by')->nullable();

            // === SUBSCRIPTION CORE DATA ===
            $table->tinyInteger('active')->default(1); // -3=Lead, 0=Frozen, 1=Active
            $table->string('active_text')->nullable();
            $table->integer('frequency')->nullable(); // Service frequency in days
            $table->integer('billing_frequency')->nullable();
            $table->integer('agreement_length')->nullable(); // Contract length
            $table->date('contract_added')->nullable();
            $table->boolean('on_hold')->default(false);

            // === SERVICE DATA ===
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('service_type')->nullable();
            $table->integer('followup_service')->nullable();
            $table->integer('annual_recurring_services')->nullable();
            $table->string('template_type')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('duration')->nullable();

            // === FINANCIAL DATA ===
            $table->decimal('initial_quote', 10, 2)->nullable();
            $table->decimal('initial_discount', 10, 2)->nullable();
            $table->decimal('initial_service_total', 10, 2)->nullable();
            $table->decimal('yif_discount', 10, 2)->nullable(); // Year in Full discount
            $table->decimal('recurring_charge', 10, 2)->nullable();
            $table->decimal('contract_value', 10, 2)->nullable();
            $table->decimal('annual_recurring_value', 10, 2)->nullable();
            $table->decimal('max_monthly_charge', 10, 2)->nullable();
            $table->date('initial_billing_date')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->integer('billing_terms_days')->nullable();
            $table->string('autopay_payment_profile_id')->nullable();

            // === LEAD DATA ===
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->date('lead_date_added')->nullable();
            $table->timestamp('lead_updated')->nullable();
            $table->unsignedBigInteger('lead_added_by')->nullable();
            $table->unsignedBigInteger('lead_source_id')->nullable();
            $table->string('lead_source')->nullable();
            $table->integer('lead_status')->nullable();
            $table->string('lead_status_text')->nullable();
            $table->unsignedBigInteger('lead_stage_id')->nullable();
            $table->string('lead_stage')->nullable();
            $table->unsignedBigInteger('lead_assigned_to')->nullable();
            $table->date('lead_date_assigned')->nullable();
            $table->decimal('lead_value', 10, 2)->nullable();
            $table->date('lead_date_closed')->nullable();
            $table->string('lead_lost_reason')->nullable();
            $table->string('lead_lost_reason_text')->nullable();

            // === SOURCE DATA ===
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('sub_source_id')->nullable();
            $table->string('sub_source')->nullable();

            // === SCHEDULING & PREFERENCES ===
            $table->date('next_service')->nullable()->index();
            $table->date('last_completed')->nullable();
            $table->timestamp('next_appointment_due_date')->nullable();
            $table->timestamp('last_appointment')->nullable();
            $table->integer('preferred_days')->nullable(); // Day of week preference
            $table->time('preferred_start')->nullable();
            $table->time('preferred_end')->nullable();
            $table->boolean('call_ahead')->default(false);
            $table->date('seasonal_start')->nullable();
            $table->date('seasonal_end')->nullable();
            $table->unsignedBigInteger('custom_schedule_id')->nullable();

            // === APPOINTMENT DATA ===
            $table->unsignedBigInteger('initial_appointment_id')->nullable();
            $table->integer('initial_status')->nullable();
            $table->string('initial_status_text')->nullable();
            $table->json('appointment_ids')->nullable(); // Array of appointment IDs
            $table->json('completed_appointment_ids')->nullable();

            // === SPECIALIZED FEATURES ===
            $table->boolean('sentricon_connected')->default(false);
            $table->string('sentricon_site_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->decimal('capacity_estimate', 8, 2)->nullable();
            $table->json('unit_ids')->nullable(); // Array of unit IDs
            $table->json('add_ons')->nullable(); // Additional services

            // === RENEWAL & CONTRACT ===
            $table->integer('renewal_frequency')->nullable();
            $table->date('renewal_date')->nullable();
            $table->date('custom_date')->nullable();
            $table->date('expiration_date')->nullable();

            // === FINANCIAL DOCUMENTS ===
            $table->string('initial_invoice')->nullable();
            $table->string('po_number')->nullable();
            $table->json('recurring_ticket')->nullable();

            // === CANCELLATION DATA ===
            $table->date('date_cancelled')->nullable();
            $table->text('cancellation_notes')->nullable(); // cxlNotes
            $table->unsignedBigInteger('cancelled_by')->nullable();

            // === EXTERNAL LINKS ===
            $table->text('subscription_link')->nullable();

            // === TIMESTAMPS ===
            $table->timestamp('date_added')->nullable()->index(); // When subscription was created in FieldRoutes
            $table->timestamp('date_updated_fr')->nullable(); // Last updated in FieldRoutes

            // === RAW DATA STORAGE ===
            $table->longText('subscription_data')->nullable(); // Complete JSON from API
            $table->longText('customer_data')->nullable(); // Customer details if fetched
            $table->longText('appointment_data')->nullable(); // Appointment details if fetched

            // === SYNC METADATA ===
            $table->string('sync_status')->default('pending'); // pending, processing, completed, failed
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_batch_id')->nullable()->index(); // For tracking batch operations
            $table->text('sync_notes')->nullable(); // Any sync-related notes or errors

            // === STANDARD TIMESTAMPS ===
            $table->timestamps();

            // === INDEXES FOR PERFORMANCE ===
            $table->index(['office_id', 'employee_id']);
            $table->index(['office_id', 'date_added']);
            $table->index(['employee_id', 'date_added']);
            $table->index(['subscription_id', 'office_id']); // Unique constraint alternative
            $table->index(['sync_status', 'last_synced_at']);
            $table->index(['active', 'next_service']);
            $table->index(['service_id', 'frequency']);

            // Note: Consider adding unique constraint on (subscription_id, office_id) if needed
            // $table->unique(['subscription_id', 'office_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('FieldRoutes_Raw_Data');
    }
};
