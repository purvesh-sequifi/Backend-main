<?php

namespace App\Exports;

use App\Models\Locations;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class OfficeExportSheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    private $startDate;

    private $endDate;

    private $officeId;

    private $m1;

    private $m2;

    private $closed;

    private $salesMaster;

    private $search;

    public function __construct($officeId = '', $startDate = '', $endDate = '', $m1 = '', $m2 = '', $closed = '', $search = '')
    {

        $this->officeId = $officeId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->m1 = $m1;
        $this->m2 = $m2;
        $this->closed = $closed;
        $this->search = $search;

    }

    public function collection(): Collection
    {
        $records = Locations::get();

        $result = [];
        foreach ($records as $record) {

            $result[] = [
                'id' => $record->id,
                'office_name' => $record->office_name,
                'general_code' => $record->general_code,
                // 'redline_standard' =>isset($record->redline_standard)?$record->redline_standard:null,
                'state_id' => $record->state_id,
                'state_name' => $record->State->name,
            ];
            // dd($result);
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Office Name',
            'General Code',
            // 'Redline Standard',
            'State Id',
            'State Name',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $event->sheet->getDelegate()->getStyle('A1:P1')
                    ->getFont()
                    ->setBold(true);

            },
        ];
    }

    public function title(): string
    {
        return 'Office';
    }
}
