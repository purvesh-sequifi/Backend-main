<?php

namespace App\Exports\Admin\PayrollExport;

use App\Models\CompanyProfile;
use App\Models\User;
use App\Models\Payroll;
use App\Models\PayrollHistory;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PidDetailExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    private $request;
    private $isHistory;

    private $isFirstCoast;
    private bool $isMortgageCompany;

    /**
     * Cached collection to avoid multiple data fetches
     */
    private $cachedCollection = null;

    /**
     * Cached total amount to avoid recalculating
     */
    private $cachedTotal = null;

    public function __construct($request, $isHistory = 0)
    {
        $this->request = $request;
        $this->isHistory = $isHistory;
        $this->isFirstCoast = in_array(strtolower(config('app.domain_name')), ['firstcoast', 'solarstage', 'mortgagestage']);
        $this->isMortgageCompany = CompanyProfile::first()?->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE;
    }

    public function collection()
    {
       
        // Return cached collection if already calculated
        if ($this->cachedCollection !== null) {
            return $this->cachedCollection;
        }

        $payrollData = $this->getPayrollData();
        if ($payrollData->isEmpty()) {
            $this->cachedCollection = collect([]);
            $this->cachedTotal = 0;
            return $this->cachedCollection;
        }

        $finalData = $this->transformPayrollData($payrollData);
        usort($finalData, function ($a, $b) {
            return strcmp($a['pid'] ?? '', $b['pid'] ?? '');
        });
        
        // Calculate total once during transformation
        $this->cachedTotal = $this->calculateTotal($finalData);
        $this->cachedCollection = collect($finalData);
        
        return $this->cachedCollection;
    }

    public function headings(): array
    {
        if ($this->isFirstCoast) {
            $base = [
                    "Borrower ID",
                    "Payment Type",
                    "Category",
                    "User Name",
                    "User ID",
                    "Borrower Name",
                    "Comments",
                    "Date Paid",
                    "Amount  ",
                "Paid Externally",
            ];
            if ($this->isMortgageCompany) {
                $base[] = "Comp Rate";
                $base[] = "Fee %";
            }
        return [
            [
                "Pay Period:",
                    Carbon::parse($this->request->startDate)->format('m/d/Y') . " - " . Carbon::parse($this->request->endDate)->format('m/d/Y'),
            ],
                $base,
            ];
        }

        $base = [
                "PID",
                "Payment Type",
                "Category",
                "User Name",
                "User ID",
                "Customer Name",
                "Comments",
                "Date Paid",
                "Amount  ",
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
        $headerRange = $this->isMortgageCompany ? 'A2:L2' : 'A2:J2';
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
            "$ " . ($this->totalAmount())
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
                $footerColumns = ['A' . ($lastRowIndex + 2), 'J' . ($lastRowIndex + 2)];
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
                        $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));;
                        $cellAddress1 = "I" . $rowCounter;
                        $event->sheet->setCellValue($cellAddress1, "$ " . exportNumberFormat($totalPay1));
                        $styleArray1 = [
                            'font' => [
                                'bold' => false,
                                "size" => 12,
                            ]
                        ];
                        if ($totalPay1 < 0) {
                            $event->sheet->setCellValue($cellAddress1, "$ (" . exportNumberFormat(abs($totalPay1)) . ")");
                            $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }

                        $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);
                    }
                    $rowCounter++;
                }

                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();
                $lastColumn = $event->sheet->getDelegate()->getHighestDataColumn();
                $evenRowColor = 'f0f0f0'; // Light green
                $oddRowColor = 'FFFFFF'; // White

                for ($row = 1; $row <= $lastRow; $row++) {
                    $fillColor = $row % 2 == 0 ? $evenRowColor : $oddRowColor;
                    $event->sheet->getStyle("A$row:$lastColumn$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $fillColor],
                        ],
                    ]);
                }
                for ($i = 2; $i <= $lastRow; $i++) {
                    $endCol = $this->isMortgageCompany ? 'M' : 'K'; // exclusive
                    for ($col = 'A'; $col != $endCol; ++$col) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle()
                        ]);
                    }
                }

                $headerRange = $this->isMortgageCompany ? 'A2:L2' : 'A2:J2';
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
                $event->sheet->setCellValue("A" . $lastRowIndex + 2, "Total");
                $event->sheet->setCellValue("I" . $lastRowIndex + 2, "$ " . exportNumberFormat($this->totalAmount()));
                $styleArray['font'] = ['bold' => true]; // Red color
                if ($this->totalAmount() < 0) {
                    $event->sheet->setCellValue("I" . $lastRowIndex + 2, "$ (" . exportNumberFormat(abs($this->totalAmount())) . ")");
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle("I" . $lastRowIndex + 2)->applyFromArray($styleArray);
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
                "payrollOverrides.payrollOverUser",
                "payrollClawBacks.payrollSaleData",
                "payrollClawBacks.payrollOverUser",
                "payrollPayrollAdjustmentDetails.payrollSaleData",
                "payrollApproveRequest" => function ($q) {
                    $q->where('status', 'Accept');
                },
                "payrollApproveRequest.adjustment",
                "payrollCustomFields.getColumn"
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
            return Payroll::with([
                "payrollUser",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollOverrides.payrollOverUser",
                "payrollClawBacks.payrollSaleData",
                "payrollClawBacks.payrollOverUser",
                "payrollPayrollAdjustmentDetails.payrollSaleData",
                "payrollApproveRequest" => function ($q) {
                    $q->where('status', 'Accept');
                },
                "payrollApproveRequest.adjustment",
                "payrollCustomFields.getColumn"
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
                    "pid" => $payrollCommission?->pid,
                    "payment_type" => 'Commission',
                    "category" => ucfirst(data_get($payrollCommission, 'schema_type')),
                    "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                    "user_id" => $response?->payrollUser?->employee_id,
                    "customer_name" => isset($payrollCommission?->payrollSaleData?->customer_name) ? $payrollCommission?->payrollSaleData?->customer_name : null,
                    "comments" => '',
                    "date_paid" => ($payrollCommission?->is_mark_paid == "1" && $payrollCommission?->updated_at) ? Carbon::parse($payrollCommission?->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($payrollCommission?->amount),
                    "Paid_externally" => $payrollCommission?->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => $formattedCompRate,
                        "fee_percentage" => $formattedFeePercentage,
                    ] : []),
                ];
            }

            $payrollOverrides = $response->payrollOverrides;
            foreach ($payrollOverrides as $payrollOverride) {
                $data[] = [
                    "pid" => $payrollOverride?->pid,
                    "payment_type" => ucfirst('Override'),
                    "category" => ucfirst($payrollOverride?->type),
                    "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                    "user_id" => $response?->payrollUser?->employee_id,
                    "customer_name" => isset($payrollOverride?->payrollSaleData?->customer_name) ? $payrollOverride?->payrollSaleData?->customer_name : null,
                    "comments" => '',
                    "date_paid" => ($payrollOverride?->is_mark_paid == "1" && $payrollOverride?->updated_at) ? Carbon::parse($payrollOverride?->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($payrollOverride?->amount),
                    "Paid_externally" => $payrollOverride?->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            $payrollClawBacks = $response->payrollClawBacks;
            foreach ($payrollClawBacks as $payrollClawBack) {
                $amount = isset($payrollClawBack?->clawback_amount) ? (0 - $payrollClawBack?->clawback_amount) : 0;
                if ($payrollClawBack->type == "commission") {
                    $formattedCompRate = '';
                    if ($this->isMortgageCompany && is_numeric($payrollClawBack->comp_rate ?? null)) {
                        $formattedCompRate = number_format((float) $payrollClawBack->comp_rate, 4, '.', '').'%';
                    }
                    $formattedFeePercentage = '';
                    $netEpc = $payrollClawBack?->payrollSaleData?->net_epc;
                    if ($this->isMortgageCompany && is_numeric($netEpc)) {
                        $formattedFeePercentage = number_format(((float) $netEpc) * 100, 4, '.', '').'%';
                    }
                    $data[] = [
                        "pid" => $payrollClawBack?->pid,
                        "payment_type" => ucfirst('Commission'),
                        "category" => ucfirst('Clawback'),
                        "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                        "user_id" => $response?->payrollUser?->employee_id,
                        "customer_name" => isset($payrollClawBack?->payrollSaleData?->customer_name) ? $payrollClawBack?->payrollSaleData?->customer_name : null,
                        "comments" => '',
                        "date_paid" => ($payrollClawBack?->is_mark_paid == "1" && $payrollClawBack?->updated_at) ? Carbon::parse($payrollClawBack?->updated_at)->format("m/d/Y") : "",
                        "amount" => "$ " . exportNumberFormat($amount),
                        "Paid_externally" => $payrollClawBack?->is_mark_paid == "1" ? "Paid Externally" : "",
                        ...($this->isMortgageCompany ? [
                            "comp_rate" => $formattedCompRate,
                            "fee_percentage" => $formattedFeePercentage,
                        ] : []),
                    ];
                } else {
                    $data[] = [
                        "pid" => $payrollClawBack?->pid,
                        "payment_type" => ucfirst('Override'),
                        "category" => ucfirst('Clawback'),
                        "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                        "user_id" => $response?->payrollUser?->employee_id,
                        "customer_name" => isset($payrollClawBack?->payrollSaleData?->customer_name) ? $payrollClawBack?->payrollSaleData?->customer_name : null,
                        "comments" => '',
                        "date_paid" => ($payrollClawBack?->is_mark_paid == "1" && $payrollClawBack?->updated_at) ? Carbon::parse($payrollClawBack?->updated_at)->format("m/d/Y") : "",
                        "amount" => "$ " . exportNumberFormat($amount),
                        "Paid_externally" => $payrollClawBack?->is_mark_paid == "1" ? "Paid Externally" : "",
                        ...($this->isMortgageCompany ? [
                            "comp_rate" => '',
                            "fee_percentage" => '',
                        ] : []),
                    ];
                }
            }

            $payrollPayrollAdjustmentDetails = $response->payrollPayrollAdjustmentDetails;
            foreach ($payrollPayrollAdjustmentDetails as $payrollPayrollAdjustmentDetail) {
                $data[] = [
                    "pid" => $payrollPayrollAdjustmentDetail?->pid,
                    "payment_type" => ucfirst('Adjustment'),
                    "category" => ucfirst($payrollPayrollAdjustmentDetail?->payroll_type),
                    "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                    "user_id" => $response?->payrollUser?->employee_id,
                    "customer_name" => isset($payrollPayrollAdjustmentDetail?->payrollSaleData?->customer_name) ? $payrollPayrollAdjustmentDetail?->payrollSaleData?->customer_name : null,
                    "comments" => $payrollPayrollAdjustmentDetail?->comment,
                    "date_paid" => ($payrollPayrollAdjustmentDetail?->is_mark_paid == "1" && $payrollPayrollAdjustmentDetail?->updated_at) ? Carbon::parse($payrollPayrollAdjustmentDetail?->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($payrollPayrollAdjustmentDetail?->amount),
                    "Paid_externally" => $payrollPayrollAdjustmentDetail?->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            $approvalsAndRequests = $response->payrollApproveRequest;
            foreach ($approvalsAndRequests as $approvalsAndRequest) {
                if ($approvalsAndRequest->adjustment_type_id == 5) {
                    $category = 'Adjustment';
                    $type = isset($approvalsAndRequest->adjustment->name) ? $approvalsAndRequest->adjustment->name : '';
                    $amount = ($approvalsAndRequest->amount < 0) ? $approvalsAndRequest->amount : (0 - $approvalsAndRequest->amount);
                } else {
                    $category = isset($approvalsAndRequest->adjustment->name) ? $approvalsAndRequest->adjustment->name : '';
                    $type = '';
                    $amount = $approvalsAndRequest->amount;
                }

                $data[] = [
                    "pid" => "",
                    "payment_type" => ucfirst($category),
                    "category" => ucfirst($type),
                    "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                    "user_id" => $response?->payrollUser?->employee_id,
                    "customer_name" => "",
                    "comments" => $approvalsAndRequest?->description,
                    "date_paid" => ($approvalsAndRequest?->is_mark_paid == "1" && $approvalsAndRequest?->updated_at) ? Carbon::parse($approvalsAndRequest?->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($amount),
                    "Paid_externally" => $approvalsAndRequest->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }

            $payrollCustomFields = $response->payrollCustomFields;
            foreach ($payrollCustomFields as $payrollCustomField) {
                $data[] = [
                    "pid" => "",
                    "payment_type" => "Custom Field",
                    "category" => isset($payrollCustomField?->getColumn?->field_name) ? $payrollCustomField?->getColumn?->field_name : "",
                    "user_name" => ucfirst($response?->payrollUser?->first_name) . ' ' . ucfirst($response?->payrollUser?->last_name),
                    "user_id" => $response?->payrollUser?->employee_id,
                    "customer_name" => "",
                    "comments" => $payrollCustomField?->comment,
                    "date_paid" => ($payrollCustomField?->is_mark_paid == "1" && $payrollCustomField?->updated_at) ? Carbon::parse($payrollCustomField?->updated_at)->format("m/d/Y") : "",
                    "amount" => "$ " . exportNumberFormat($payrollCustomField?->value),
                    "Paid_externally" => $payrollCustomField?->is_mark_paid == "1" ? "Paid Externally" : "",
                    ...($this->isMortgageCompany ? [
                        "comp_rate" => '',
                        "fee_percentage" => '',
                    ] : []),
                ];
            }
        }
        return $data;
    }

    private function totalAmount()
    {
        // Ensure collection is calculated first (which will also calculate total)
        if ($this->cachedTotal === null) {
            $this->collection();
        }
        
        // Return cached total to avoid recalculating
        return $this->cachedTotal ?? 0;
    }

    /**
     * Calculate total amount from final data array
     */
    private function calculateTotal(array $finalData): float
    {
        $totalPay = [];
        foreach ($finalData as $value) {
            if (!empty($value['amount'])) {
                $payValue = explode("$ ", $value["amount"]);
                if (isset($payValue[1])) {
                    $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
                }
            }
        }
        return array_sum($totalPay);
    }
}
