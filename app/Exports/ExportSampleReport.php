<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportSampleReport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        $val[] = [
            'id' => '',
            'legacy_data_id' => '',
            'aveyo_hs_id' => '',
            'prospect_id' => '1729',
            'page' => '',
            'weekly_sheet_id' => '',
            'homeowner_id' => '',
            'proposal_id' => '',
            'customer_name' => 'DAVID HOUDYSHELL SR',
            'customer_address' => '3319 2ND ST',
            'customer_address_2' => '',
            'customer_city' => '',
            'customer_state' => 'IL',
            'customer_zip' => '61244',
            'customer_email' => 'ribbuster50@yahoo.com',
            'customer_phone' => '(309)798-3827',
            'setter_id' => '9',
            'employee_id' => '',
            'rep_name' => 'Will Smith',
            'rep_email' => 'will.smith@flexpwr.org',
            'install_partner' => 'Blue Sky',
            'install_partner_id' => '',
            'customer_signoff' => '2023-06-30',
            'm1' => '',
            'scheduled_install' => '',
            'install_complete_date' => '',
            'm2' => '',
            'date_cancelled' => '',
            'return_sales_date' => '',
            'gross_account_value' => '19327.02',
            'cash_amount' => '',
            'loan_amount' => '',
            'kw' => '4.8',
            'dealer_fee_percentage' => '1',
            'adders' => '',
            'cancel_fee' => '',
            'adders_description' => '',
            'funding_source' => '',
            'financing_rate' => '',
            'financing_term' => '',
            'product' => '',
            'epc' => '4.03',
            'net_epc' => '4.03',
            'source_created_at' => '',
            'source_updated_at' => '',
            'data_source_type' => '',
            'created_at' => '',
            'updated_at' => '',
        ];

        return collect($val);
    }

    public function headings(): array
    {
        return [
            'id',
            'legacy_data_id',
            'aveyo_hs_id',
            'pid',
            'page',
            'weekly_sheet_id',
            'homeowner_id',
            'proposal_id',
            'customer_name',
            'customer_address',
            'customer_address_2',
            'customer_city',
            'customer_state',
            'customer_zip',
            'customer_email',
            'customer_phone',
            'setter_id',
            'employee_id',
            'sales_rep_name',
            'sales_rep_email',
            'install_partner',
            'install_partner_id',
            'customer_signoff',
            'm1_date',
            'scheduled_install',
            'install_complete_date',
            'm2_date',
            'date_cancelled',
            'return_sales_date',
            'gross_account_value',
            'cash_amount',
            'loan_amount',
            'kw',
            'dealer_fee_percentage',
            'adders',
            'cancel_fee',
            'adders_description',
            'funding_source',
            'financing_rate',
            'financing_term',
            'product',
            'epc',
            'net_epc',
            'source_created_at',
            'source_updated_at',
            'data_source_type',
            'created_at',
            'updated_at',
        ];
    }
}
