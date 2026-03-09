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
        Schema::create('FieldRoutes_Customer_Data', function (Blueprint $table) {
            $table->id();

            // === PRIMARY IDENTIFIERS ===
            $table->unsignedBigInteger('customer_id')->index(); // FieldRoutes customerID
            $table->unsignedBigInteger('office_id_fr')->nullable(); // FieldRoutes office ID
            $table->string('office_id')->nullable()->index(); // Our integration office identifier
            $table->string('office_name')->nullable()->index();
            $table->string('customer_link')->nullable(); // CustomerID specified on import
            $table->unsignedBigInteger('region_id')->nullable();

            // === PERSONAL INFORMATION ===
            $table->string('fname')->nullable()->index(); // First name
            $table->string('lname')->nullable()->index(); // Last name
            $table->string('company_name')->nullable()->index(); // Company name

            // === ADDRESS INFORMATION ===
            $table->text('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state', 10)->nullable()->index();
            $table->string('zip', 20)->nullable()->index();

            // === CONTACT INFORMATION ===
            $table->string('phone1', 20)->nullable()->index(); // Primary phone
            $table->string('phone2', 20)->nullable(); // Secondary phone
            $table->string('additional_phone', 20)->nullable(); // Additional contact phone
            $table->string('billing_phone', 20)->nullable(); // Billing phone
            $table->string('email')->nullable()->index();

            // === STATUS & ACTIVITY ===
            $table->tinyInteger('active')->default(1)->index(); // 1=Active, 0=Inactive
            $table->timestamp('date_added')->nullable()->index(); // When customer was created
            $table->timestamp('date_updated_fr')->nullable()->index(); // Last updated in FieldRoutes
            $table->timestamp('date_cancelled')->nullable(); // When customer was cancelled

            // === FINANCIAL INFORMATION ===
            $table->decimal('balance', 10, 2)->default(0)->index(); // Customer balance
            $table->decimal('responsible_balance', 10, 2)->default(0); // Responsible balance
            $table->integer('balance_age')->nullable(); // Balance age in days
            $table->date('aging_date')->nullable(); // Date the balance began
            $table->integer('responsible_balance_age')->nullable(); // Responsible balance age in days
            $table->date('responsible_aging_date')->nullable(); // Date the responsible balance began
            $table->tinyInteger('auto_pay_status')->default(0); // 0=None, 1=CC, 2=ACH
            $table->string('auto_pay_payment_profile_id')->nullable();

            // === EMPLOYEE/REP INFORMATION ===
            $table->string('employee_id')->nullable()->index(); // EmployeeID who added customer
            $table->string('employee_name')->nullable();
            $table->unsignedBigInteger('sequifi_id')->nullable()->index();
            $table->unsignedBigInteger('added_by_id')->nullable(); // EmployeeID who added customer

            // === RAW DATA STORAGE ===
            $table->longText('customer_data')->nullable(); // JSON storage of full API response

            // === SYNC METADATA ===
            $table->string('sync_status')->default('pending')->index(); // pending, completed, failed
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('last_modified')->nullable()->index(); // Track actual data changes
            $table->string('sync_batch_id')->nullable()->index();
            $table->text('sync_notes')->nullable(); // Error messages, warnings, etc.

            // === TIMESTAMPS ===
            $table->timestamps();

            // === INDEXES FOR PERFORMANCE ===
            $table->unique(['customer_id', 'office_id'], 'unique_customer_office');
            $table->index(['office_name', 'active'], 'office_active_customers');
            $table->index(['employee_id', 'active'], 'employee_active_customers');
            $table->index(['date_updated_fr', 'active'], 'updated_active_customers');
            $table->index(['last_synced_at', 'sync_status'], 'sync_tracking');
            $table->index(['balance', 'active'], 'balance_active_customers');
            $table->index(['auto_pay_status', 'active'], 'autopay_customers');
            $table->index(['state', 'city'], 'location_customers');
            $table->index(['fname', 'lname'], 'customer_name_search');
            $table->index(['company_name'], 'company_search');
            $table->index(['email'], 'email_search');
            $table->index('sequifi_id', 'sequifi_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('FieldRoutes_Customer_Data');
    }
};
