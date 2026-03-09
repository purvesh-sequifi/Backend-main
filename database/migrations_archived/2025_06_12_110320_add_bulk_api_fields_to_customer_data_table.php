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
        Schema::table('FieldRoutes_Customer_Data', function (Blueprint $table) {
            // === BILLING & ACCOUNT INFO ===
            $table->unsignedBigInteger('bill_to_account_id')->nullable()->after('customer_id'); // Billing account ID
            $table->string('spouse')->nullable()->after('company_name'); // Spouse/alternate contact
            $table->tinyInteger('commercial_account')->default(0)->after('spouse'); // 0=Residential, 1=Commercial
            $table->tinyInteger('status')->default(1)->after('active'); // 0=Inactive, 1=Active (separate from our active field)
            $table->string('status_text')->nullable()->after('status'); // Friendly status text

            // === EXTENDED CONTACT INFO ===
            $table->string('ext1', 10)->nullable()->after('phone1'); // Extension for phone1
            $table->string('ext2', 10)->nullable()->after('phone2'); // Extension for phone2

            // === BILLING ADDRESS & CONTACT ===
            $table->string('billing_company_name')->nullable()->after('email');
            $table->string('billing_fname')->nullable()->after('billing_company_name');
            $table->string('billing_lname')->nullable()->after('billing_fname');
            $table->string('billing_country_id')->nullable()->after('billing_lname');
            $table->text('billing_address')->nullable()->after('billing_country_id');
            $table->string('billing_city')->nullable()->after('billing_address');
            $table->string('billing_state', 10)->nullable()->after('billing_city');
            $table->string('billing_zip', 20)->nullable()->after('billing_state');
            $table->string('billing_email')->nullable()->after('billing_zip');

            // === LOCATION & PROPERTY INFO ===
            $table->decimal('lat', 10, 8)->nullable()->after('zip'); // Latitude
            $table->decimal('lng', 11, 8)->nullable()->after('lat'); // Longitude
            $table->integer('square_feet')->nullable()->after('lng'); // Square footage
            $table->string('county')->nullable()->after('state'); // County name

            // === SOURCE & ACQUISITION ===
            $table->unsignedBigInteger('source_id')->nullable()->after('added_by_id'); // Source ID
            $table->string('source')->nullable()->after('source_id'); // Source description
            $table->string('customer_source')->nullable()->after('source'); // Customer source
            $table->string('customer_source_id')->nullable()->after('customer_source'); // Customer source ID
            $table->unsignedBigInteger('customer_sub_source_id')->nullable()->after('customer_source_id'); // Sub-source ID
            $table->string('customer_sub_source')->nullable()->after('customer_sub_source_id'); // Sub-source description

            // === PAYMENT & BILLING PREFERENCES ===
            $table->string('a_pay')->nullable()->after('auto_pay_payment_profile_id'); // AutoPay status (legacy)
            $table->unsignedBigInteger('preferred_tech_id')->nullable()->after('a_pay'); // Preferred technician
            $table->tinyInteger('paid_in_full')->default(0)->after('preferred_tech_id'); // Prefers to pay in advance
            $table->integer('preferred_billing_date')->nullable()->after('paid_in_full'); // Preferred billing day
            $table->date('payment_hold_date')->nullable()->after('preferred_billing_date'); // Payment hold date
            $table->decimal('max_monthly_charge', 10, 2)->nullable()->after('payment_hold_date'); // Max monthly charge

            // === CREDIT CARD INFO ===
            $table->string('most_recent_credit_card_last_four', 4)->nullable()->after('max_monthly_charge');
            $table->string('most_recent_credit_card_expiration_date')->nullable()->after('most_recent_credit_card_last_four');

            // === RELATIONSHIP DATA (stored as comma-separated strings) ===
            $table->text('subscription_ids')->nullable()->after('most_recent_credit_card_expiration_date'); // CSV of subscription IDs
            $table->text('appointment_ids')->nullable()->after('subscription_ids'); // CSV of appointment IDs
            $table->text('ticket_ids')->nullable()->after('appointment_ids'); // CSV of ticket IDs
            $table->text('payment_ids')->nullable()->after('ticket_ids'); // CSV of payment IDs
            $table->text('unit_ids')->nullable()->after('payment_ids'); // CSV of unit IDs

            // === NESTED DATA ARRAYS ===
            $table->json('subscriptions')->nullable()->after('unit_ids'); // Full subscription objects array
            $table->json('cancellation_reasons')->nullable()->after('subscriptions'); // Cancellation notes array
            $table->json('customer_flags')->nullable()->after('cancellation_reasons'); // Customer flags array
            $table->json('additional_contacts')->nullable()->after('customer_flags'); // Additional contacts array

            // === PORTAL ACCESS ===
            $table->text('portal_login')->nullable()->after('additional_contacts'); // Portal login URL
            $table->timestamp('portal_login_expires')->nullable()->after('portal_login'); // Portal token expiration

            // === ACCOUNT DETAILS ===
            $table->string('customer_number')->nullable()->after('portal_login_expires'); // Legacy customer number
            $table->string('master_account')->nullable()->after('customer_number'); // Master account customer ID

            // === LOCATION & ROUTING ===
            $table->string('map_code')->nullable()->after('master_account'); // Map code
            $table->string('map_page')->nullable()->after('map_code'); // Map page
            $table->text('special_scheduling')->nullable()->after('map_page'); // Special scheduling notes

            // === TAX INFORMATION ===
            $table->decimal('tax_rate', 8, 6)->nullable()->after('special_scheduling'); // Tax rate
            $table->decimal('state_tax', 8, 6)->nullable()->after('tax_rate'); // State tax rate
            $table->decimal('city_tax', 8, 6)->nullable()->after('state_tax'); // City tax rate
            $table->decimal('county_tax', 8, 6)->nullable()->after('city_tax'); // County tax rate
            $table->decimal('district_tax', 8, 6)->nullable()->after('county_tax'); // District tax rate
            $table->decimal('district_tax1', 8, 6)->nullable()->after('district_tax'); // District 1 tax rate
            $table->decimal('district_tax2', 8, 6)->nullable()->after('district_tax1'); // District 2 tax rate
            $table->decimal('district_tax3', 8, 6)->nullable()->after('district_tax2'); // District 3 tax rate
            $table->decimal('district_tax4', 8, 6)->nullable()->after('district_tax3'); // District 4 tax rate
            $table->decimal('district_tax5', 8, 6)->nullable()->after('district_tax4'); // District 5 tax rate
            $table->decimal('custom_tax', 8, 6)->nullable()->after('district_tax5'); // Custom tax rate
            $table->unsignedBigInteger('zip_tax_id')->nullable()->after('custom_tax'); // Zip tax ID

            // === COMMUNICATION PREFERENCES ===
            $table->tinyInteger('sms_reminders')->default(0)->after('zip_tax_id'); // SMS reminder preference
            $table->tinyInteger('phone_reminders')->default(0)->after('sms_reminders'); // Phone reminder preference
            $table->tinyInteger('email_reminders')->default(0)->after('phone_reminders'); // Email reminder preference

            // === PROPERTY CLASSIFICATION ===
            $table->tinyInteger('use_structures')->default(0)->after('email_reminders'); // Structure customer flag
            $table->tinyInteger('is_multi_unit')->default(0)->after('use_structures'); // Multi-unit customer flag
            $table->unsignedBigInteger('division_id')->nullable()->after('is_multi_unit'); // Division ID
            $table->unsignedBigInteger('sub_property_type_id')->nullable()->after('division_id'); // Sub-property type ID
            $table->string('sub_property_type')->nullable()->after('sub_property_type_id'); // Sub-property type description

            // === CUSTOMER FLAGS ===
            $table->tinyInteger('salesman_a_pay')->default(0)->after('sub_property_type'); // Sales rep APay flag
            $table->tinyInteger('purple_dragon')->default(0)->after('salesman_a_pay'); // Purple Dragon flag
            $table->tinyInteger('termite_monitoring')->default(0)->after('purple_dragon'); // Termite monitoring flag
            $table->tinyInteger('pending_cancel')->default(0)->after('termite_monitoring'); // Pending cancel flag

            // === ADDITIONAL INDEXES ===
            $table->index(['bill_to_account_id'], 'bill_to_account_lookup');
            $table->index(['commercial_account'], 'commercial_customers');
            $table->index(['status', 'active'], 'status_active');
            $table->index(['source_id'], 'source_lookup');
            $table->index(['preferred_tech_id'], 'preferred_tech');
            $table->index(['lat', 'lng'], 'location_coordinates');
            $table->index(['county'], 'county_lookup');
            $table->index(['division_id'], 'division_lookup');
            $table->index(['use_structures', 'is_multi_unit'], 'property_classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('FieldRoutes_Customer_Data', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['bill_to_account_id']);
            $table->dropIndex(['commercial_account']);
            $table->dropIndex(['status', 'active']);
            $table->dropIndex(['source_id']);
            $table->dropIndex(['preferred_tech_id']);
            $table->dropIndex(['lat', 'lng']);
            $table->dropIndex(['county']);
            $table->dropIndex(['division_id']);
            $table->dropIndex(['use_structures', 'is_multi_unit']);

            // Drop columns
            $table->dropColumn([
                'bill_to_account_id', 'spouse', 'commercial_account', 'status', 'status_text',
                'ext1', 'ext2',
                'billing_company_name', 'billing_fname', 'billing_lname', 'billing_country_id',
                'billing_address', 'billing_city', 'billing_state', 'billing_zip', 'billing_email',
                'lat', 'lng', 'square_feet', 'county',
                'source_id', 'source', 'customer_source', 'customer_source_id',
                'customer_sub_source_id', 'customer_sub_source',
                'a_pay', 'preferred_tech_id', 'paid_in_full', 'preferred_billing_date',
                'payment_hold_date', 'max_monthly_charge',
                'most_recent_credit_card_last_four', 'most_recent_credit_card_expiration_date',
                'subscription_ids', 'appointment_ids', 'ticket_ids', 'payment_ids', 'unit_ids',
                'subscriptions', 'cancellation_reasons', 'customer_flags', 'additional_contacts',
                'portal_login', 'portal_login_expires',
                'customer_number', 'master_account',
                'map_code', 'map_page', 'special_scheduling',
                'tax_rate', 'state_tax', 'city_tax', 'county_tax', 'district_tax',
                'district_tax1', 'district_tax2', 'district_tax3', 'district_tax4', 'district_tax5',
                'custom_tax', 'zip_tax_id',
                'sms_reminders', 'phone_reminders', 'email_reminders',
                'use_structures', 'is_multi_unit', 'division_id', 'sub_property_type_id', 'sub_property_type',
                'salesman_a_pay', 'purple_dragon', 'termite_monitoring', 'pending_cancel',
            ]);
        });
    }
};
