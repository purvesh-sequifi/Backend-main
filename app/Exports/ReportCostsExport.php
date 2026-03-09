<?php

namespace App\Exports;

use App\Models\ApprovalsAndRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportCostsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $startDate;

    private $endDate;

    private $location;

    public function __construct($location = '', $startDate = '', $endDate = '')
    {
        $this->location = $location;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collectionOld(): Collection
    {
        if ($this->location != '' && $this->startDate != '' && $this->endDate != '') {
            if ($this->location == 'all') {
                $records = \DB::table('users as u')
                    ->select('u.id', 'u.first_name', 'u.last_name', 'u.image', 'u.position_id', 'u.state_id')
                    ->JOIN('states as s', 's.id', '=', 'u.state_id')
                    ->whereIn('u.position_id', [2, 3])
                    ->orderBy('u.id', 'asc')->get();
            } else {
                $records = \DB::table('users as u')
                    ->select('u.id', 'u.first_name', 'u.last_name', 'u.image', 'u.position_id', 'u.state_id')
                    ->JOIN('states as s', 's.id', '=', 'u.state_id')
                    ->where('s.state_code', '=', $this->location)
                    ->whereIn('u.position_id', [2, 3])
                    ->orderBy('u.id', 'asc')->get();
            }
        }
        /* return $records->transform(function($user) {
        $apprecords = ApprovalsAndRequest::with('adjustment', 'costcenter')
        ->where('user_id', $user->id)
        ->whereBetween('cost_date', [$this->startDate, $this->endDate])
        ->get();
        if(count($apprecords) > 0){
        dump($apprecords);
        foreach ($apprecords as $key1 => $value) {
        $result[] = array(
        'emp_name' => $user->first_name.' '.$user->last_name,
        'requested_on' => date('Y-m-d', strtotime($value->created_at)),
        'approved_by' => $value->manager_id,
        'amount' => $value->amount,
        'cost_tracking' => $value->costcenter->code,
        'description' => $value->description,
        );
        }
        return $result;
        }
        return [];
        });
        // dd("H");
        dd($records); */

        $result = [];
        foreach ($records as $user) {

            $apprecords = ApprovalsAndRequest::with('adjustment', 'costcenter')
                ->where('user_id', $user->id)
                ->whereBetween('cost_date', [$this->startDate, $this->endDate])
                ->get();
            if (count($apprecords) > 0) {
                foreach ($apprecords as $key1 => $value) {
                    $result[] = [
                        'emp_name' => $user->first_name.' '.$user->last_name,
                        'requested_on' => date('Y-m-d', strtotime($value->created_at)),
                        'approved_by' => $value->manager_id,
                        'amount' => $value->amount,
                        'cost_tracking' => $value?->costcenter?->code,
                        'description' => $value->description,
                    ];

                }
            }
        }

        return collect($result);
    }

    public function collection()
    {
        $users = $this->getFilteredUsers($this->location);
        $data = $this->getUserData($users, $this->startDate, $this->endDate, $this->location);

        return collect($data);
    }

    private function getFilteredUsers($location)
    {
        $users = DB::table('users as u')
            ->select('u.id', 'u.first_name', 'u.last_name', 'u.image', 'u.position_id', 'u.sub_position_id', 'u.is_super_admin', 'u.is_manager', 'u.state_id')
            ->join('states as s', 's.id', '=', 'u.state_id')
            ->whereIn('u.position_id', [1, 2, 3]);

        if ($location != 'all') {
            $userId = User::where('office_id', $location)->pluck('id');
            $users->whereIn('u.id', $userId);
        }

        if ($location != 'all' && 1 == 2) {
            $users->where('s.state_code', $location);
        }

        /*  if ($request->has('search')) {
             $search = $request->search;
             $users->where(function ($query) use ($search) {
                 $query->where('u.first_name', 'LIKE', '%' . trim($search) . '%')
                     ->orWhere('u.last_name', 'LIKE', '%' . trim($search) . '%')
                     ->orWhereRaw('CONCAT(u.first_name, " ", u.last_name) LIKE ?', ['%' . trim($search) . '%'])
                     ->orWhere('s.name', 'LIKE', '%' . $search . '%')
                     ->orWhere('s.state_code', 'LIKE', '%' . $search . '%');
             });
         } */

        return $users->get();
    }

    private function getUserData($users, $startDate, $endDate, $request)
    {
        $data = [];

        foreach ($users as $user) {
            $records = ApprovalsAndRequest::with('adjustment', 'costcenter')
                ->where('status', 'Approved')
                ->where('user_id', $user->id)
                ->whereBetween('cost_date', ["$startDate 00:00:00", "$endDate 00:00:00"])
                ->get();

            foreach ($records as $record) {
                $approvedByUser = $record->manager_id
                ? User::find($record->manager_id)
                : User::find($record->approved_by);

                $approvedBy = $approvedByUser ? $approvedByUser->first_name.' '.$approvedByUser->last_name : null;
                $image_s3 = $user->image ? s3_getTempUrl(config('app.domain_name').'/'.$user->image) : null;

                $data[] = [
                    'emp_id' => $user->id,
                    'position_id' => $user->position_id,
                    'sub_position_id' => $user->sub_position_id,
                    'is_super_admin' => $user->is_super_admin,
                    'is_manager' => $user->is_manager,
                    'emp_img' => $user->image,
                    'emp_img_s3' => $image_s3,
                    'emp_name' => $user->first_name.' '.$user->last_name,
                    'requested_on' => $record->request_date,
                    'approved_by' => $approvedBy,
                    'amount' => $record->amount,
                    'cost_tracking' => $record->costcenter?->code,
                    'description' => $record->description,
                ];
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Employee  ',
            'Requested on  ',
            'Approved By  ',
            'Amount  ',
            'Cost Tracking  ',
            'Description  ',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:Q1')->applyFromArray([
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
        return 'Costs List';
    }

    public function registerEvents(): array
    {
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');

                $this->alternateRowColorStyle($event);

                // Determine the index of the "KW" column
                $headers = $this->headings();

                /* $fieldsToFormat = [
                    "KW ",
                    "M1 ",
                    "M2 ",
                    "EPC ",
                    "Net EPC ",
                    self::ADDERS,
                    self::TOTAL_COMMISSION_HEADER,
                    "Sales Rep-1 ",
                    "Sales Rep-2 ",
                    "Gross Value ",
                    "Upfront ",
                    "Remaining Commission ",
                ];

                $fieldIndexes = [];
                foreach ($fieldsToFormat as $field) {
                    $index = array_search($field, $headers);
                    if ($index !== false) {
                        $fieldIndexes[$field] = $index + 1; // Adding 1 because Excel columns are 1-based
                    }
                }

                // Iterate through the collection data
                $rowCounter = 2; // Assuming data starts from the 2nd row
                foreach ($collectionData as $value) {
                    foreach ($fieldIndexes as $field => $columnIndex) {
                        if (isset($value[strtolower(str_replace(' ', '_', trim($field)))])) {
                            $fieldValue = $value[strtolower(str_replace(' ', '_', trim($field)))];
                            $this->setCellValueAndStyle($event, $columnIndex, $rowCounter, $fieldValue);
                        }
                    }
                    $rowCounter++;
                } */
            },
        ];
    }

    private function setCellValueAndStyle($event, $columnIndex, $rowCounter, $value)
    {
        $cell = $event->sheet->getDelegate()->getCellByColumnAndRow($columnIndex, $rowCounter);
        $cell->setValue('$ '.exportNumberFormat(floatval($value)));

        if ($value < 0) {
            $cell->setValue('$ ('.exportNumberFormat(abs(floatval($value))).')');
            $event->sheet->getDelegate()->getStyle($cell->getCoordinate())
                ->applyFromArray([
                    'font' => [
                        'color' => ['rgb' => 'FF0000'],
                    ],
                ]);
        }
    }

    private function alternateRowColorStyle($event)
    {
        // Define the colors for alternating rows
        $evenRowColor = 'f0f0f0'; // Light green
        $oddRowColor = 'FFFFFF'; // White
        $lastRow = $event->sheet->getDelegate()->getHighestDataRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            // Apply background color based on row parity
            $fillColor = $row % 2 == 0 ? $evenRowColor : $oddRowColor;
            $event->sheet->getStyle("$row:$row")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor],
                ],
            ]);
        }
        for ($i = 1; $i <= $lastRow; $i++) {
            for ($col = 'A'; $col != 'AC'; $col++) {
                $event->sheet->getStyle("$col$i")->applyFromArray([
                    'borders' => $this->borderStyle(),
                ]);
            }
        }
    }

    private function borderStyle()
    {
        return [
            'top' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
            'bottom' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
            'left' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
            'right' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'dadada'],
            ],
        ];
    }
}
