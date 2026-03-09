<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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

class PendingInstallExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    private $endDates;

    private $stateCode;

    private $officeId;

    private $search;

    // public function __construct($startDate=0, $endDate=0, $officeId)
    public function __construct($data)
    {
        $this->data = $data;

    }

    public function collection(): Collection
    {
        return collect($this->data);

    }

    public function headings(): array
    {
        return [

            'PID  ',
            'Customer Name  ',
            'Closer  ',
            'Installer  ',
            'KW  ',
            'EPC  ',
            'Net EPC  ',
            'Dealer Fee %  ',
            'Dealer Fee $  ',
            'Gross Account Value  ',
            'Total $  ',
            'M1 Date  ',
            'Status  ',
            'Age (Days)  ',
        ];
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
                    'KW ',

                    'Remaining Commission ',
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

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:N1')->applyFromArray([
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
        return 'Pending Install List';
    }
}
