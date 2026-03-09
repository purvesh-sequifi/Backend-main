<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templateId = DB::table('fiber_sales_import_templates')->insertGetId([
            'name' => 'Fiber Default Template',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $excelFields = [
            'pid',
            'homeowner_id',
            'product_id',
            'sale_product_name',
            'customer_name',
            'customer_address',
            'customer_address_2',
            'customer_city',
            'customer_state',
            'customer_zip',
            'customer_email',
            'customer_phone',
            'install_partner',
            'length_of_agreement',
            'service_schedule',
            'gross_account_value',
            'initial_service_cost',
            'subscription_payment',
            'card_on_file',
            'auto_pay',
            'job_status',
            'customer_signoff',
            'date_cancelled',
            'service_completed',
            'last_service_date',
            'bill_status',
            'balance_age',
            'adders_description',
            'closer1_id',
            'closer2_id',
            'M1 Date',
            'M2 Date',
        ];

        $rows = [];

        foreach ($excelFields as $field) {
            $fieldId = DB::table('fiber_sales_import_fields')
                ->where('name', $field)
                ->value('id');

            if (! $fieldId) {
                \Log::warning("Field '{$field}' not found in fiber_sales_import_fields.");

                continue;
            }

            $rows[] = [
                'template_id' => $templateId,
                'field_id' => $fieldId,
                'excel_field' => $field,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('fiber_sales_import_template_details')->insert($rows);
    }

    public function down(): void
    {
        $templateId = DB::table('fiber_sales_import_templates')
            ->where('name', 'Fiber Default Template')
            ->value('id');

        if ($templateId) {
            DB::table('fiber_sales_import_template_details')
                ->where('template_id', $templateId)
                ->delete();

            DB::table('fiber_sales_import_templates')
                ->where('id', $templateId)
                ->delete();
        }
    }
};
