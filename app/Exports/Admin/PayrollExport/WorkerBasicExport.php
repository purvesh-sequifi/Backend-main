<?php

namespace App\Exports\Admin\PayrollExport;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Payroll;
use App\Models\PayrollHistory;
use App\Models\PayrollSsetup;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkerBasicExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    private $request;
    private $paidDate;
    private $isHistory;
    private $customField;

    public function __construct($request, $isHistory = 0)
    {
        $this->request = $request;
        $this->paidDate = null;
        $this->isHistory = $isHistory;
        $workerType = $request->worker_type;
        $workerTypeValue = ($workerType == '1099') ? 'Contractor' : 'Employee';
        $this->customField = PayrollSsetup::where('worked_type', 'LIKE', '%' . $workerTypeValue . '%')->where('status', 1)->orderBy('id', 'Asc')->get();
    }

    public function collection()
    {
        $payrollData = $this->getPayrollData();
        if ($payrollData->isEmpty()) {
            return collect([]);
        }

        return $this->transformPayrollData($payrollData);
    }

    public function headings(): array
    {
        $customFields = $this->customField->pluck('field_name')->toArray();
        $headings = [
            "User Name First",
            "User Name Last ",
            "User ID",
            "Contractor Type",
            "SSN ",
            "Business Name ",
            "EIN ",
            "Memo  ",
            "Commissions  ",
            "Overrides  ",
            "Adjustments  ",
            "Reimbursements  ",
            "Deductions ",
            "Net Pay",
            "Invoice Number",
            "Paid Externally"
        ];

        if (!empty($customFields)) {
            $headings = array_merge($headings, $customFields);
        }

        return [
            [
                "Pay Period",
                Carbon::parse($this->request->pay_period_from)->format('m/d/Y') . " - " . Carbon::parse($this->request->pay_period_to)->format('m/d/Y'),
                "",
                "Date Paid",
                ($this->paidDate && $this->isHistory) ? Carbon::parse($this->paidDate)->format('m/d/Y') : "",
            ],
            $headings
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow = 2;
        $highestColumn = $sheet->getHighestColumn();
        $headerRange = 'A' . $headerRow . ':' . $highestColumn . $headerRow;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                "size" => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999'
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
                $this->setHeadersAndFooter($event);
                $this->styleRows($event, $collectionData);
                $this->formatSSNAndEIN($event);

                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();
                $lastColumn = $event->sheet->getDelegate()->getHighestDataColumn();

                $evenRowColor = 'f0f0f0'; // Light green
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
                for ($i = 2; $i <= $lastRow; $i++) {
                    for ($col = 'A'; $col != 'AC'; ++$col) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle(),
                        ]);
                    }
                }
                $event->sheet->setCellValue("A" . $lastRow + 1, "Total");
                $event->sheet->setCellValue("N" . $lastRow + 1, "$ " . $this->totalNetPay());
                $styleArray['font'] = ['bold' => true];
                if ($this->totalNetPay() < 0) {
                    $event->sheet->setCellValue("N" . $lastRow + 1, "$ (" . exportNumberFormat(abs($this->totalNetPay())) . ")");
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle("N" . $lastRow + 1)->applyFromArray($styleArray);
                $event->sheet->getStyle("A" . $lastRow + 1)->applyFromArray($styleArray);
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

        $event->sheet->freezePane('A3');
        $event->sheet->mergeCells('B1:C1');
        $event->sheet->insertNewRowBefore($lastRowIndex + 1, 1);

        if (!$this->isHistory) {
            $event->sheet->mergeCells('G1:P1');
            $event->sheet->setCellValue("G1", 'Note : The "Date Paid" field would only have a data once this payroll is executed and this report is found in payroll reports');
        }
    }

    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $rowCounter = 3;
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'commission', 'I' . $rowCounter);
            $this->applyCellStyle($event, $val, 'overrides', 'J' . $rowCounter);
            $this->applyCellStyle($event, $val, 'adjustment', 'K' . $rowCounter);
            $this->applyCellStyle($event, $val, 'reimbursement', 'L' . $rowCounter);
            $this->applyCellStyle($event, $val, 'deduction', 'M' . $rowCounter);
            $this->applyCellStyle($event, $val, 'net_pay', 'N' . $rowCounter);
            $this->applyCellStyle($event, $val, 'paid_externally', 'P' . $rowCounter);
            $rowCounter++;
        }
    }

    private function applyCellStyle(AfterSheet $event, $val, $field, $cellAddress): void
    {
        if (!empty($val[$field])) {
            $payValue = explode("$ ", $val[$field]);
            $totalPay = floatval(str_replace(',', '', $payValue[1]));
            $styleArray = [
                'font' => [
                    'bold' => false,
                    "size" => 12,
                ],
            ];
            if ($totalPay < 0) {
                $event->sheet->setCellValue($cellAddress, "$ (" . exportNumberFormat(abs($totalPay)) . ")");
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }

            if ($field == 'deduction') {
                $event->sheet->setCellValue($cellAddress, "$ (" . exportNumberFormat(abs($totalPay)) . ")");
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
        }
    }

    private function formatSSNAndEIN(AfterSheet $event)
    {
        $collectionData = $this->collection();
        $rowCounter = 3;

        foreach ($collectionData as $value) {
            if (!empty($value["ssn"])) {
                $string = str_replace("-", "", $value["ssn"]);
                $formattedString = substr($string, 0, 3) . "-" . substr($string, 3, 2) . "-" . substr($string, 5);
                $event->sheet->setCellValue("E" . $rowCounter, $formattedString);
            }

            if (!empty($value["ein"])) {
                $string = str_replace("-", "", $value["ein"]);
                $formattedString = substr($string, 0, 2) . "-" . substr($string, 2);
                $event->sheet->setCellValue("G" . $rowCounter, $formattedString);
            }

            $rowCounter++;
        }
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
            $payrollData = PayrollHistory::with(["payrollUser"])
                ->when(!empty($search), function ($query) use ($search) {
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
                    "payrollCustomFields:id,column_id,value",
                    "payrollUser:id,first_name,last_name,employee_id,entity_type,social_sequrity_no,business_name,business_ein"
                ])->applyFrequencyFilter($param)->orderBy(
                    User::select('first_name')
                        ->whereColumn('id', 'payroll_history.user_id')
                        ->orderBy('first_name', 'asc')
                        ->limit(1),
                    'ASC'
                )->get();
        } else {
            $payrollData = Payroll::with(["payrollUser"])
                ->when(!empty($search), function ($query) use ($search) {
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
                    "payrollCustomFields:id,column_id,value",
                    "payrollUser:id,first_name,last_name,employee_id,entity_type,social_sequrity_no,business_name,business_ein"
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

    private function transformPayrollData($payrollData)
    {
        return $payrollData->transform(function ($response) {
            $customFields = [];
            foreach ($this->customField as $setting) {
                $field = $response->payrollCustomFields->where('column_id', $setting->id)->first();
                $customFields[$setting->field_name] = (isset($field->value)) ? "$ " . exportNumberFormat($field->value) : "$ 0.00";
            }

            $returnData = [
                "user_name_first" => $response?->payrollUser?->first_name ?? '',
                "user_name_last" => $response?->payrollUser?->last_name ?? '',
                "user_id" => $response?->payrollUser?->employee_id ?? '',
                "contractor_type" => $response?->payrollUser?->entity_type ?? '',
                "ssn" => $response?->payrollUser?->social_sequrity_no ?? '',
                "buisnness_name" => $response?->payrollUser?->business_name ?? '',
                "ein" => $response?->payrollUser?->business_ein ?? '',
                "memo" => "", // Asking to deepak ya aahsu sir
                "commission" => "$ " . exportNumberFormat($response->commission),
                "overrides" => "$ " . exportNumberFormat($response->override),
                "adjustment" => "$ " . exportNumberFormat(floatval($response->adjustment)),
                "reimbursement" => "$ " . exportNumberFormat($response?->reimbursement ?? "0"),
                "deduction" => "$ " . exportNumberFormat($response?->deduction ?? "0"),
                "net_pay" => "$ " . exportNumberFormat($response->net_pay),
                "invoice_number" => "",
                'paid_external' => $response->is_mark_paid ? 'Paid Externally' : ''
            ];

            if (!empty($customFields)) {
                $returnData = $returnData + $customFields;
            }
            return $returnData;
        });
    }

    private function totalNetPay()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            $payValue = explode("$ ", $value["net_pay"]);
            $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
        }
        return array_sum($totalPay);
    }
}