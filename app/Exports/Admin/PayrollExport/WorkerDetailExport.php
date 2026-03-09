<?php

namespace App\Exports\Admin\PayrollExport;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Models\Payroll;
use App\Models\CustomField;
use App\Models\SalesMaster;
use App\Models\UserOverrides;
use App\Models\UserCommission;
use Illuminate\Support\Carbon;
use App\Models\ClawbackSettlement;
use App\Models\ApprovalsAndRequest;
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

class WorkerDetailExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    private $request;
    private $isHistory;
    private bool $isMortgageCompany;

    public function __construct($request, $isHistory = 0)
    {
        $this->request = $request;
        $this->isHistory = $isHistory;
        $this->isMortgageCompany = CompanyProfile::first()?->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;
    }

    public function collection()
    {
        $payrollData = $this->getPayrollData();
        if ($payrollData->isEmpty()) {
            return collect([]);
        }

        return collect($this->transformPayrollData($payrollData));
    }

    public function headings(): array
    {
        $base = [
                "User Name First",
                "User Name Last",
                "User ID",
                "Category",
                "Type",
                "PID / Req ID",
                "Customer Name  ",
                "Comments",
                "Date Paid",
                "Amount  ",
                "Net  ",
            "Paid Externally",
        ];
        if ($this->isMortgageCompany) {
            $base[] = "Comp Rate";
            $base[] = "Fee %";
        }
        return [
            [
                "Pay Period:",
                Carbon::parse($this->request->pay_period_from)->format('m/d/Y') . " - " . Carbon::parse($this->request->pay_period_to)->format('m/d/Y'),
            ],
            $base,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $headerRange = $this->isMortgageCompany ? 'A2:N2' : 'A2:L2';
        $sheet->getStyle($headerRange)->applyFromArray([
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
        $secondHeader = [
            "Total",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "$ " . (exportNumberFormat($this->totalAmount())),
            "$ " . (exportNumberFormat($this->totalNetPay())),

        ];
        $collectionData = $this->collection();
        return [
            AfterSheet::class => function (AfterSheet $event) use ($secondHeader, $collectionData) {
                $event->sheet->freezePane('A3');

                $worksheet = $event->sheet->getDelegate();
                $worksheet->mergeCells('B1:C1');
                $lastRowIndex = $worksheet->getHighestDataRow();
                $worksheet->insertNewRowBefore($lastRowIndex + 1, 1);
                $worksheet->fromArray([$secondHeader], null, 'A' . ($lastRowIndex + 2), true);
                $footerColumns = ['A' . ($lastRowIndex + 2), 'J' . ($lastRowIndex + 2), 'K' . ($lastRowIndex + 2)];
                foreach ($footerColumns as $value) {
                    $event->sheet->getStyle($value)->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                    ]);
                }

                $rowCounter = 3;
                foreach ($collectionData as $val) {
                    if (!empty($val["amount"])) {
                        $payValue1 = explode("$ ", $val["amount"]);
                        $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));
                        $cellAddress1 = "J" . $rowCounter;
                        $event->sheet->setCellValue($cellAddress1, "$ " . exportNumberFormat($totalPay1));
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

                    if (!empty($val["amount"])) {
                        $payValue1 = explode("$ ", $val["amount"]);
                        $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));
                        $cellAddress1 = "J" . $rowCounter;
                        $event->sheet->setCellValue($cellAddress1, "$ " . exportNumberFormat($totalPay1));
                        $styleArray1 = [
                            'font' => [
                                'bold' => false,
                                "size" => 12,
                            ],

                        ];
                        if ($totalPay1 < 0 || $val["category"] == 'Deduction') {
                            $event->sheet->setCellValue($cellAddress1, "$ (" . exportNumberFormat(abs($totalPay1)) . ")");
                            $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }

                        $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);
                    }
                    $rowCounter++;
                }

                if ($this->totalNetPay() < 0) {
                    $event->sheet->setCellValue("J" . $lastRowIndex + 2, "$ (" . exportNumberFormat(abs($this->totalAmount())) . ")");
                    $event->sheet->setCellValue("K" . $lastRowIndex + 2, "$ (" . exportNumberFormat(abs($this->totalNetPay())) . ")");
                    $event->sheet->getStyle("K" . $lastRowIndex + 2)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => [
                                'rgb' => 'FF0000',
                            ],
                        ],
                    ]);
                    $event->sheet->getStyle("J" . $lastRowIndex + 2)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => [
                                'rgb' => 'FF0000',
                            ],
                        ],
                    ]);
                }


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
                    $endCol = $this->isMortgageCompany ? 'O' : 'M'; // exclusive
                    for ($col = 'A'; $col != $endCol; ++$col) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle()
                        ]);
                    }
                }

                $headerRange = $this->isMortgageCompany ? 'A2:N2' : 'A2:L2';
                $event->sheet->getStyle($headerRange)->applyFromArray([
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
            ]
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
            return PayrollHistory::with([
                "payrollUser",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollClawBacks.payrollSaleData",
                "payrollPayrollAdjustmentDetails.payrollSaleData",
                "payrollApproveRequest" => function ($q) {
                    $q->where(['status' => 'Paid']);
                },
                "payrollApproveRequest.payrollAdjustment",
                "payrollApproveRequest.payrollComments",
                "payrollDeductions",
                "payrollCustomFields.getColumn"
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
            return Payroll::with([
                "payrollUser",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollClawBacks.payrollSaleData",
                "payrollPayrollAdjustmentDetails.payrollSaleData",
                "payrollApproveRequest" => function ($q) {
                    $q->where(['status' => 'Accept']);
                },
                "payrollApproveRequest.payrollAdjustment",
                "payrollApproveRequest.payrollComments",
                "payrollDeductions",
                "payrollCustomFields.getColumn"
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
    }

    private function transformPayrollData($payrollData)
    {
        $data = [];
        foreach ($payrollData as $response) {
            $payrollCommissions = $response->payrollCommissions;
            foreach ($payrollCommissions as $payrollCommission) {
                $formattedCompRate = '';
                if ($this->isMortgageCompany && is_numeric($payrollCommission->comp_rate)) {
                    $formattedCompRate = number_format((float) $payrollCommission->comp_rate, 4, '.', '').'%';
                }
                $formattedFeePercentage = '';
                $netEpc = $payrollCommission?->payrollSaleData?->net_epc;
                if ($this->isMortgageCompany && is_numeric($netEpc)) {
                    $formattedFeePercentage = number_format(((float) $netEpc) * 100, 4, '.', '').'%';
                }
                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name),
                    "user_name_last" => ucfirst($response->payrollUser->last_name),
                    "user_id" => $response->payrollUser->employee_id,
                    "category" => ucfirst('Commission'),
                    "type" => ucfirst($payrollCommission->amount_type),
                    "pid_reqid" => $payrollCommission->pid,
                    "customer_name" => isset($payrollCommission->payrollSaleData->customer_name) ? $payrollCommission->payrollSaleData->customer_name : null,
                    "comments" => '',
                    "date_paid" => $payrollCommission->is_mark_paid == "1" ? Carbon::parse($payrollCommission->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($payrollCommission->amount),
                    "net_pay" => 'test here',
                    "paid_externally" => $payrollCommission->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => $formattedCompRate,
                        "fee_percentage" => $formattedFeePercentage,
                    ] : []),
                ];
            }

            $payrollOverrides = $response->payrollOverrides;
            foreach ($payrollOverrides as $overrides) {
                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name),
                    "user_name_last" => ucfirst($response->payrollUser->last_name),
                    "user_id" => $response->payrollUser->employee_id,
                    "category" => ucfirst('Override'),
                    "type" => ucfirst($overrides->type),
                    "pid_reqid" => $overrides->pid,
                    "customer_name" => isset($overrides->payrollSaleData->customer_name) ? $overrides->payrollSaleData->customer_name : null,
                    "comments" => '',
                    "date_paid" => $overrides->is_mark_paid == "1" ? Carbon::parse($overrides->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($overrides->amount),
                    "net_pay" => '',
                    "paid_externally" => $overrides->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            $payrollClawBacks = $response->payrollClawBacks;
            foreach ($payrollClawBacks as $clawBack) {
                if ($clawBack->type == "commission") {
                    $amount = isset($clawBack->clawback_amount) ? (0 - $clawBack->clawback_amount) : 0;
                    $formattedCompRate = '';
                    if ($this->isMortgageCompany && is_numeric($clawBack->comp_rate ?? null)) {
                        $formattedCompRate = number_format((float) $clawBack->comp_rate, 4, '.', '').'%';
                    }
                    $formattedFeePercentage = '';
                    $netEpc = $clawBack?->payrollSaleData?->net_epc;
                    if ($this->isMortgageCompany && is_numeric($netEpc)) {
                        $formattedFeePercentage = number_format(((float) $netEpc) * 100, 4, '.', '').'%';
                    }
                    $data[] = [
                        "user_name_first" => ucfirst($response->payrollUser->first_name),
                        "user_name_last" => ucfirst($response->payrollUser->last_name),
                        "user_id" => $response->payrollUser->employee_id,
                        "category" => ucfirst('Commission'),
                        "type" => ucfirst('Clawback'),
                        "pid_reqid" => $clawBack->pid,
                        "customer_name" => isset($clawBack->payrollSaleData->customer_name) ? $clawBack->payrollSaleData->customer_name : null,
                        "comments" => '',
                        "date_paid" => $clawBack->is_mark_paid == "1" ? Carbon::parse($clawBack->updated_at)->format("m/d/Y") : "",
                        "amount" => "$ " . exportNumberFormat($amount),
                        "net_pay" => '',
                        "paid_externally" => $clawBack->is_mark_paid == "1" ? "Paid Externally" : "",
                        ...($this->isMortgageCompany ? [
                            "comp_rate" => $formattedCompRate,
                            "fee_percentage" => $formattedFeePercentage,
                        ] : []),
                    ];
                } else {
                    $amount = isset($clawBack->clawback_amount) ? (0 - $clawBack->clawback_amount) : 0;
                    $data[] = [
                        "user_name_first" => ucfirst($response->payrollUser->first_name),
                        "user_name_last" => ucfirst($response->payrollUser->last_name),
                        "user_id" => $response->payrollUser->employee_id,
                        "category" => ucfirst('Override'),
                        "type" => ucfirst('Clawback'),
                        "pid_reqid" => $clawBack->pid,
                        "customer_name" => isset($clawBack->payrollSaleData->customer_name) ? $clawBack->payrollSaleData->customer_name : null,
                        "comments" => '',
                        "date_paid" => $clawBack->is_mark_paid == "1" ? Carbon::parse($clawBack->updated_at)->format("m/d/Y") : "",
                        "amount" => "$ " . exportNumberFormat($amount),
                        "net_pay" => '',
                        "paid_externally" => $clawBack->is_mark_paid == "1" ? "Paid Externally" : "",
                        ...($this->isMortgageCompany ? [
                            "comp_rate" => '',
                            "fee_percentage" => '',
                        ] : []),
                    ];
                }
            }

            $payrollPayrollAdjustmentDetails = $response->payrollPayrollAdjustmentDetails;
            foreach ($payrollPayrollAdjustmentDetails as $adjustment) {
                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name),
                    "user_name_last" => ucfirst($response->payrollUser->last_name),
                    "user_id" => $response->payrollUser->employee_id,
                    "category" => ucfirst('Adjustment'),
                    "type" => ucfirst($adjustment->payroll_type),
                    "pid_reqid" => $adjustment->pid,
                    "customer_name" => $adjustment?->payrollSaleData?->customer_name,
                    "comments" => $adjustment->comment,
                    "date_paid" => $adjustment->is_mark_paid == "1" ? Carbon::parse($adjustment->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($adjustment->amount),
                    "net_pay" => '',
                    "paid_externally" => $adjustment->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            $payrollApproveRequests = $response->payrollApproveRequest;
            foreach ($payrollApproveRequests as $payrollApproveRequest) {
                $type = '';
                if ($payrollApproveRequest->adjustment_type_id == 5) {
                    $category = 'Adjustment';
                    $type = isset($payrollApproveRequest->payrollAdjustment->name) ? $payrollApproveRequest->payrollAdjustment->name : '';
                    $amount = ($payrollApproveRequest->amount < 0) ? $payrollApproveRequest->amount : (0 - $payrollApproveRequest->amount);
                } else {
                    $category = isset($payrollApproveRequest->payrollAdjustment->name) ? $payrollApproveRequest->payrollAdjustment->name : '';
                    $amount = $payrollApproveRequest->amount;
                }

                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name),
                    "user_name_last" => ucfirst($response->payrollUser->last_name),
                    "user_id" => $response->payrollUser->employee_id,
                    "category" => ucfirst($category),
                    "type" => ucfirst($type),
                    "pid_reqid" => $payrollApproveRequest->req_no,
                    "customer_name" => '',
                    "comments" => isset($approvalAndRequestDetail->description)
                        ? $approvalAndRequestDetail->description
                        : (isset($approvalAndRequestDetail?->payrollComments?->comment) ? strip_tags($approvalAndRequestDetail?->payrollComments?->comment) : null),
                    "date_paid" => $payrollApproveRequest->is_mark_paid == "1" ? Carbon::parse($payrollApproveRequest->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($amount),
                    "net_pay" => '',
                    "paid_externally" => $payrollApproveRequest->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            $payrollCustomFields = $response->payrollCustomFields;
            foreach ($payrollCustomFields as $payrollCustomField) {
                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name),
                    "user_name_last" => ucfirst($response->payrollUser->last_name),
                    "user_id" => $response->payrollUser->employee_id,
                    "category" => 'Custom Field',
                    "type" => $payrollCustomField?->getColumn?->field_name,
                    "pid_reqid" => '',
                    "customer_name" => '',
                    "comments" => $payrollCustomField?->comment,
                    "date_paid" => $payrollCustomField->is_mark_paid == "1" ? Carbon::parse($payrollCustomField->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . $payrollCustomField?->value,
                    "net_pay" => '',
                    "paid_externally" => $payrollCustomField->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            if ($response->net_pay != 0) {
                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name),
                    "user_name_last" => ucfirst($response->payrollUser->last_name),
                    "user_id" => $response->payrollUser->employee_id,
                    "category" => ucfirst('Net Pay'),
                    "type" => '',
                    "pid_reqid" => '',
                    "customer_name" => '',
                    "comments" => '',
                    "date_paid" => $response->is_mark_paid == "1" ? Carbon::parse($response->updated_at)->format("m/d/Y") : "",
                    "amount" => '',
                    "net_pay" => '$ ' . exportNumberFormat($response->net_pay),
                    "paid_externally" => $response->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            if ($response->deduction) {
                $data[] = [
                    "user_name_first" => ucfirst($response->payrollUser->first_name ?? ''),
                    "user_name_last" => ucfirst($response->payrollUser->last_name ?? ''),
                    "user_id" => $response->payrollUser->employee_id ?? '',
                    "category" => ucfirst('Deduction'),
                    "type" => '',
                    "pid_reqid" => '',
                    "customer_name" => '',
                    "comments" => '',
                    "date_paid" => $response->is_mark_paid == "1" ? Carbon::parse($response->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . isset($response->deduction) ? (0 - $response->deduction) : 0,
                    "net_pay" => '',
                    "paid_externally" => $response->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }
        }
        return $data;
    }

    private function totalNetPay()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if ($value["category"] == 'Net Pay') {
                $payValue = explode("$ ", $value["net_pay"]);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }
        return array_sum($totalPay);
    }

    private function totalAmount()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if (!empty($value["amount"])) {
                $payValue = explode("$ ", $value["amount"]);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }
        return array_sum($totalPay);
    }
}