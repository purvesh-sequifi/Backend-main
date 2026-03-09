<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportReportSales implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $exportdata;

    public function __construct($exportdata)
    {
        $this->exportdata = $exportdata;
    }

    public function collection(): Collection
    {
        $res = [];
        $data = $this->exportdata;
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            foreach ($data as $val) {
                $res[] = [
                    'pid' => $val['pid'],
                    'customer_name' => isset($val['customer_name']) ? $val['customer_name'] : null,
                    'customer_address' => isset($val['customer_address']) ? $val['customer_address'] : null,
                    'customer_city' => isset($val['customer_city']) ? $val['customer_city'] : null,
                    'customer_state' => isset($val['state']) ? $val['state'] : null,
                    'customer_zip' => isset($val['customer_zip']) ? $val['customer_zip'] : null,
                    'customer_email' => isset($val['customer_email']) ? $val['customer_email'] : null,
                    'customer_phone' => isset($val['customer_phone']) ? $val['customer_phone'] : null,
                    'closer' => isset($val['closer']) ? $val['closer'] : null,
                    'account_value' => isset($val['gross_account_value']) ? round($val['gross_account_value'], 5) : null,
                    'product' => isset($val['product']) ? $val['product'] : null,
                    'status' => isset($val['status']) ? $val['status'] : null,
                    'last_milestone' => isset($val['last_milestone']) && ! empty($val['last_milestone']['name']) && ! empty($val['last_milestone']['date']) ? $val['last_milestone']['name'].', '.dateToYMD($val['last_milestone']['date']) : null,
                    'date_cancelled' => isset($val['date_cancelled']) ? dateToYMD($val['date_cancelled']) : null,
                    'm1_date' => isset($val['m1_date']) ? dateToYMD($val['m1_date']) : null,
                    'm2_date' => isset($val['m2_date']) ? dateToYMD($val['m2_date']) : null,
                    'adders' => isset($val['adders']) ? $val['adders'] : '',
                    'dealer_fee' => isset($val['dealer_fee']) ? $val['dealer_fee'] : '',
                    'dealer_fee_percentage' => isset($val['dealer_fee_percentage']) ? $val['dealer_fee_percentage'] : null,
                    'job_status' => isset($val['job_status']) ? $val['job_status'] : null,
                ];
            }
        } else {
            foreach ($data as $val) {
                $res[] = [
                    'pid' => $val['pid'],
                    'installer' => $val['installer'],
                    'customer_name' => isset($val['customer_name']) ? $val['customer_name'] : null,
                    'customer_address' => isset($val['customer_address']) ? $val['customer_address'] : null,
                    'customer_city' => isset($val['customer_city']) ? $val['customer_city'] : null,
                    'customer_state' => isset($val['state']) ? $val['state'] : null,
                    'customer_zip' => isset($val['customer_zip']) ? $val['customer_zip'] : null,
                    'customer_email' => isset($val['customer_email']) ? $val['customer_email'] : null,
                    'customer_phone' => isset($val['customer_phone']) ? $val['customer_phone'] : null,
                    'product' => isset($val['product']) ? $val['product'] : null,
                    'closer' => isset($val['closer']) ? $val['closer'] : null,
                    'setter' => isset($val['setter']) ? $val['setter'] : null,
                    'kw' => isset($val['kw']) ? round($val['kw'], 5) : null,
                    'epc' => isset($val['epc']) ? $val['epc'] : null,
                    'net_epc' => isset($val['net_epc']) ? $val['net_epc'] : null,
                    'status' => isset($val['status']) ? $val['status'] : null,
                    'last_milestone' => isset($val['last_milestone']) && ! empty($val['last_milestone']['name']) && ! empty($val['last_milestone']['date']) ? $val['last_milestone']['name'].', '.dateToYMD($val['last_milestone']['date']) : null,
                    'date_cancelled' => isset($val['date_cancelled']) ? dateToYMD($val['date_cancelled']) : null,
                    'm1_date' => isset($val['m1_date']) ? dateToYMD($val['m1_date']) : null,
                    'm2_date' => isset($val['m2_date']) ? dateToYMD($val['m2_date']) : null,
                    'adders' => isset($val['adders']) ? $val['adders'] : '',
                    'dealer_fee' => isset($val['dealer_fee']) ? $val['dealer_fee'] : '',
                    'dealer_fee_percentage' => isset($val['dealer_fee_percentage']) ? $val['dealer_fee_percentage'] : null,
                    'gross_account_value' => isset($val['gross_account_value']) ? $val['gross_account_value'] : null,
                    'job_status' => isset($val['job_status']) ? $val['job_status'] : null,
                ];
            }
        }

        return collect($res);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');
            },
        ];
    }

    public function headings(): array
    {
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            return [
                'PID',
                'Customer Name',
                'Customer Street Address',
                'Customer City',
                'Customer state',
                'Customer Zip Code',
                'Customer Email',
                'Customer Phone',
                'Sales Rep name',
                'Account Value',
                'Product',
                'Status',
                'Last Milestone',
                'Cancel Date',
                'Initial Service Date',
                'Service Complete Date',
                'Adders',
                'Dealer Fee',
                'Dealer Fee percentage',
                'Job Status',
            ];
        } else {
            return [
                'PID',
                'Installer',
                'Customer Name',
                'Customer Street Address',
                'Customer City',
                'Customer state',
                'Customer Zip Code',
                'Customer Email',
                'Customer Phone',
                'Product',
                'Closer name',
                'Seter name',
                'Kw',
                'EPC',
                'Net EPC',
                'Status',
                'Last Milestone',
                'Cancel Date',
                'M1 Date',
                'M2 Date',
                'Adders',
                'Dealer Fee',
                'Dealer Fee percentage',
                'Gross Account Value',
                'Job Status',
            ];
        }
    }

    public function styles(Worksheet $sheet)
    {
        $data = $this->exportdata;
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $sheet->getStyle('A1:S1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => '999999', // Background color (light gray)
                    ],
                ],
            ]);
        } else {
            $sheet->getStyle('A1:W1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => '999999', // Background color (light gray)
                    ],
                ],
            ]);
        }
    }

    public function title(): string
    {
        return 'Sales List';
    }
}
