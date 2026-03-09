<?php

namespace App\Exports;

use App\Models\Department;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class DepartmentExportSheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    public function __construct() {}

    public function collection(): Collection
    {
        $records = Department::whereIn('id', [1, 2, 4])->get();

        $result = [];
        foreach ($records as $record) {

            $result[] = [
                'id' => $record->id,
                'name' => $record->name,
            ];
            // dd($result);
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Department Name',
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
        return 'Department';
    }
}
