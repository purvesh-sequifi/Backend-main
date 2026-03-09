<?php

namespace App\Exports;

use App\Models\SalesMaster;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesExport implements FromCollection, WithHeadings
{
    private $startDates;

    private $endDates;

    public function __construct($startDate = 0, $endDate = 0)
    {
        $this->startDates = $startDate;
        $this->endDates = $endDate;
    }

    public function collection(): Collection
    {
        if ($this->startDates != 0 && $this->endDates != 0) {
            $records = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$this->startDates, $this->endDates])->get();
        } else {
            $records = SalesMaster::with('salesMasterProcess')->get();
        }

        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'id' => $record->id,
                'pid' => $record->pid,
                'customer_name' => $record->customer_name,
                'customer_state' => $record->customer_state,
                'setter' => isset($record->salesMasterProcess->setter1Detail->first_name) ? $record->salesMasterProcess->setter1Detail->first_name : null,
                'net_epc' => $record->net_epc,
                'kw' => $record->kw,
                'date_cancelled' => $record->date_cancelled,
                'm1_date' => $record->m1_date,
                'm1_amount' => $record->m1_amount,
                'm2_date' => $record->m2_date,
                'm2_amount' => $record->m2_amount,
            ];
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'Id No.',
            'PID',
            'Customer Name',
            'Customer state',
            'Setter',
            'Net Epc',
            'KW',
            'Cancel',
            'M1 Date',
            'M1 Amount',
            'M2 Date',
            'M2 Amount',
        ];
    }
}
