<?php

use App\Models\RoofingSalesImportField;
use App\Models\SchemaTriggerDate;
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
        if (! Schema::hasTable('roofing_sales_import_fields')) {
            Schema::create('roofing_sales_import_fields', function (Blueprint $table) {
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

        if (! Schema::hasTable('roofing_sales_import_templates')) {
            Schema::create('roofing_sales_import_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roofing_sales_import_template_details')) {
            Schema::create('roofing_sales_import_template_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->unsignedBigInteger('field_id')->nullable();
                $table->string('excel_field')->nullable();
                $table->timestamps();
            });
        }

        // ROOFING SECTION
        $roofing = [
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
            $roofing[] = [
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

        if (! RoofingSalesImportField::first()) {
            RoofingSalesImportField::insert($roofing);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roofing_sales_import_fields');
    }
};
