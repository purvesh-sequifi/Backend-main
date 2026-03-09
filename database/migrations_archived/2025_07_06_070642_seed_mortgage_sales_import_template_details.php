<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templateId = DB::table('mortgage_sales_import_templates')->insertGetId([
            'name' => 'Mortgage Default Template',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $excelFields = [
            'pid',
            'homeowner_id',
            'proposal_id',
            'product_id',
            'sale_product_name',
            'customer_name',
            'customer_address',
            'customer_address_2',
            'customer_city',
            'customer_state',
            'location_code',
            'customer_zip',
            'customer_email',
            'customer_phone',
            'install_partner',
            'service_schedule',
            'job_status',
            'gross_account_value',
            'net_epc',
            'epc',
            'dealer_fee_percentage',
            'dealer_fee_amount',
            'adders',
            'customer_signoff',
            'date_cancelled',
            'adders_description',
            'closer1_id',
            'setter1_id',
            'closer2_id',
            'setter2_id',
            'M1 Date',
            'M2 Date',
        ];

        $rows = [];

        foreach ($excelFields as $field) {
            $fieldId = DB::table('mortgage_sales_import_fields')
                ->where('name', $field)
                ->value('id');

            if (! $fieldId) {
                \Log::warning("Field '{$field}' not found in mortgage_sales_import_fields.");

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

        DB::table('mortgage_sales_import_template_details')->insert($rows);
    }

    public function down(): void
    {
        $templateId = DB::table('mortgage_sales_import_templates')
            ->where('name', 'Mortgage Default Template')
            ->value('id');

        if ($templateId) {
            DB::table('mortgage_sales_import_template_details')
                ->where('template_id', $templateId)
                ->delete();

            DB::table('mortgage_sales_import_templates')
                ->where('id', $templateId)
                ->delete();
        }
    }
};
