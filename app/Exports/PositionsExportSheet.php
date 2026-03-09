<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use App\Models\Positions;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class PositionsExportSheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    private $startDate;

    private $endDate;

    private $officeId;

    private $m1;

    private $m2;

    private $closed;

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
        $records = Positions::query();
        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $records->whereNotIn('id', ['1', '2', '3']);
        }
        $records = $records->where('position_name', '!=', 'Super Admin')->get();
        $result = [];
        foreach ($records as $record) {
            $result[] = [
                'id' => $record->id,
                'position_name' => $record->position_name,
                'department_id' => $record->department_id,
                'department_name' => $record->departmentDetail->name ?? '',
                'parent_id' => isset($record->parent_id) ? $record->parent_id : null,
            ];
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Position Name',
            'Department Id',
            'Department Name',
            'Parent Id',
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
        return 'Position';
    }
}
