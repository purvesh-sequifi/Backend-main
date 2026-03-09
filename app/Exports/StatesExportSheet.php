<?php

namespace App\Exports;

use App\Models\State;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class StatesExportSheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    public function __construct() {}

    public function collection(): Collection
    {
        $records = State::where('state_code', '!=', null)->get();

        $result = [];
        foreach ($records as $record) {

            $result[] = [
                'id' => $record->id,
                'name' => $record->name,
                'state_code' => $record->state_code,
            ];
            // dd($result);
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'ID',
            'State Name',
            'State Code',
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
        return 'State';
    }
}
