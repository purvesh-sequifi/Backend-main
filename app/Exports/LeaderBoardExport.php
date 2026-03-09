<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeaderBoardExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private $data;

    private $type;

    private $companyProfile;

    public function __construct($data, $type, $companyProfile)
    {
        $this->data = $data;
        $this->type = $type;
        $this->companyProfile = $companyProfile;
    }

    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            unset($item['unknown'], $item['cancelled'], $item['clawback']);

            return $item;
        });
    }

    public function headings(): array
    {
        $companyProfile = $this->companyProfile;
        if ($this->type == 'user') {
            if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                return [
                    'Rank',
                    'User Name',
                    'Office Name',
                    'Sold',
                    'Installed',
                    'Pending',
                    'Completion Rate',
                    'Sq. Ft. Sold',
                    'Sq. Ft. Installed',
                    'Avg. Sq. Ft.',
                ];
            } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                return [
                    'Rank',
                    'User Name',
                    'Office Name',
                    'Sold',
                    'Installed',
                    'Pending',
                    'Service Rate',
                    'Value Sold',
                    'Value Serviced',
                    'Avg. Contract Value',
                ];
            } elseif ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                return [
                    'Rank',
                    'User Name',
                    'Office Name',
                    'Sold',
                    'Installed',
                    'Pending',
                    'Completion Rate',
                    'KW Sold',
                    'KW Installed',
                    'Avg. Net EPC',
                ];
            }
        } elseif ($this->type == 'office') {
            if ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                return [
                    'Rank',
                    'Office Name',
                    'No. of Employees',
                    'Sold',
                    'Installed',
                    'Pending',
                    'Completion Rate',
                    'Sq. Ft. Sold',
                    'Sq. Ft. Installed',
                    'Avg. Sq. Ft.',
                ];
            } elseif (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                return [
                    'Rank',
                    'Office Name',
                    'No. of Employees',
                    'Sold',
                    'Installed',
                    'Pending',
                    'Service Rate',
                    'Value Sold',
                    'Value Serviced',
                    'Avg. Contract Value',
                ];
            } elseif ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
                return [
                    'Rank',
                    'Office Name',
                    'No. of Employees',
                    'Sold',
                    'Installed',
                    'Pending',
                    'Completion Rate',
                    'KW Sold',
                    'KW Installed',
                    'Avg. Net EPC',
                ];
            }
        }

        return [];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999',
                ],
            ],
        ]);
    }

    public function title(): string
    {
        return 'Leaderboard';
    }
}
