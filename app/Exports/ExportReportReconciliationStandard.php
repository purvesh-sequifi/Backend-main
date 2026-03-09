<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportReportReconciliationStandard implements FromCollection, WithHeadings
{
    private $exportdata;

    public function __construct($exportdata)
    {
        $this->exportdata = $exportdata;
    }

    public function collection(): Collection
    {
        $data = $this->exportdata;
        $res = [];
        // dd($data['commission']['paid']);
        foreach ($data as $key => $val) {

            $res[] = [
                'payout_summary' => $key,
                'total_value' => isset($val['total_value']) ? $val['total_value'] : 0,
                'paid' => isset($val['paid']) ? $val['paid'] : 0,
                'held_back' => isset($val['held_back']) ? $val['held_back'] : 0,
                'due_amount' => isset($val['due_amount']) ? $val['due_amount'] : 0,
            ];

        }
        $res[] = [
            'total_due' => isset($data['total_due']) ? $data['total_due'] : 0,
        ];

        return collect($res);
    }

    public function headings(): array
    {
        return [
            'Payput Summary',
            'Total Value',
            'Paid',
            'Held Back',
            'Due Amount',
            'Total Due',
        ];
    }
}
