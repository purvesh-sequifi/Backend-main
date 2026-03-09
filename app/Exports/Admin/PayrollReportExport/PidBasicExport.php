<?php

namespace App\Exports\Admin\PayrollReportExport;

use App\Models\ClawbackSettlementLock;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\UserCommissionLock;
use App\Models\UserOverridesLock;
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

class PidBasicExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): Collection
    {
        $requestData = $this->request;

        $payrollData = $this->getPayrollData($requestData);

        if (empty($payrollData)) {
            return collect([]);
        }
        // return collect($this->transformPayrollData($payrollData));
        // $finalData = $this->exportFinalData($this->transformPayrollData($payrollData));
        $finalData = $this->transformPayrollData($payrollData);

        usort($finalData, function ($key, $value) {
            return strcmp($key['pId'], $value['pId']);
        });

        return collect($finalData);
    }

    /* Set header in the sheet */
    public function headings(): array
    {
        return [
            [
                'Pay Period:',
                Carbon::parse($this->request->startDate)->format('m/d/Y').' - '.Carbon::parse($this->request->endDate)->format('m/d/Y'),
                '',
                'Date Paid:',
                '',
            ],
            [
                'PID',
                'Customer Name  ',
                'Commissions  ',
                'Overrides  ',
                'Adjustments  ',
                'Net  ',
                'Paid Externally',
            ],
        ];
    }

    /**
     * Method styles: This methods is use for the excel sheet stylinng and formatting
     *
     * @param  Worksheet  $sheet  [explicite description]
     */
    public function styles(Worksheet $sheet): void
    {
        $sheet->getStyle('2:2')->applyFromArray([
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
        $columns = ['A1:A1', 'D1:D1']; // Array of column ranges
        foreach ($columns as $column) {
            $sheet->getStyle($column)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);
        }
    }

    /* set footer value */
    public function registerEvents(): array
    {
        // $collectionData = $this->exportFinalData($this->collection());
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                $this->setHeadersAndFooter($event);
                $this->styleRows($event, $collectionData);

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
                $rowCount = 3;
                $totalNetPayAmount = [];
                foreach ($collectionData as $value) {

                    $commissionValue = explode('$ ', $value['commission']);
                    $overrideValue = explode('$ ', $value['overrides']);
                    $adjustmentValue = explode('$ ', $value['adjustment']);

                    $totalNetPayAmount[] = $totalAmount = floatval(str_replace(',', '', end($commissionValue))) +
                    floatval(str_replace(',', '', end($overrideValue))) +
                    floatval(str_replace(',', '', end($adjustmentValue)));

                    $event->sheet->setCellValue("F$rowCount", '$ '.exportNumberFormat(floatval(str_replace(',', '', end($commissionValue))) +
                    floatval(str_replace(',', '', end($overrideValue))) +
                    floatval(str_replace(',', '', end($adjustmentValue)))));
                    $styleArray = [];
                    if ($totalAmount < 0) {
                        $event->sheet->setCellValue("F$rowCount", '$ ('.exportNumberFormat(abs($totalAmount)).')');
                        $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                    }
                    $event->sheet->getStyle("F$rowCount")->applyFromArray($styleArray);
                    $rowCount++;
                }

                /* Set header background color */

                $event->sheet->getStyle('2:2')->applyFromArray([
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
                /* Set footer values */
                $event->sheet->setCellValue("A$lastRow", 'Total');
                $event->sheet->setCellValue("F$lastRow", '$ '.exportNumberFormat(array_sum($totalNetPayAmount)));
                $styleArray = [];
                if (array_sum($totalNetPayAmount) < 0) {
                    $event->sheet->setCellValue("F$lastRow", '$ ('.exportNumberFormat(abs(array_sum($totalNetPayAmount))).')');
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle("F$lastRow")->applyFromArray($styleArray);
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

    private function setHeadersAndFooter(AfterSheet $event)
    {
        $lastRowIndex = $event->sheet->getDelegate()->getHighestDataRow();

        $event->sheet->freezePane('A2');
        $event->sheet->mergeCells('B1:C1');
        $event->sheet->insertNewRowBefore($lastRowIndex + 1, 1);

        $footerColumns = ['A'.($lastRowIndex + 2), 'F'.($lastRowIndex + 2)];
        foreach ($footerColumns as $value) {
            $event->sheet->getStyle($value)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);
        }

        $event->sheet->mergeCells('H1:U1');
        $event->sheet->setCellValue('H1', 'Note : The "Date Paid" field would only have a data once this payroll is executed and this report is found in payroll reports');
    }

    /**
     * Method applyStyles: if negetive found in cell then remove negetive sign and replace with round bracket with red color
     *
     * @param  AfterSheet  $event  [explicite description]
     */
    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $rowCounter = 3; // Reset the row counter before processing each collection
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'commission', 'C'.$rowCounter);
            $this->applyCellStyle($event, $val, 'overrides', 'D'.$rowCounter);
            $this->applyCellStyle($event, $val, 'adjustment', 'E'.$rowCounter);
            $this->applyCellStyle($event, $val, 'net_pay', 'F'.$rowCounter);
            $this->applyCellStyle($event, $val, 'paid_external', 'G'.$rowCounter);
            $rowCounter++;
        }
    }

    private function applyCellStyle(AfterSheet $event, $val, $field, $cellAddress): void
    {
        if (! empty($val[$field])) {
            $payValue = explode('$ ', $val[$field]);
            $totalPay = floatval(str_replace(',', '', $payValue[1]));
            $styleArray = [
                'font' => [
                    'bold' => false,
                    'size' => 12,
                ],
            ];
            if ($totalPay < 0) {
                $event->sheet->setCellValue($cellAddress, '$ ('.exportNumberFormat(abs($totalPay)).')');
                // Set text color to red for negative values
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
        }
    }

    private function getPayrollData($requestData)
    {
        $startDate = $requestData->startDate;
        $endDate = $requestData->endDate;
        $search = $requestData->search;

        if (! $this->isValidRequest($requestData, $startDate, $endDate)) {
            return collect([]);
        }

        $commissionPayrolls = UserCommissionLock::with('saledata')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_stop_payroll' => 0, 'status' => '3'])
            ->when($search && ! empty($search), function ($q) {
                $q->whereHas('saledata', function ($q) {
                    $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                });
            })->get()->toArray();

        $overridePayrolls = UserOverridesLock::with('saledata')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_stop_payroll' => 0, 'status' => '3'])
            ->when($search && ! empty($search), function ($q) {
                $q->whereHas('saledata', function ($q) {
                    $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                });
            })->get()->toArray();

        $clawbackPayrolls = ClawbackSettlementLock::with('saledata')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_stop_payroll' => 0, 'status' => '3'])
            ->when($search && ! empty($search), function ($q) {
                $q->whereHas('saledata', function ($q) {
                    $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                });
            })->get()->toArray();

        $adjustmentDetailsPayrolls = PayrollAdjustmentDetailLock::with('saledata')->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'status' => '3'])
            ->when($search && ! empty($search), function ($q) {
                $q->whereHas('saledata', function ($q) {
                    $q->where('pid', 'LIKE', '%'.request()->input('search').'%')->orWhere('customer_name', 'LIKE', '%'.request()->input('search').'%');
                });
            })->get()->toArray();

        $data = [];
        foreach ($commissionPayrolls as $commissionPayroll) {
            $commissionPayroll['data_type'] = 'commission';
            $data[$commissionPayroll['pid']]['commission'][] = $commissionPayroll;
        }
        foreach ($overridePayrolls as $overridePayroll) {
            $overridePayroll['data_type'] = 'override';
            $data[$overridePayroll['pid']]['override'][] = $overridePayroll;
        }
        foreach ($clawbackPayrolls as $clawbackPayroll) {
            $clawbackPayroll['data_type'] = 'clawback';
            $data[$clawbackPayroll['pid']]['clawback'][] = $clawbackPayroll;
        }
        foreach ($adjustmentDetailsPayrolls as $adjustmentDetailsPayroll) {
            $adjustmentDetailsPayroll['data_type'] = 'adjustment';
            $data[$adjustmentDetailsPayroll['pid']]['adjustment'][] = $adjustmentDetailsPayroll;
        }

        return $data;

    }

    private function isValidRequest($requestData, $startDate, $endDate)
    {
        return $requestData->has('startDate') && $requestData->has('endDate') && $requestData->has('payFrequency') &&
        ! empty($startDate) && ! empty($endDate) && ! empty($requestData->payFrequency);
    }

    private function transformPayrollData($payrollData)
    {
        $finalData = [];
        foreach ($payrollData as $payrollDataValue) {
            if (! empty($payrollDataValue['commission'])) {
                foreach ($payrollDataValue['commission'] as $userCommissionValue) {

                    $finalData[] = $this->sheetRowCreate($userCommissionValue, $payrollDataValue, 'commission');
                }
            }
            if (! empty($payrollDataValue['override'])) {
                foreach ($payrollDataValue['override'] as $userOverrideValue) {

                    $finalData[] = $this->sheetRowCreate($userOverrideValue, $payrollDataValue, 'overrides');
                }
            }
        }

        return $finalData;
    }

    private function sheetRowCreate($relationResponse, $payrollDataResponse, $categoryType)
    {
        switch ($categoryType) {
            case 'commission':
                $pid = $relationResponse['pid'];
                $customerName = ucfirst($relationResponse['saledata']['customer_name'] ?? $relationResponse['saledata']['customer_name']);
                $commission = '$ '.exportNumberFormat($relationResponse['amount'] ?? '0');
                $overrides = '';
                $adjustmentVal = 0;
                // if(isset($payrollDataResponse['adjustment']) && !empty($payrollDataResponse['adjustment'])){
                //     foreach($payrollDataResponse['adjustment'] as $adjustment){
                //         if($adjustment['payroll_type'] == 'commission'){
                //             $adjustmentVal += $adjustment['amount'];
                //         }
                //     }
                // }
                $adjustment = '$ '.exportNumberFormat($adjustmentVal ? $adjustmentVal : '0');
                $netPay = '';
                $paid_external = $relationResponse['is_mark_paid'] == '1' ? 'Paid Externally' : '';
                break;
            case 'overrides':
                $pid = $relationResponse['pid'];
                $customerName = ucfirst($relationResponse['saledata']['customer_name'] ?? $relationResponse['saledata']['customer_name']);
                $commission = '';
                $overrides = '$ '.exportNumberFormat($relationResponse['amount'] ?? '0');
                $adjustmentVal = 0;
                // if(isset($payrollDataResponse['adjustment']) && !empty($payrollDataResponse['adjustment'])){
                //     foreach($payrollDataResponse['adjustment'] as $adjustment){
                //         if($adjustment['payroll_type'] == 'overrides'){
                //             $adjustmentVal += $adjustment['amount'];
                //         }
                //     }
                // }
                $adjustment = '$ '.exportNumberFormat($adjustmentVal ? $adjustmentVal : '0');
                $netPay = '';
                $paid_external = $relationResponse['is_mark_paid'] == '1' ? 'Paid Externally' : '';
                break;
            default:
                // code...
                break;
        }

        return [
            'pId' => $pid,
            'customer_name' => $customerName,
            'commission' => $commission,
            'overrides' => $overrides,
            'adjustment' => $adjustment,
            'net_pay' => $netPay,
            'paid_externally' => $paid_external,
        ];
    }

    private function exportFinalData($array)
    {
        $mergedArray = [];

        foreach ($array as $item) {
            $key = $item['pId'].'|'.$item['customer_name'];

            if (array_key_exists($key, $mergedArray)) {
                $mergedArray[$key] = array_merge($mergedArray[$key], $item);
            } else {
                $mergedArray[$key] = $item;
            }
        }

        return array_values($mergedArray);
    }
}
