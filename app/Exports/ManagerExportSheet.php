<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ManagerExportSheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    public function __construct() {}

    public function collection(): Collection
    {
        $records = User::select('id', 'employee_id', 'first_name', 'last_name', 'email', 'mobile_no', 'office_id', 'manager_id_effective_date')->where('is_manager', 1)->get();

        $result = [];
        foreach ($records as $record) {

            $result[] = [
                'id' => $record->id,
                'first_name' => $record->first_name,
                'last_name' => $record->last_name,
                'email' => $record->email,
                'mobile_no' => $record->mobile_no,
                'office_id' => $record->office_id,
                'office_name' => $record->office->office_name ?? '',
                'manager_effective_date' => $record->manager_id_effective_date ?? '',
            ];
            // dd($result, $records[0]);
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Mobile No',
            'Office Id',
            'Office Name',
            'Manager Effective Date',
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
        return 'Managers';
    }
}
