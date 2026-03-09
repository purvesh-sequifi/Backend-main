<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Jobssalesexports implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    private $companyProfile;

    public function __construct($data, $companyProfile)
    {
        $this->data = $data;
        $this->companyProfile = $companyProfile;
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

    public function collection()
    {
        $data = $this->data;
        // echo "<pre>";print_r($data);die();
        if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $data->transform(function ($result) {
                return [
                    'PID' => $result['pid'],
                    'Customer' => $result['customer_name'],
                    'Source' => $result['source'],
                    'Job Status' => $result['job_status'],
                    'State' => $result['state'],
                    'Closer-1' => $result['closer_1'] ?? '',
                    'Closer-2' => $result['closer_2'] ?? '',
                    'Gross Value' => $result['gross_account_value'],
                    'Upfront' => '$'.$result['m1'] ?? '$0.00',
                    'Remaining Commission' => '$'.$result['m2'] ?? '$0.00',
                    'Adders' => $result['adders'],
                    'Total Commission' => '$'.$result['total_commission'] ?? '$0.00',
                    'Payment Status' => $result['status'],
                    'Days' => $result['days'] ?? '0',
                    'Bucket' => $result['bucket_id'],
                ];
            });
        } else {
            $data->transform(function ($result) {
                return [
                    'PID' => $result['pid'],
                    'Customer' => $result['customer_name'],
                    'Source' => $result['source'],
                    'Job Status' => $result['job_status'],
                    'State' => $result['state'],
                    'Closer-1' => $result['closer_1'] ?? '',
                    'Closer-2' => $result['closer_2'] ?? '',
                    'Setter-1' => $result['setter_1'] ?? '',
                    'Setter-2' => $result['setter_2'] ?? '',
                    'KW' => $result['kw'],
                    'M1' => '$'.$result['m1'] ?? '$0.00',
                    'M2' => '$'.$result['m2'] ?? '$0.00',
                    'EPC' => '$'.$result['epc'] ?? '$0.00',
                    'Net EPC' => '$'.$result['net_epc'] ?? '$0.00',
                    'Adders' => $result['adders'],
                    'Total Commission' => '$'.$result['total_commission'] ?? '$0.00',
                    'Payment Status' => $result['status'],
                    'Days' => $result['days'] ?? '0',
                    'Bucket' => $result['bucket_id'],

                ];
            });
        }

        // echo "<pre>";print_r($data);die();
        return $data;

    }

    public function headings(): array
    {
        if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            return [
                'PID',
                'Customer',
                'Source',
                'Job Status',
                'State',
                'Sales Rep-1',
                'Sales Rep-2',
                'Gross Value',
                'Upfront',
                'Remaining Commission',
                'Adders',
                'Total Commission',
                'Payment Status',
                'Days',
                'Bucket',

            ];
        } else {
            return [
                'PID',
                'Customer',
                'Source',
                'Job Status',
                'State',
                'Closer-1',
                'Closer-2',
                'Setter-1',
                'Setter-2',
                'KW',
                'M1',
                'M2',
                'EPC',
                'Net EPC',
                'Adders',
                'Total Commission',
                'Payment Status',
                'Days',
                'Bucket',

            ];
        }
    }

    public function styles(Worksheet $sheet)
    {
        if (in_array($this->companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $sheet->getStyle('A1:O1')->applyFromArray([
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
        }
    }

    public function title(): string
    {
        return 'Jobs List';
    }
}
