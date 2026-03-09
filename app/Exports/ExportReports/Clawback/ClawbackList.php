<?php

namespace App\Exports\ExportReports\Clawback;

use App\Models\ClawbackSettlement;
use App\Models\SalesMaster;
use App\Models\User;
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

class ClawbackList implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        if (isset($this->data['startDates']) && isset($this->data['endDates'])) {
            if ($this->data['officeId'] != 'all') {
                $officeId = $this->data['officeId'];
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = ClawbackSettlement::whereIn('user_id', $userId)->groupBy('pid')->pluck('pid')->toArray();

                $records = SalesMaster::with('salesMasterProcess', 'clawbackAmount')
                    ->whereIn('pid', $pid)
                    ->where('date_cancelled', '!=', null)
                    ->whereBetween('customer_signoff', [$this->data['startDates'], $this->data['endDates']]);

                if (isset($this->data['search']) && ! empty($this->data['search'])) {
                    $records->where(function ($query) {
                        return $query->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')
                            ->orWhere('date_cancelled', 'LIKE', '%'.$this->data['search'].'%')
                            ->orWhere('customer_state', 'LIKE', '%'.$this->data['search'].'%')
                            ->orWhere('net_epc', 'LIKE', '%'.$this->data['search'].'%');
                    });
                }
            } else {
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->toArray();
                $records = SalesMaster::with('salesMasterProcess')
                    ->where('date_cancelled', '!=', null)
                    ->whereBetween('customer_signoff', [$this->data['startDates'], $this->data['endDates']])
                    ->whereIn('pid', $pid)
                    ->orderBy('date_cancelled', 'desc');
                if (isset($this->data['search']) && ! empty($this->data['search'])) {
                    $records->where(function ($query) {
                        return $query->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')
                            ->orWhere('date_cancelled', 'LIKE', '%'.$this->data['search'].'%')
                            ->orWhere('customer_state', 'LIKE', '%'.$this->data['search'].'%')
                            ->orWhere('net_epc', 'LIKE', '%'.$this->data['search'].'%');
                    });
                }
            }
        } else {
            if ($this->data['officeId'] != 'all') {
                $officeId = $this->data['officeId'];
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->whereIn('user_id', $userId)->toArray();
            } else {
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->toArray();
            }

            $records = SalesMaster::with('salesMasterProcess', 'clawbackAmount')
                ->where('date_cancelled', '!=', null)
                ->whereIn('pid', $pid);
        }
        $result = $records->get();
        $result->transform(function ($response) {
            return [
                'customer_name' => isset($response->customer_name) ? $response->customer_name : null,
                'customer_state' => isset($response->customer_state) ? ucwords($response->customer_state) : null,
                'setter' => $response->salesMasterProcess?->setter1Detail?->first_name.' '.
                $response->salesMasterProcess?->setter1Detail?->last_name ?? '-',
                'closer' => $response->salesMasterProcess?->closer1Detail?->first_name.' '.
                $response->salesMasterProcess?->closer1Detail?->last_name ?? '-',
                'setter2' => $response->salesMasterProcess?->setter2Detail?->first_name.' '.
                $response->salesMasterProcess?->setter2Detail?->last_name ?? '-',
                'closer2' => $response->salesMasterProcess?->closer2Detail?->first_name.' '.
                $response->salesMasterProcess?->closer2Detail?->last_name ?? '-',
                'clawback_date' => $response->date_cancelled,
                'last_payment' => $response?->salesMasterProcess?->updated_at->format('Y-m-d'),
                'amount' => '$ '.$response?->clawbackAmount?->sum('clawback_amount') ?? '$0',
            ];
        });

        return $result;
    }

    public function registerEvents(): array
    {
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');

                $this->alternateRowColorStyle($event);

                // Determine the index of the "KW" column
                $headers = $this->headings();

                $fieldsToFormat = [
                    'Amount  ',
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
                }
            },
        ];
    }

    private function setCellValueAndStyle($event, $columnIndex, $rowCounter, $value)
    {
        $cell = $event->sheet->getDelegate()->getCellByColumnAndRow($columnIndex, $rowCounter);
        $numericValue = explode('$ ', $value);
        $cell->setValue('$ '.exportNumberFormat(floatval($numericValue[1])));

        if ($numericValue[1] < 0) {
            $cell->setValue('$ ('.exportNumberFormat(abs(floatval($numericValue[1]))).')');
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

    public function headings(): array
    {
        return [
            'Customer  ',
            'State  ',
            'Setter-1  ',
            'Closer-1  ',
            'Setter-2  ',
            'Closer-2  ',
            'Clawback Date ',
            'Last Payment  ',
            'Amount  ',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:I1')->applyFromArray([
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
        return 'Clawback List';
    }
}
