<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserOverrideExport implements FromCollection, WithHeadings
{
    private $getFilterdata;

    public function __construct($getFilterdata)
    {
        $this->getFilterdata = $getFilterdata;
    }

    public function collection(): Collection
    {
        $payrollList = [];
        foreach ($this->getFilterdata as $value) {
            $payrollList[] = [
                'pid' => $value['pid'],
                'customer_name' => $value['customer_name'],
                'override_over' => $value['override_over'],
                'type' => $value['type'],
                'kw_installed' => $value['kw_installed'],
                'override' => isset($value['override']) ? (string) $value['override'] : '0',
                'total_override' => isset($value['total_override']) ? (string) $value['total_override'] : '0',
            ];
        }

        return collect($payrollList);
    }

    public function headings(): array
    {
        $companyProfile = CompanyProfile::first();
        $kw = 'KW Installed';
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $kw = 'Account Value';
        }

        return [
            'Pid',
            'Customer',
            'Override Over',
            'Type',
            $kw,
            'Override',
            'Total Earnings',
        ];
    }
}
