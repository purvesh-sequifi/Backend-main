<?php

namespace App\Exports\Admin\PayrollExport;

use App\Models\User;
use App\Models\Payroll;
use Illuminate\Support\Carbon;
use App\Models\PayrollAdjustmentDetail;
use App\Models\PayrollHistory;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PidBasicExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    private $request;
    private $paidDate;
    private $isHistory;

    private $isFirstCoast;

    public function __construct($request, $isHistory = 0)
    {
        $this->request = $request;
        $this->isHistory = $isHistory;
        $this->paidDate = null;
        $this->isFirstCoast = in_array(strtolower(config('app.domain_name')), ['firstcoast', 'solarstage', 'mortgagestage']);
    }

    public function collection()
    {
        $payrollData = $this->getPayrollData();
        if ($payrollData->isEmpty()) {
            return collect([]);
        }

        $adjustmentData = $this->getAdjustmentData();

        $finalData = $this->transformPayrollData($payrollData, $adjustmentData);
        usort($finalData, function ($key, $value) {
            return strcmp($key['pId'], $value['pId']);
        });
        return collect($finalData);
    }

    public function headings(): array
    {
        if ($this->isFirstCoast) {
            return [
                [
                    "Pay Period:",
                    Carbon::parse($this->request->startDate)->format('m/d/Y') . " - " . Carbon::parse($this->request->endDate)->format('m/d/Y'),
                    "",
                    "Date Paid:",
                    "",
                ],
                [
                    "Customer ID",
                    "Borrower Name  ",
                    "Commissions  ",
                    "Overrides  ",
                    "Adjustments  ",
                    "Net  ",
                    "Paid Externally",
                ],
            ];
        }

        return [
            [
                "Pay Period:",
                Carbon::parse($this->request->pay_period_from)->format('m/d/Y') . " - " . Carbon::parse($this->request->pay_period_to)->format('m/d/Y'),
                "",
                "Date Paid:",
                ($this->paidDate && $this->isHistory) ? Carbon::parse($this->paidDate)->format('m/d/Y') : "",
            ],
            [
                "PID",
                "Customer Name  ",
                "Commissions  ",
                "Overrides  ",
                "Adjustments  ",
                "Net  ",
                "Paid Externally",
            ],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => [
                'bold' => true,
                "size" => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999', // Background color (light gray)
                ],
            ],
        ]);
        $columns = ['A1:A1', 'D1:D1'];
        foreach ($columns as $column) {
            $sheet->getStyle($column)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);
        }
    }

    public function registerEvents(): array
    {
        $collectionData = $this->collection();
        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                $event->sheet->freezePane('A3');

                $worksheet = $event->sheet->getDelegate();
                $worksheet->mergeCells('B1:C1');
                $lastRowIndex = $worksheet->getHighestDataRow();
                $worksheet->insertNewRowBefore($lastRowIndex + 1, 1);

                // Add note field
                if (!$this->isHistory) {
                    $event->sheet->mergeCells('H1:U1');
                    $event->sheet->setCellValue("H1", 'Note : The "Date Paid" field would only have a data once this payroll is executed and this report is found in payroll reports');
                }

                // Style footer columns
                $footerColumns = ['A' . ($lastRowIndex + 2), 'F' . ($lastRowIndex + 2)];
                foreach ($footerColumns as $value) {
                    $event->sheet->getStyle($value)->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                    ]);
                }

                // Process each row for styling and calculations
                $rowCounter = 3;
                $totalNetPayAmount = [];
                foreach ($collectionData as $val) {
                    // Commission column styling
                    if (!empty($val["commission"])) {
                        $payValue1 = explode("$ ", $val["commission"]);
                        $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));
                        $cellAddress1 = "C" . $rowCounter;
                        $styleArray1 = [
                            'font' => [
                                'bold' => false,
                                "size" => 12,
                            ],
                        ];
                        if ($totalPay1 < 0) {
                            $event->sheet->setCellValue($cellAddress1, "$ (" . exportNumberFormat(abs($totalPay1)) . ")");
                            $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }
                        $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);
                    }

                    // Override column styling
                    if (!empty($val["overrides"])) {
                        $payValue2 = explode("$ ", $val["overrides"]);
                        $totalPay2 = floatval(str_replace(',', '', $payValue2[1]));
                        $cellAddress2 = "D" . $rowCounter;
                        $styleArray2 = [
                            'font' => [
                                'bold' => false,
                                "size" => 12,
                            ],
                        ];
                        if ($totalPay2 < 0) {
                            $event->sheet->setCellValue($cellAddress2, "$ (" . exportNumberFormat(abs($totalPay2)) . ")");
                            $styleArray2['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }
                        $event->sheet->getStyle($cellAddress2)->applyFromArray($styleArray2);
                    }

                    // Adjustment column styling
                    if (!empty($val["adjustment"])) {
                        $payValue3 = explode("$ ", $val["adjustment"]);
                        $totalPay3 = floatval(str_replace(',', '', $payValue3[1]));
                        $cellAddress3 = "E" . $rowCounter;
                        $styleArray3 = [
                            'font' => [
                                'bold' => false,
                                "size" => 12,
                            ],
                        ];
                        if ($totalPay3 < 0) {
                            $event->sheet->setCellValue($cellAddress3, "$ (" . exportNumberFormat(abs($totalPay3)) . ")");
                            $styleArray3['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }
                        $event->sheet->getStyle($cellAddress3)->applyFromArray($styleArray3);
                    }

                    // Calculate net pay for each row
                    $commissionValue = explode("$ ", $val["commission"]);
                    $overrideValue = explode("$ ", $val["overrides"]);
                    $adjustmentValue = explode("$ ", $val["adjustment"]);
                    $totalNetPayAmount[] = $totalAmount = floatval(str_replace(',', '', end($commissionValue))) +
                        floatval(str_replace(',', '', end($overrideValue))) +
                        floatval(str_replace(',', '', end($adjustmentValue)));

                    $event->sheet->setCellValue("F$rowCounter", "$ " . exportNumberFormat($totalAmount));
                    $styleArray = [
                        'font' => [
                            'bold' => false,
                            "size" => 12,
                        ],
                    ];
                    if ($totalAmount < 0) {
                        $event->sheet->setCellValue("F$rowCounter", "$ (" . exportNumberFormat(abs($totalAmount)) . ")");
                        $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                    }
                    $event->sheet->getStyle("F$rowCounter")->applyFromArray($styleArray);
                    $rowCounter++;
                }

                // Add total row
                $event->sheet->setCellValue("A" . ($lastRowIndex + 2), "Total");
                $event->sheet->setCellValue("F" . ($lastRowIndex + 2), "$ " . exportNumberFormat(array_sum($totalNetPayAmount)));
                $styleArray = [
                    'font' => [
                        'bold' => true,
                    ],
                ];
                if (array_sum($totalNetPayAmount) < 0) {
                    $event->sheet->setCellValue("F" . ($lastRowIndex + 2), "$ (" . exportNumberFormat(abs(array_sum($totalNetPayAmount))) . ")");
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle("F" . ($lastRowIndex + 2))->applyFromArray($styleArray);

                // Apply alternating row colors (starting from row 4 for even, row 3 for odd)
                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();
                $lastColumn = $event->sheet->getDelegate()->getHighestDataColumn();

                $evenRowColor = 'f0f0f0'; // Light gray
                $oddRowColor = 'FFFFFF'; // White

                for ($row = 4; $row <= $lastRow; $row += 2) {
                    $event->sheet->getStyle("A$row:$lastColumn$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $evenRowColor],
                        ],
                    ]);
                }

                for ($row = 3; $row <= $lastRow; $row += 2) {
                    $event->sheet->getStyle("A$row:$lastColumn$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $oddRowColor],
                        ],
                    ]);
                }

                // Apply borders to all data cells
                for ($i = 2; $i <= $lastRow; $i++) {
                    for ($col = 'A'; $col != 'H'; ++$col) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle()
                        ]);
                    }
                }

                // Reapply header styling to ensure it's correct
                $event->sheet->getStyle('A2:G2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        "size" => 12,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '999999', // Background color (light gray)
                        ],
                    ],
                ]);
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

    private function getPayrollData()
    {
        $requestData = $this->request;
        $search = $requestData->search;
        $negativeNetPay = $requestData->netpay_filter;
        $isReconciliation = $requestData->is_reconciliation;
        $payPeriodFrom = $requestData->pay_period_from;
        $payPeriodTo = $requestData->pay_period_to;
        $payFrequency = $requestData->pay_frequency;
        $workerType = $requestData->worker_type;

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        if ($this->isHistory) {
            $payrollData = PayrollHistory::with([
                "payrollUser",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollOverrides.payrollOverUser",
                "payrollClawBacks.payrollSaleData",
                "payrollClawBacks.payrollOverUser",
                // "payrollReconciliations"
            ])->when(!empty($search), function ($query) use ($search) {
                $query->whereHas("payrollUser", function ($query) use ($search) {
                    $query->where("first_name", "like", "%{$search}%")
                        ->orWhere("last_name", "like", "%{$search}%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"]);
                });
            })->when(!empty($negativeNetPay), function ($query) {
                $query->where("net_pay", "<", 0);
            })->when($isReconciliation == 1, function ($query) use ($payPeriodTo) {
                $positionArray = getReconciliationPositions($payPeriodTo);
                $query->whereIn("position_id", $positionArray);
            })->with([
                "payrollUser:id,first_name,last_name,employee_id"
            ])->applyFrequencyFilter($param)->orderBy(
                User::select('first_name')
                    ->whereColumn('id', 'payroll_history.user_id')
                    ->orderBy('first_name', 'asc')
                    ->limit(1),
                'ASC'
            )->get();
        } else {
            $payrollData = Payroll::with([
                "payrollUser",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollOverrides.payrollOverUser",
                "payrollClawBacks.payrollSaleData",
                "payrollClawBacks.payrollOverUser",
                // "payrollReconciliations"
            ])->when(!empty($search), function ($query) use ($search) {
                $query->whereHas("payrollUser", function ($query) use ($search) {
                    $query->where("first_name", "like", "%{$search}%")
                        ->orWhere("last_name", "like", "%{$search}%")
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["%{$search}%"]);
                });
            })->when(!empty($negativeNetPay), function ($query) {
                $query->where("net_pay", "<", 0);
            })->when($isReconciliation == 1, function ($query) use ($payPeriodTo) {
                $positionArray = getReconciliationPositions($payPeriodTo);
                $query->whereIn("position_id", $positionArray);
            })->with([
                "payrollUser:id,first_name,last_name,employee_id"
            ])->applyFrequencyFilter($param)->orderBy(
                User::select('first_name')
                    ->whereColumn('id', 'payrolls.user_id')
                    ->orderBy('first_name', 'asc')
                    ->limit(1),
                'ASC'
            )->get();
        }

        $this->paidDate = $payrollData?->first()?->created_at;
        return $payrollData;
    }

    private function getAdjustmentData()
    {
        $requestData = $this->request;
        $payPeriodFrom = $requestData->pay_period_from;
        $payPeriodTo = $requestData->pay_period_to;
        $payFrequency = $requestData->pay_frequency;
        $workerType = $requestData->worker_type;

        $param = [
            "pay_frequency" => $payFrequency,
            "worker_type" => $workerType,
            "pay_period_from" => $payPeriodFrom,
            "pay_period_to" => $payPeriodTo
        ];

        $commissionAdjustments = PayrollAdjustmentDetail::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")->applyFrequencyFilter($param);
        $clawBackAdjustments = PayrollAdjustmentDetail::with("payrollCommentedBy:id,first_name,last_name,image,position_id,sub_position_id,is_manager,is_super_admin")->applyFrequencyFilter($param);
        return $commissionAdjustments->union($clawBackAdjustments)->get();
    }

    private function transformPayrollData($payrollData, $adjustmentData)
    {
        $finalData = [];
        foreach ($payrollData as $payrollDataValue) {
            foreach ($payrollDataValue->payrollCommissions as $payrollCommission) {
                $finalData[] = $this->sheetRowCreate($payrollCommission, $adjustmentData, "Commission");
            }
            foreach ($payrollDataValue->payrollOverrides as $payrollOverride) {
                $finalData[] = $this->sheetRowCreate($payrollOverride, $adjustmentData, "Override");
            }
            foreach ($payrollDataValue->payrollClawBacks as $payrollClawBack) {
                if ($payrollClawBack->type == "commission") {
                    $finalData[] = $this->sheetRowCreate($payrollClawBack, $adjustmentData, "Commission ClawBack");
                } else {
                    $finalData[] = $this->sheetRowCreate($payrollClawBack, $adjustmentData, "Override ClawBack");
                }
            }
            // foreach ($payrollDataValue->payrollReconciliations as $payrollReconciliation) {
            //     $finalData[] = $this->sheetRowCreate($payrollReconciliation, $adjustmentData, "Reconciliation");
            // }
        }
        return $finalData;
    }

    private function sheetRowCreate($relationResponse, $adjustments, $categoryType)
    {
        switch ($categoryType) {
            case 'Commission':
                $relationResponse->payroll_type = $relationResponse->amount_type;
                $adjustment = adjustmentColumn($relationResponse, $adjustments, "commission");

                $pid = $relationResponse->pid;
                $customerName = ucfirst($relationResponse?->payrollSaleData?->customer_name);
                $commission = "$ " . exportNumberFormat($relationResponse->amount ?? "0");
                $overrides = "";
                $adjustment = "$ " . exportNumberFormat(isset($adjustment['adjustment_amount']) ? $adjustment['adjustment_amount'] : "0");
                $netPay = "";
                $paidExternal = $relationResponse->is_mark_paid == "1" ? 'Paid Externally' : '';
                break;
            case 'Override':
                $relationResponse->payroll_type = $relationResponse->type;
                $adjustment = adjustmentColumn($relationResponse, $adjustments, "override");

                $pid = $relationResponse->pid;
                $customerName = ucfirst($relationResponse?->payrollSaleData?->customer_name);
                $commission = "";
                $overrides = "$ " . exportNumberFormat($relationResponse?->amount ?? "0");
                $adjustment = "$ " . exportNumberFormat(isset($adjustment['adjustment_amount']) ? $adjustment['adjustment_amount'] : "0");
                $netPay = "";
                $paidExternal = $relationResponse->is_mark_paid == "1" ? 'Paid Externally' : '';
                break;
            case 'Commission ClawBack':
                $relationResponse->payroll_type = "clawback";
                $relationResponse->amount_type = $relationResponse->adders_type;
                $adjustment = adjustmentColumn($relationResponse, $adjustments, "commission");

                $pid = $relationResponse->pid;
                $customerName = ucfirst($relationResponse?->payrollSaleData?->customer_name);
                $commission = "$ " . exportNumberFormat($relationResponse->clawback_amount ?? "0");
                $overrides = "";
                $adjustment = "$ " . exportNumberFormat(isset($adjustment['adjustment_amount']) ? $adjustment['adjustment_amount'] : "0");
                $netPay = "";
                $paidExternal = $relationResponse->is_mark_paid == "1" ? 'Paid Externally' : '';
                break;
            case 'Override ClawBack':
                $relationResponse->payroll_type = "clawback";
                $relationResponse->type = $relationResponse->adders_type;
                $adjustment = adjustmentColumn($relationResponse, $adjustments, "override");

                $pid = $relationResponse->pid;
                $customerName = ucfirst($relationResponse?->payrollSaleData?->customer_name);
                $commission = "";
                $overrides = "$ " . exportNumberFormat($relationResponse?->clawback_amount ?? "0");
                $adjustment = "$ " . exportNumberFormat(isset($adjustment['adjustment_amount']) ? $adjustment['adjustment_amount'] : "0");
                $netPay = "";
                $paidExternal = $relationResponse->is_mark_paid == "1" ? 'Paid Externally' : '';
                break;
            // case 'Reconciliations':
            //     $pid = $relationResponse->pid;
            //     $customerName = NULL;
            //     $commission = "$ " . exportNumberFormat($relationResponse->net_amount ? $relationResponse->net_amount : "0");
            //     $overrides = "";
            //     $adjustment = "";
            //     $netPay = "";
            //     $paidExternal = $relationResponse->is_mark_paid == "1" ? 'Paid Externally' : '';
            //     break;
            default:
                # code...
                break;
        }

        return [
            "pId" => $pid,
            "customer_name" => $customerName,
            "commission" => $commission,
            "overrides" => $overrides,
            "adjustment" => $adjustment,
            "net_pay" => $netPay,
            "paid_externally" => $paidExternal
        ];
    }
}