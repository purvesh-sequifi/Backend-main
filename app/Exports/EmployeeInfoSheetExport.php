<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class EmployeeInfoSheetExport implements FromCollection, WithHeadings, WithTitle
{
    private $data;

    private $title;

    private $mileStonesNames = [];

    public function __construct($data, $title, $mileStonesNames)
    {
        $this->data = $data;
        $this->title = $title;
        $this->mileStonesNames = $mileStonesNames;
    }

    /**
     * @return string[]
     */
    public function headings(): array
    {
        $companyProfile = CompanyProfile::first();

        $columns = [
            'Employee Id',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Office State',
            'Office Name',
            'Department',
            'Position',
            'Since',
            'Manager',
            'Team',
            'Is Manager?',
            'Setter/Closer',
            'Since',
            'Recruiter',
            'Additional Recruiter1',
            'Additional Recruiter2',
            'Additional Location',
            'Self Gen Commission Effective Date',
            'Self Gen Commission',
            'Setter Commission Effective Date',
            'Setter Commission',
        ];

        // checking for redline if company type if solar for setter
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $redlinesColumns = [
                'Setter Redline Effective Date',
                'Setter Redline',
                'Setter Redline Type',
            ];
            $columns = array_merge($columns, $redlinesColumns);
        }

        \Log::debug($this->title.' has setterUpFront '.count($this->mileStonesNames));
        foreach ($this->mileStonesNames as $upName) {
            $upfrontColumns = [
                'Setter '.$upName.' Effective Date ',
                'Setter '.$upName.' Amount',
            ];
            $columns = array_merge($columns, $upfrontColumns);
        }
        $columns2 = [
            'Setter Withheld Effective Date',
            'Setter Withheld Amount',
            'Closer Commission Effective Date',
            'Closer Commission',
        ];
        $columns = array_merge($columns, $columns2);

        // checking for redline if company type if solar for closer
        if ($companyProfile->company_type == CompanyProfile::SOLAR_COMPANY_TYPE || $companyProfile->company_type == CompanyProfile::SOLAR2_COMPANY_TYPE) {
            $redlinesColumns = [
                'Closer Redline Effective Date',
                'Closer Redline',
                'Closer Redline Type',
            ];
            $columns = array_merge($columns, $redlinesColumns);
        }

        \Log::debug($this->title.' has closerUpFront '.count($this->mileStonesNames));
        foreach ($this->mileStonesNames as $upName) {
            $upfrontColumns = [
                'Closer '.$upName.' Effective Date ',
                'Closer '.$upName.' Amount',
            ];
            $columns = array_merge($columns, $upfrontColumns);
        }

        $columns3 = [
            'Closer Withheld Effective Date',
            'Closer Withheld Amount',
            'Override Effective Date',
            'Direct Overrides',
            'Indirect Overrides',
            'Office Overrides',
            'Office Stack',
            'Hire Date',
            'Probation Period',
            'Offer includes bonus?',
            'Period of Agreement',
            'Offer Expiry Date',
        ];
        $columns = array_merge($columns, $columns3);

        // Adding fields in the last of excel by maintaining separate array
        $columns4 = [
            'Payroll Status',
            'User Status',
            'Termination Status',
            'Contract Status',
            'Login Status',
        ];
        $columns = array_merge($columns, $columns4);

        return $columns;
    }

    public function collection(): Collection
    {
        return new Collection([$this->data]);
    }

    public function title(): string
    {
        return $this->title;
    }
}
