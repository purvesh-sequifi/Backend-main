<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OrganizationInfoSheetExport implements FromCollection, WithHeadings, WithTitle
{
    private $data;

    private $title;

    public function __construct($data, $title)
    {
        $this->data = $data;
        $this->title = $title;
    }

    /**
     * @return string[]
     */
    public function headings(): array
    {
        return [
            'Employee Id',
            'Office State',
            'Office Name',
            'Department',
            'Position',
            'Is Manager',
            'May act as both setter and closer',
            'Manager',
            'Team',
            'Recruiter',
            'Pay Type',
            'Pay Rate',
            'PTO Hours (Paid time off)',
            'Unused PTO',
            'Expected Weekly hours',
            'Hire Date',
            'Probation Period',
            'Offer includes bonus?',
            'Period of Agreement',
            'Offer Expiry Date',
            'Hiring Personnel',
            'Hiring Signature',
            'Deductions Effective Date',
            'Deductions',
            'Smart Fields',
            'Payroll Status',
            'User Status',
            'Termination Status',
            'Contract Status',
            'Login Status',
        ];
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
