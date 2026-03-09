<?php

namespace App\Exports\ExportRecon;

use App\Models\ReconciliationFinalizeHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserReconListExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    private $request;

    private $rowCounter;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): Collection
    {
        $startDate = $this->request->start_date;
        $endDate = $this->request->end_date;
        $search = $this->request->search;
        $sentId = $this->request->sent_id;

        $data = $this->buildQuery($startDate, $endDate, $search, $sentId)->get();

        return collect($this->transformData($data, $startDate, $endDate));
    }

    private function buildQuery($startDate, $endDate, $search, $sentId)
    {
        $query = ReconciliationFinalizeHistory::where('finalize_count', $sentId)->where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->whereIn('status', ['payroll', 'clawback'])
            ->groupBy('user_id');

        if ($search) {
            $query->whereHas('user', function ($query) use ($search) {
                $query->where('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        return $query->with('user');
    }

    private function transformData($data, $startDate, $endDate)
    {
        return $data->transform(function ($data) {
            $commission = $data->paid_commission ?? 0;
            $overrideDue = $data->paid_override ?? 0;
            $clawbackDue = $data->clawback ?? 0;
            $totalAdjustments = $data->adjustments - $data->deductions;
            $totalDue = $data->gross_amount;
            $netPay = $totalAdjustments + $clawbackDue;

            // $calculations ÷= $this->calculateSums($startDate, $endDate, $data->user_id);
            return [
                'id' => $data?->user->employee_id,
                /* 'user_id' => $data->user_id, */
                'emp_name' => isset($data->user->first_name) ? ucfirst($data->user->first_name).' '.ucfirst($data->user->last_name) : null,
                'commissionWithholding' => "$ {$commission}",
                'overrideDue' => "$ {$overrideDue}",
                'total_due' => /* $data->net_amount */ '$ '.floatval($commission + $overrideDue),
                'pay' => '$ '.floatval($data->payout),
                'total_pay' => '$ '.floatval($commission + $overrideDue),
                'clawbackDue' => '$ '.floatval($clawbackDue),
                'totalAdjustments' => '$ '.floatval($totalAdjustments),
                'payout' => '$ '.$netPay + ($commission + $overrideDue),
                // 'status' => $data->status,
            ];
        });
    }

    private function calculateSums($startDate, $endDate, $user_id)
    {
        $userCalculation = ReconciliationFinalizeHistory::where('start_date', $startDate)
            ->where('end_date', $endDate)
            ->where('status', 'payroll')
            ->where('user_id', $user_id);

        return [
            'commission' => $userCalculation->sum('paid_commission'),
            'overrideDue' => $userCalculation->sum('paid_override'),
            'clawbackDue' => $userCalculation->sum('clawback'),
            'totalAdjustments' => $userCalculation->sum('adjustments'),
            'totalDue' => $userCalculation->sum('gross_amount'),
            'netPay' => $userCalculation->sum('net_amount'),
        ];
    }

    public function headings(): array
    {
        return [
            [
                'Pay Period:',
                Carbon::parse($this->request->start_date)->format('m/d/Y').' - '.Carbon::parse($this->request->end_date)->format('m/d/Y'),
            ],
            [
                'Id No.  ',
                /* 'User Id', */
                'Employee Name  ',
                'Commissions Withheld  ',
                'Override Due ',
                'Total Due ',
                'Percentage ',
                'Total Pay ',
                'Clawback  ',
                'Adjustments  ',
                'Payout  ',
                // 'Status  ',

            ]];
    }

    /* Set style sheet */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:J2')->applyFromArray([
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

    /* set footer value */
    public function registerEvents(): array
    {
        $secondHeader = [
            'Total',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '$ '.($this->totalAmount()),
        ];
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData, $secondHeader) {
                $event->sheet->freezePane('A3');
                $worksheet = $event->sheet->getDelegate();
                $this->styleRows($event, $collectionData);

                $worksheet->mergeCells('B1:C1');
                // Get the last row index
                $lastRowIndex = $worksheet->getHighestDataRow();
                $worksheet->insertNewRowBefore($lastRowIndex + 1, 1);
                // Populate the second header row with data
                $worksheet->fromArray([$secondHeader], null, 'A'.($lastRowIndex + 2), true);
                $footerColumns = ['A'.($lastRowIndex + 2), 'J'.($lastRowIndex + 2), 'K'.($lastRowIndex + 2)];
                foreach ($footerColumns as $value) {
                    $event->sheet->getStyle($value)->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                    ]);
                }

                /* data column styling */
                /* $rowCounter = 3;
                foreach ($collectionData as $val) {
                if (!empty($val["payout"])) {
                $payValue1 = explode("$ ", $val["payout"]);
                $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));
                // Calculate the cell address
                $cellAddress1 = "J" . $rowCounter;
                $event->sheet->setCellValue($cellAddress1, "$ " . exportNumberFormat($totalPay1));
                // Apply styles based on the condition
                $styleArray1 = [
                'font' => [
                'bold' => false,
                "size" => 12,
                ],
                ];
                if ($totalPay1 < 0) {
                $event->sheet->setCellValue($cellAddress1, "$ (" . exportNumberFormat(abs($totalPay1)) . ")");
                // Set text color to red for negative values
                $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }

                $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);
                }
                $rowCounter++;
                } */

                /* alternate row color */
                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();

                // Define the colors for alternating rows
                $evenRowColor = 'f0f0f0'; // Light green
                $oddRowColor = 'FFFFFF'; // White

                for ($row = 1; $row <= $lastRow; $row++) {
                    // Apply background color based on row parity
                    $fillColor = $row % 2 == 0 ? $evenRowColor : $oddRowColor;
                    $event->sheet->getStyle("$row:$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $fillColor],
                        ],
                    ]);
                }
                for ($i = 2; $i <= $lastRow; $i++) {
                    for ($col = 'A'; $col != 'AC'; $col++) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle(),
                        ]);
                    }
                }

                /* Set footer values */
                $event->sheet->setCellValue('A'.$lastRowIndex + 2, 'Total');
                $event->sheet->setCellValue('J'.$lastRowIndex + 2, '$ '.exportNumberFormat($this->totalAmount()));
                $styleArray['font'] = ['bold' => true]; // Red color
                if ($this->totalAmount() < 0) {
                    $event->sheet->setCellValue('J'.$lastRowIndex + 2, '$ ('.exportNumberFormat(abs($this->totalAmount())).')');
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle('J'.$lastRowIndex + 2)->applyFromArray($styleArray);
            },
        ];
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

    private function totalAmount()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if (! empty($value['payout'])) {
                $payValue = explode('$ ', $value['payout']);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }

        return array_sum($totalPay);
    }

    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $this->rowCounter = 3; // Reset the row counter before processing each collection
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'commissionWithholding', 'C');
            $this->applyCellStyle($event, $val, 'total_due', 'E');
            $this->applyCellStyle($event, $val, 'overrideDue', 'D');
            $this->applyCellStyle($event, $val, 'pay', 'F');
            $this->applyCellStyle($event, $val, 'total_pay', 'G');
            $this->applyCellStyle($event, $val, 'clawbackDue', 'H');
            $this->applyCellStyle($event, $val, 'totalAdjustments', 'I');
            $this->applyCellStyle($event, $val, 'payout', 'J');
            $this->rowCounter++;
        }
    }

    private function applyCellStyle(AfterSheet $event, $val, $field, $columnLetter): void
    {
        if (! empty($val[$field])) {
            $payValue = explode('$ ', $val[$field]);
            $totalPay = floatval(str_replace(',', '', $payValue[1]));
            // Calculate the cell address
            $cellAddress = $columnLetter.($this->rowCounter);
            // Apply styles based on the condition
            $styleArray = [
                'font' => [
                    'bold' => false,
                    'size' => 12,
                ],
            ];
            if ($totalPay < 0) {
                $event->sheet->setCellValue($cellAddress, '$ ('.exportNumberFormat(abs(floatval($totalPay))).')');
                // Set text color to red for negative values
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
        }
    }
}
