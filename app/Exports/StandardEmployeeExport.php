<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StandardEmployeeExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public $office;

    public $filter;

    public function __construct($office = 0, $filter = 0)
    {

        $this->office = $office;
        $this->filter = $filter;

    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');
            },
        ];
    }

    public function collection(): Collection
    {
        $user = User::where('dismiss', 0)->where('is_super_admin', '!=', 1)->orderBy('id', 'desc');

        if (! empty($this->office)) {
            $officeId = $this->office;

            if ($officeId != 'all') {

                $user->where(function ($query) use ($officeId) {
                    return $query->where('office_id', $officeId);
                });
            }

        }

        if ($this->filter && ! empty($this->filter)) {
            $search = $this->filter;
            $user->whereHas(
                'positionDetail', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ['%'.$search.'%'])
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('mobile_no', 'like', '%'.$search.'%')
                        ->orWhere('position_name', 'like', '%'.$search.'%');
                })
                ->orWhereHas('additionalEmails', function ($query) use ($search) {
                    $query->where('email', 'like', '%'.$search.'%');
                });
        }

        $totalAdminUsersStatus = '';
        $data = $user->with('positionDetail', 'office', 'departmentDetail')->get();
        // $records = User::with('additionalLocations','office','state','departmentDetail','managerDetail')->orderBy('id','desc')->get();
        $data->transform(function ($result) {
            return [
                'employee_id' => $result?->employee_id,
                'first_name' => $result?->first_name,
                'last_name' => $result?->last_name,
                'department' => $result?->departmentDetail?->name,
                'postition' => $result?->positionDetail?->position_name,
                'state' => $result?->state?->name,
                'office_name' => $result?->office?->office_name,
                'phone' => $result?->mobile_no,
                'email' => $result?->email,
                'hire_date' => date('m/d/Y', strtotime($result?->period_of_agreement_start_date)),
            ];
        });

        return $data;
        /* foreach ($data as $record) {
            $result = [];
            if ($record->dismiss == 0) {
                $status = 'Active';
            } else {
                $status = 'Inactive';
            }

            $result[] = array(
                'employee_id' => isset($record->employee_id) ? $record->employee_id : "",
                'first_name' => $record->first_name . ' ' . $record->last_name,
                'last_name' => isset($record->last_name) ? $record->last_name : "",
                'department' => isset($record->departmentDetail->name) ? $record->departmentDetail->name : "",
                'postition' => isset($record->positionDetail->position_name) ? $record->positionDetail->position_name : "",
                'state' => isset($record->state->name) ? $record->state->name : "",
                'office_name' => isset($record->office->office_name) ? $record->office->office_name : "",
                'phone' => isset($record->mobile_no) ? $record->mobile_no : "",
                'email' => isset($record->email) ? $record->email : null,
                'hire_date' => isset($record->period_of_agreement_start_date) ? $record->period_of_agreement_start_date : null,
            );
        }
        return collect($result); */
    }

    public function headings(): array
    {
        return [
            'Employee ID',
            'First Name',
            'Last Name',
            'Department',
            'Position',
            'State',
            'Office',
            'Phone',
            'Email',
            'Hire Date',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999', // Background color (light gray)
                ],
            ],
        ]);
    }

    public function title(): string
    {
        return 'Customer Report';
    }
}
