<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BankingInfoSheetExport implements FromCollection, WithHeadings, WithTitle
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
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Name of Bank',
            'Account Number',
            'Routing Number',
            'Account Name',
            'Type of Account',
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
