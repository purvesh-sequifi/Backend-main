<?php

use App\Models\FiberSalesImportField;
use App\Models\MortgageSalesImportField;
use App\Models\PestSalesImportField;
use App\Models\SchemaTriggerDate;
use App\Models\SolarSalesImportField;
use App\Models\TurfSalesImportField;
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
        if (! Schema::hasTable('pest_sales_import_fields')) {
            Schema::create('pest_sales_import_fields', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('label')->nullable();
                $table->tinyInteger('is_mandatory')->comment('0 = Non Mandatory, 1 = Mandatory')->default(0);
                $table->tinyInteger('is_custom')->comment('0 = Non Custom, 1 = Custom')->default(0);
                $table->string('section_name')->nullable();
                $table->string('field_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pest_sales_import_templates')) {
            Schema::create('pest_sales_import_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pest_sales_import_template_details')) {
            Schema::create('pest_sales_import_template_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('field_id')->nullable();
                $table->string('excel_field')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('solar_sales_import_fields')) {
            Schema::create('solar_sales_import_fields', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('label')->nullable();
                $table->tinyInteger('is_mandatory')->comment('0 = Non Mandatory, 1 = Mandatory')->default(0);
                $table->tinyInteger('is_custom')->comment('0 = Non Custom, 1 = Custom')->default(0);
                $table->string('section_name')->nullable();
                $table->string('field_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('solar_sales_import_templates')) {
            Schema::create('solar_sales_import_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('solar_sales_import_template_details')) {
            Schema::create('solar_sales_import_template_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('field_id')->nullable();
                $table->string('excel_field')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('turf_sales_import_fields')) {
            Schema::create('turf_sales_import_fields', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('label')->nullable();
                $table->tinyInteger('is_mandatory')->comment('0 = Non Mandatory, 1 = Mandatory')->default(0);
                $table->tinyInteger('is_custom')->comment('0 = Non Custom, 1 = Custom')->default(0);
                $table->string('section_name')->nullable();
                $table->string('field_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('turf_sales_import_templates')) {
            Schema::create('turf_sales_import_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('turf_sales_import_template_details')) {
            Schema::create('turf_sales_import_template_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('field_id')->nullable();
                $table->string('excel_field')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fiber_sales_import_fields')) {
            Schema::create('fiber_sales_import_fields', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('label')->nullable();
                $table->tinyInteger('is_mandatory')->comment('0 = Non Mandatory, 1 = Mandatory')->default(0);
                $table->tinyInteger('is_custom')->comment('0 = Non Custom, 1 = Custom')->default(0);
                $table->string('section_name')->nullable();
                $table->string('field_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fiber_sales_import_templates')) {
            Schema::create('fiber_sales_import_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fiber_sales_import_template_details')) {
            Schema::create('fiber_sales_import_template_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('field_id')->nullable();
                $table->string('excel_field')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mortgage_sales_import_fields')) {
            Schema::create('mortgage_sales_import_fields', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('label')->nullable();
                $table->tinyInteger('is_mandatory')->comment('0 = Non Mandatory, 1 = Mandatory')->default(0);
                $table->tinyInteger('is_custom')->comment('0 = Non Custom, 1 = Custom')->default(0);
                $table->string('section_name')->nullable();
                $table->string('field_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mortgage_sales_import_templates')) {
            Schema::create('mortgage_sales_import_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mortgage_sales_import_template_details')) {
            Schema::create('mortgage_sales_import_template_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('field_id')->nullable();
                $table->string('excel_field')->nullable();
                $table->timestamps();
            });
        }

        // PEST SECTION
        $pest = [
            [
                'name' => 'pid',
                'label' => 'PID',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'homeowner_id',
                'label' => 'Homeowner ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'product_id',
                'label' => 'Product',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_name',
                'label' => 'Customer Name',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address',
                'label' => 'Customer Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address_2',
                'label' => 'Customer Address 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_city',
                'label' => 'Customer City',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_state',
                'label' => 'Customer State',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_zip',
                'label' => 'Customer Zip',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_email',
                'label' => 'Customer Email',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_phone',
                'label' => 'Customer Phone',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'install_partner',
                'label' => 'Service Provider',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'length_of_agreement',
                'label' => 'Length Of Agreement',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'service_schedule',
                'label' => 'Service Schedule',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'gross_account_value',
                'label' => 'Gross Account Value',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'initial_service_cost',
                'label' => 'Initial Service Cost',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'subscription_payment',
                'label' => 'Subscription Payment',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'card_on_file',
                'label' => 'Card on file',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'auto_pay',
                'label' => 'Autopay',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'job_status',
                'label' => 'Job Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_signoff',
                'label' => 'Sale Date',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'date_cancelled',
                'label' => 'Cancel Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'service_completed',
                'label' => 'Services Completed',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'last_service_date',
                'label' => 'Last Service Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'bill_status',
                'label' => 'Bill Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'balance_age',
                'label' => 'Balance Age (Days)',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders_description',
                'label' => 'Notes',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Notes',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer1_id',
                'label' => 'Sales Rep 1',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sales Rep 1 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer2_id',
                'label' => 'Sales Rep 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sales Rep 2 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $pest[] = [
                'name' => $schemaTriggerDate->name,
                'label' => $schemaTriggerDate->name,
                'is_mandatory' => 0,
                'is_custom' => 1,
                'section_name' => 'Service History',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! PestSalesImportField::first()) {
            PestSalesImportField::insert($pest);
        }

        // FIBER SECTION
        $fiber = [
            [
                'name' => 'pid',
                'label' => 'PID',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'homeowner_id',
                'label' => 'Homeowner ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'product_id',
                'label' => 'Product',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_name',
                'label' => 'Customer Name',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address',
                'label' => 'Customer Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address_2',
                'label' => 'Customer Address 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_city',
                'label' => 'Customer City',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_state',
                'label' => 'Customer State',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_zip',
                'label' => 'Customer Zip',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_email',
                'label' => 'Customer Email',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_phone',
                'label' => 'Customer Phone',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'install_partner',
                'label' => 'Service Provider',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'length_of_agreement',
                'label' => 'Length Of Agreement',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'service_schedule',
                'label' => 'Service Schedule',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'gross_account_value',
                'label' => 'Gross Account Value',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'initial_service_cost',
                'label' => 'Initial Service Cost',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'subscription_payment',
                'label' => 'Subscription Payment',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'card_on_file',
                'label' => 'Card on file',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'auto_pay',
                'label' => 'Autopay',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'job_status',
                'label' => 'Job Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_signoff',
                'label' => 'Sale Date',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'date_cancelled',
                'label' => 'Cancel Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'service_completed',
                'label' => 'Services Completed',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'last_service_date',
                'label' => 'Last Service Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'bill_status',
                'label' => 'Bill Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'balance_age',
                'label' => 'Balance Age (Days)',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Service History',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders_description',
                'label' => 'Notes',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Notes',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer1_id',
                'label' => 'Sales Rep 1',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sales Rep 1 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer2_id',
                'label' => 'Sales Rep 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sales Rep 2 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $fiber[] = [
                'name' => $schemaTriggerDate->name,
                'label' => $schemaTriggerDate->name,
                'is_mandatory' => 0,
                'is_custom' => 1,
                'section_name' => 'Service History',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! FiberSalesImportField::first()) {
            FiberSalesImportField::insert($fiber);
        }

        // SOLAR SECTION
        $solar = [
            [
                'name' => 'pid',
                'label' => 'PID',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'homeowner_id',
                'label' => 'Homeowner ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'proposal_id',
                'label' => 'Proposal ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'product_id',
                'label' => 'Product',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_name',
                'label' => 'Customer Name',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address',
                'label' => 'Customer Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address_2',
                'label' => 'Customer Address 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_city',
                'label' => 'Customer City',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_state',
                'label' => 'Customer State',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'location_code',
                'label' => 'Location Code',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_zip',
                'label' => 'Customer Zip',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_email',
                'label' => 'Customer Email',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_phone',
                'label' => 'Customer Phone',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'install_partner',
                'label' => 'Installer',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'job_status',
                'label' => 'Job Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'gross_account_value',
                'label' => 'Gross Account Value',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'kw',
                'label' => 'KW',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'epc',
                'label' => 'EPC',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'net_epc',
                'label' => 'Net EPC',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'dealer_fee_percentage',
                'label' => 'Dealer Fee %',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'dealer_fee_amount',
                'label' => 'Dealer Fee $',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders',
                'label' => 'Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_signoff',
                'label' => 'Sale Date',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'date_cancelled',
                'label' => 'Cancel Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders_description',
                'label' => 'Notes',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Notes',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer1_id',
                'label' => 'Closer 1',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Closer 1 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'setter1_id',
                'label' => 'Setter 1',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Setter 1 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer2_id',
                'label' => 'Closer 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Closer 2 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'setter2_id',
                'label' => 'Setter 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Setter 2 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $solar[] = [
                'name' => $schemaTriggerDate->name,
                'label' => $schemaTriggerDate->name,
                'is_mandatory' => 0,
                'is_custom' => 1,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! SolarSalesImportField::first()) {
            SolarSalesImportField::insert($solar);
        }

        // TURF SECTION
        $turf = [
            [
                'name' => 'pid',
                'label' => 'PID',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'homeowner_id',
                'label' => 'Homeowner ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'product_id',
                'label' => 'Product',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_name',
                'label' => 'Customer Name',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address',
                'label' => 'Customer Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address_2',
                'label' => 'Customer Address 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_city',
                'label' => 'Customer City',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_state',
                'label' => 'Customer State',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_zip',
                'label' => 'Customer Zip',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_email',
                'label' => 'Customer Email',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_phone',
                'label' => 'Customer Phone',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'install_partner',
                'label' => 'Installer',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'job_status',
                'label' => 'Job Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'gross_account_value',
                'label' => 'Gross Account Value',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'kw',
                'label' => 'Sq Ft',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'epc',
                'label' => 'Gross $ / Sq ft',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'net_epc',
                'label' => 'Net $ / Sq ft',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'dealer_fee_percentage',
                'label' => 'Dealer Fee %',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'dealer_fee_amount',
                'label' => 'Dealer Fee $',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders',
                'label' => 'Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_signoff',
                'label' => 'Sale Date',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'date_cancelled',
                'label' => 'Cancel Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders_description',
                'label' => 'Notes',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Notes',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer1_id',
                'label' => 'Closer 1',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Closer 1 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'setter1_id',
                'label' => 'Setter 1',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Setter 1 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer2_id',
                'label' => 'Closer 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Closer 2 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'setter2_id',
                'label' => 'Setter 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Setter 2 email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $turf[] = [
                'name' => $schemaTriggerDate->name,
                'label' => $schemaTriggerDate->name,
                'is_mandatory' => 0,
                'is_custom' => 1,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! TurfSalesImportField::first()) {
            TurfSalesImportField::insert($turf);
        }

        // MORTGAGE SECTION
        $mortgage = [
            [
                'name' => 'pid',
                'label' => 'PID',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'homeowner_id',
                'label' => 'Homeowner ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'proposal_id',
                'label' => 'Proposal ID',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'product_id',
                'label' => 'Product',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'sale_product_name',
                'label' => 'Product Name',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Sale Info',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_name',
                'label' => 'Customer Name',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address',
                'label' => 'Customer Address',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_address_2',
                'label' => 'Customer Address 2',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_city',
                'label' => 'Customer City',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_state',
                'label' => 'Customer State',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'location_code',
                'label' => 'Location Code',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_zip',
                'label' => 'Customer Zip',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_email',
                'label' => 'Customer Email',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_phone',
                'label' => 'Customer Phone',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Customer Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'install_partner',
                'label' => 'Broker',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'service_schedule',
                'label' => 'Loan Type',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'job_status',
                'label' => 'Status',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'gross_account_value',
                'label' => 'Loan Amount',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'net_epc',
                'label' => 'Fee %',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'epc',
                'label' => 'Gross Revenue',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'float',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'dealer_fee_percentage',
                'label' => 'Appraisal Fee',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'dealer_fee_amount',
                'label' => 'Credit Report',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders',
                'label' => 'Broker Fee',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Job Details',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'customer_signoff',
                'label' => 'Closing Date',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'date_cancelled',
                'label' => 'Cancel Date',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'adders_description',
                'label' => 'Notes',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Dates',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer1_id',
                'label' => 'MLO',
                'is_mandatory' => 1,
                'is_custom' => 0,
                'section_name' => 'MLO email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'setter1_id',
                'label' => 'LOA',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'LOA email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'closer2_id',
                'label' => 'Coordinator',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Coordinator email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'setter2_id',
                'label' => 'Processor',
                'is_mandatory' => 0,
                'is_custom' => 0,
                'section_name' => 'Processor email',
                'field_type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        $schemaTriggerDates = SchemaTriggerDate::get();
        foreach ($schemaTriggerDates as $schemaTriggerDate) {
            $mortgage[] = [
                'name' => $schemaTriggerDate->name,
                'label' => $schemaTriggerDate->name,
                'is_mandatory' => 0,
                'is_custom' => 1,
                'section_name' => 'Dates',
                'field_type' => 'date',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! MortgageSalesImportField::first()) {
            MortgageSalesImportField::insert($mortgage);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pest_sales_import_fields');
        Schema::dropIfExists('solar_sales_import_fields');
        Schema::dropIfExists('turf_sales_import_fields');
        Schema::dropIfExists('fiber_sales_import_fields');
        Schema::dropIfExists('mortgage_sales_import_fields');
        Schema::dropIfExists('pest_sales_import_mapping');
        Schema::dropIfExists('solar_sales_import_mapping');
        Schema::dropIfExists('turf_sales_import_mapping');
        Schema::dropIfExists('fiber_sales_import_mapping');
        Schema::dropIfExists('mortgage_sales_import_mapping');
        Schema::dropIfExists('pest_sales_import_templates');
        Schema::dropIfExists('solar_sales_import_templates');
        Schema::dropIfExists('turf_sales_import_templates');
        Schema::dropIfExists('fiber_sales_import_templates');
        Schema::dropIfExists('mortgage_sales_import_templates');
    }
};
