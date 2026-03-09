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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkerAllDetailsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    private $request;
    private $rowCounter;
    private $isHistory;
    const NET_PAY = "Net Pay";
    const DATE_FORMATE = "m/d/Y";

    private $isFirstCoast;
    private bool $isMortgageCompany;

    public function __construct($request, $isHistory = 0)
    {
        $this->request = $request;
        $this->isHistory = $isHistory;
        $this->isFirstCoast = in_array(strtolower(config('app.domain_name')), ['firstcoast', 'solarstage', 'mortgagestage']);
        $this->isMortgageCompany = CompanyProfile::first()?->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE;
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
        if ($this->isFirstCoast) {
            $feeColumns = $this->isMortgageCompany
                ? ['Gross Fee']
                : ['Gross Fee'];
            return [
                [
                    'Pay Period',
                    Carbon::parse($this->request->startDate)->format(self::DATE_FORMATE).' - '.Carbon::parse($this->request->endDate)->format(self::DATE_FORMATE),
                ],
                [
                    'User ID',
                    'User Name First',
                    'User Name Last',
                    'Position',
                    'Borrower ID',
                    'Borrower Name',
                    'Branch Fee',
                    'Loan Amount',
                    ...$feeColumns,
                    'Milestone Name',
                    'Date',
                    'Category',
                    'Type',
                    'Override Over',
                    'Override Value',
                    'Adjustment',
                    'Amount',
                    'Net',
                    'Trigger',
                    'State',
                    'Paid Exter Request',
                    'Adjustment Cost Head',
                    'Deduction Limit',
                    'Total',
                    'Outstandi Is',
                    'Date Paid',
                    'Commen',
                    'Comp Rate',
                ],
            ];
        }

        $base = [
            "User Name First",
            "User Name Last",
            "Position",
            "User ID",
            "Category  ",
            "Type",
            "PID/Req ID",
            "Customer Name",
            "State",
            "Rep Redline",
            "KW  ",
            "Net EPC  ",
        ];
        $base = array_merge($base, [
            "Date  ",
            "Adders  ",
            "Adjustment  ",
            "Override Over",
            "Override Value",
            "Request ID",
            "Adjustment By",
            "Cost Head  ",
            "Deduction Amount  ",
            "Limit  ",
            "Total",
            "Outstanding  ",
            "Comments  ",
            "Date Paid",
            "Amount",
            "Net  ",
            "Paid Externally",
        ]);

        return [
            [
                "Pay Period",
                Carbon::parse($this->request->pay_period_from)->format(self::DATE_FORMATE) . " - " . Carbon::parse($this->request->pay_period_to)->format(self::DATE_FORMATE),
            ],
            $base,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header styling range: derive from actual header count so it stays correct when columns change.
        $headers = $this->headings()[1] ?? [];
        $lastHeaderColumn = Coordinate::stringFromColumnIndex(max(1, count($headers)));
        $headerRange = 'A2:'.$lastHeaderColumn.'2';

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
        /* Set word wrap in comments column (header-driven so shifting columns doesn't break styling) */
        $commentsColumn = $this->findFirstMatchingColumnLetter($headers, ['Commen', 'Comments']);
        if ($commentsColumn) {
            $sheet->getStyle($commentsColumn . '1')->getAlignment()->setWrapText(true);
        }
    }

    public function registerEvents(): array
    {
        $secondHeader = $this->generateSecondHeader();
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($secondHeader, $collectionData) {
                $this->setupSheet($event, $secondHeader);
                $this->styleRows($event, $collectionData);

                /* Set styling net column negative value */
                $headers = $this->headings()[1] ?? [];
                $netColumn = $this->findColumnLetterByHeader($headers, 'Net');
                $amountColumn = $this->findColumnLetterByHeader($headers, 'Amount');

                /* set style and formate on net pay value */
                if ($netColumn) {
                    foreach ($event->sheet->getRowIterator(3) as $row) {
                        // Get the cell value in the specified column for the current row
                        $cellValue = $event->sheet->getCell($netColumn.$row->getRowIndex())->getValue();
                        // Check if the cell value is empty or null
                        if ($cellValue) {
                            $payValue = explode("$ ", $cellValue);
                            $totalPay = isset($payValue[1]) ? floatval(str_replace(',', '', $payValue[1])) : 0.0;
                            if ($totalPay < 0) {
                                $event->sheet->setCellValue($netColumn.$row->getRowIndex(), '$ ('.exportNumberFormat(abs(floatval($totalPay))).')');
                                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color

                                $event->sheet->getStyle($netColumn.$row->getRowIndex())->applyFromArray($styleArray);
                            }
                        }
                    }
                }
                /* set sytle annd formate on amount pay value */
                if ($amountColumn) {
                    foreach ($event->sheet->getRowIterator(3) as $row) {
                        // Get the cell value in the specified column for the current row
                        $cellValue = $event->sheet->getCell($amountColumn.$row->getRowIndex())->getValue();
                        // Check if the cell value is empty or null
                        if ($cellValue) {
                            $payValue = explode("$ ", $cellValue);
                            $totalPay = isset($payValue[1]) ? floatval(str_replace(',', '', $payValue[1])) : 0.0;
                            if ($totalPay < 0) {
                                $event->sheet->setCellValue($amountColumn.$row->getRowIndex(), '$ ('.exportNumberFormat(abs(floatval($totalPay))).')');
                                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color

                                $event->sheet->getStyle($amountColumn.$row->getRowIndex())->applyFromArray($styleArray);
                            }
                        }
                    }
                }
            }
        ];
    }

    private function generateSecondHeader(): array
    {
        $headers = $this->headings()[1] ?? [];
        $length = max(1, count($headers));
        $row = array_fill(0, $length, '');
        $row[0] = 'Total';

        $amountIndex = $this->findHeaderIndex($headers, 'Amount');
        if ($amountIndex !== null) {
            $row[$amountIndex] = '$ '.exportNumberFormat($this->totalAmount());
        }
        $netIndex = $this->findHeaderIndex($headers, 'Net');
        if ($netIndex !== null) {
            $row[$netIndex] = '$ '.exportNumberFormat($this->totalNetPay());
        }

        return $row;
    }

    private function setupSheet(AfterSheet $event, array $secondHeader): void
    {
        $event->sheet->freezePane('A3');

        // Set comments column word wrap based on actual column position
        $headers = $this->headings()[1] ?? [];
        $commentsColLetter = $this->findFirstMatchingColumnLetter($headers, ['Commen', 'Comments']);
        if ($commentsColLetter) {
            $event->sheet->getStyle($commentsColLetter.':'.$commentsColLetter)->getAlignment()->setWrapText(true);
        }

        $worksheet = $event->sheet->getDelegate();
        $this->mergeCellsAndInsertRows($worksheet);
        $this->populateSecondHeader($worksheet, $secondHeader);
        $this->styleFooterColumns($event, $worksheet);

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

        // Set border styling for all columns (use actual last column so it stays correct when columns change)
        $lastColumnIndex = Coordinate::columnIndexFromString($lastColumn);
        for ($i = 2; $i <= $lastRow; $i++) {
            for ($colIndex = 1; $colIndex <= $lastColumnIndex; $colIndex++) {
                $colLetter = Coordinate::stringFromColumnIndex($colIndex);
                $event->sheet->getStyle($colLetter.$i)->applyFromArray([
                    'borders' => $this->borderStyle()
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

    private function mergeCellsAndInsertRows($worksheet): void
    {
        $worksheet->mergeCells('B1:C1');
        $lastRowIndex = $worksheet->getHighestDataRow();
        $worksheet->insertNewRowBefore($lastRowIndex + 1, 1);
    }

    private function populateSecondHeader($worksheet, array $secondHeader): void
    {
        $lastRowIndex = $worksheet->getHighestDataRow();
        $worksheet->fromArray([$secondHeader], null, 'A' . ($lastRowIndex + 1), true);
    }

    private function styleFooterColumns(AfterSheet $event, $worksheet): void
    {
        $lastRowIndex = $worksheet->getHighestDataRow();
        $headers = $this->headings()[1] ?? [];
        $amountCol = $this->findColumnLetterByHeader($headers, 'Amount');
        $netCol = $this->findColumnLetterByHeader($headers, 'Net');

        $footerColumns = ['A'.($lastRowIndex)];
        if ($amountCol) {
            $footerColumns[] = $amountCol.($lastRowIndex);
        }
        if ($netCol) {
            $footerColumns[] = $netCol.($lastRowIndex);
        }

        foreach ($footerColumns as $value) {
            $event->sheet->getStyle($value)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);
        }
    }

    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $this->rowCounter = 3; // Reset the row counter before processing each collection
        // Determine Adjustment column letter from header so shifting columns doesn't break styling
        $headers = $this->headings()[1] ?? [];
        $adjustmentColumn = $this->findColumnLetterByHeader($headers, 'Adjustment') ?? 'Q';
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'adjustment', $adjustmentColumn);
        }
    }

    private function findHeaderIndex(array $headers, string $needle): ?int
    {
        $needle = strtolower(trim($needle));
        foreach ($headers as $index => $header) {
            $value = strtolower(trim((string) $header));
            if ($value === $needle) {
                return (int) $index;
            }
        }

        return null;
    }

    private function findColumnLetterByHeader(array $headers, string $needle): ?string
    {
        $index = $this->findHeaderIndex($headers, $needle);
        if ($index === null) {
            return null;
        }

        return Coordinate::stringFromColumnIndex($index + 1);
    }

    private function findFirstMatchingColumnLetter(array $headers, array $needles): ?string
    {
        foreach ($needles as $needle) {
            $col = $this->findColumnLetterByHeader($headers, (string) $needle);
            if ($col) {
                return $col;
            }
        }

        return null;
    }

    private function applyCellStyle(AfterSheet $event, $val, $field, $columnLetter): void
    {
        if (!empty($val[$field])) {
            $payValue = explode("$ ", $val[$field]);
            $totalPay = floatval(str_replace(',', '', $payValue[1]));
            $cellAddress = $columnLetter . ($this->rowCounter++);
            $styleArray = [
                'font' => [
                    'bold' => false,
                    "size" => 12,
                ],
            ];
            if ($totalPay < 0) {
                $event->sheet->setCellValue($cellAddress, "$ (" . exportNumberFormat(abs(floatval($totalPay))) . ")");
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
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
            return PayrollHistory::with([
                "payrollUser",
                "positionDetail",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollOverrides.payrollOverUser",
                "payrollClawBacks.payrollSaleData",
                "payrollClawBacks.payrollOverUser",
                "payrollPayrollAdjustmentDetails.payrollCommentedBy",
                "payrollApproveRequest" => function ($q) {
                    $q->where(['status' => 'Paid']);
                },
                "payrollApproveRequest.payrollAdjustment",
                "payrollApproveRequest.payrollComments",
                "payrollApproveRequest.payrollCommentedBy",
                "payrollApproveRequest.payrollCostCenter",
                "payrollDeductions.payrollCostCenter",
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
                "positionDetail",
                "payrollCommissions.payrollSaleData",
                "payrollOverrides.payrollSaleData",
                "payrollOverrides.payrollOverUser",
                "payrollClawBacks.payrollSaleData",
                "payrollClawBacks.payrollOverUser",
                "payrollPayrollAdjustmentDetails.payrollCommentedBy",
                "payrollApproveRequest" => function ($q) {
                    $q->where(['status' => 'Accept']);
                },
                "payrollApproveRequest.payrollAdjustment",
                "payrollApproveRequest.payrollComments",
                "payrollApproveRequest.payrollCommentedBy",
                "payrollApproveRequest.payrollCostCenter",
                "payrollDeductions.payrollCostCenter",
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
        $finalData = [];
        foreach ($payrollData as $payrollDataValue) {
            foreach ($payrollDataValue->payrollCommissions as $payrollCommission) {
                $finalData[] = $this->sheetRowCreate($payrollCommission, $payrollDataValue, "Commission");
            }
            foreach ($payrollDataValue->payrollOverrides as $payrollOverride) {
                $finalData[] = $this->sheetRowCreate($payrollOverride, $payrollDataValue, "Override");
            }
            foreach ($payrollDataValue->payrollClawBacks as $payrollClawBack) {
                if ($payrollClawBack->type == "commission") {
                    $finalData[] = $this->sheetRowCreate($payrollClawBack, $payrollDataValue, "Commission ClawBack");
                } else {
                    $finalData[] = $this->sheetRowCreate($payrollClawBack, $payrollDataValue, "Override ClawBack");
                }
            }
            foreach ($payrollDataValue->payrollPayrollAdjustmentDetails as $payrollPayrollAdjustmentDetail) {
                $finalData[] = $this->sheetRowCreate($payrollPayrollAdjustmentDetail, $payrollDataValue, "Adjustment");
            }
            foreach ($payrollDataValue->payrollApproveRequest as $payrollApproveRequest) {
                if ($payrollApproveRequest->adjustment_type_id == 2) {
                    $finalData[] = $this->sheetRowCreate($payrollApproveRequest, $payrollDataValue, "Reimbursement");
                } else {
                    $finalData[] = $this->sheetRowCreate($payrollApproveRequest, $payrollDataValue, "Adjustment");
                }
            }
            foreach ($payrollDataValue->payrollDeductions as $payrollDeduction) {
                $finalData[] = $this->sheetRowCreate($payrollDeduction, $payrollDataValue, "Deduction");
            }
            foreach ($payrollDataValue->payrollCustomFields as $payrollCustomField) {
                $finalData[] = $this->sheetRowCreate($payrollCustomField, $payrollDataValue, "Custom Fields");
            }
            $finalData[] = $this->sheetRowCreate("", $payrollDataValue, self::NET_PAY);
        }

        return $finalData;
    }

    private function sheetRowCreate($request, $payrollDataResponse, $categoryType)
    {
        // Initialize FirstCoast-specific variables
        $milestoneName = null;
        $trigger = null;
        $compRate = null;

        switch ($categoryType) {
            case 'Commission':
                $type = $request->amount_type;
                $pid = $request->pid;
                $customerName = ucfirst($request?->payrollSaleData?->customer_name);
                $state = strtoupper($request?->payrollSaleData?->customer_state);
                $redline = $request->redline;
                $kw = $request?->kw;
                $netEpc = $request?->net_epc;
                $compRate = $request?->comp_rate;
                $address = $request?->payrollSaleData?->adders;
                $date = date(self::DATE_FORMATE, strtotime($request->date));
                $amount = null;
                $overrideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = null;
                $totalAmount = exportNumberFormat($request?->amount);
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : "";
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields
                $milestoneName = $request?->payrollSaleData?->milestone_name ?? null;
                $trigger = $request?->trigger ?? null;
                break;
            case 'Override':
                $type = $request->type;
                $pid = $request->pid;
                $customerName = ucfirst($request?->payrollSaleData?->customer_name);
                $state = null;
                $redline = null;
                $kw = $request?->payrollSaleData?->kw;
                $netEpc = null;
                $address = $request?->payrollSaleData?->adders;
                $date = $request?->payrollSaleData ? date(self::DATE_FORMATE, strtotime($request?->payrollSaleData?->m2_date)) : "";
                $amount = null;
                $overrideOver = ucfirst($request?->payrollOverUser?->first_name) . " " . ucfirst($request?->payrollOverUser?->last_name);
                $overrideValue = $request->type !== "Stack" ? "$ " . $request?->overrides_amount . " " . $request?->overrides_type : $request?->overrides_amount . " %";
                $requestId = null;
                $approvedBy = null;
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = null;
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : "";
                $totalAmount = exportNumberFormat($request?->amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields
                $milestoneName = $request?->payrollSaleData?->milestone_name ?? null;
                $trigger = $request?->trigger ?? null;
                break;
            case 'Commission ClawBack':
                $type = $request->adders_type;
                $pid = $request->pid;
                $customerName = ucfirst($request?->payrollSaleData?->customer_name);
                $state = strtoupper($request?->payrollSaleData?->customer_state);
                $redline = $request->redline;
                $kw = $request?->kw;
                $netEpc = $request?->net_epc;
                $compRate = $request?->comp_rate;
                $address = $request?->payrollSaleData?->adders;
                $date = $request?->payrollSaleData ? date(self::DATE_FORMATE, strtotime($request?->payrollSaleData?->date_cancelled)) : "";
                $amount = null;
                $overrideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = null;
                $totalAmount = exportNumberFormat($request?->clawback_amount);
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : "";
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields
                $milestoneName = $request?->payrollSaleData?->milestone_name ?? null;
                $trigger = $request?->trigger ?? null;
                break;
            case 'Override ClawBack':
                $type = $request->adders_type;
                $pid = $request->pid;
                $customerName = ucfirst($request?->payrollSaleData?->customer_name);
                $state = null;
                $redline = null;
                $kw = $request?->payrollSaleData?->kw;
                $netEpc = null;
                $address = $request?->payrollSaleData?->adders;
                $date = $request?->payrollSaleData ? date(self::DATE_FORMATE, strtotime($request?->payrollSaleData?->date_cancelled)) : "";
                $amount = null;
                $overrideOver = ucfirst($request?->payrollOverUser?->first_name) . " " . ucfirst($request?->payrollOverUser?->last_name);
                $overrideValue = $request->adders_type !== "Stack" ? "$ " . $request?->clawback_cal_amount . " " . $request?->clawback_cal_type : $request?->clawback_cal_amount . " %";
                $requestId = null;
                $approvedBy = null;
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = null;
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : "";
                $totalAmount = exportNumberFormat($request?->clawback_amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields
                $milestoneName = $request?->payrollSaleData?->milestone_name ?? null;
                $trigger = $request?->trigger ?? null;
                break;
            case 'Adjustment':
                $type = $request?->adjustment?->name ?? $request?->payroll_type;
                $pid = $request?->req_no ?? $request?->pid;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = date(self::DATE_FORMATE, strtotime($request?->updated_at));
                $amount = exportNumberFormat($request?->amount);
                $overrideOver = null;
                $overrideValue = null;
                $requestId = $request->req_no;
                $approvedBy = $request?->payrollCommentedBy ? ucfirst($request?->payrollCommentedBy?->first_name) . " " . ucfirst($request?->payrollCommentedBy?->last_name) : "";
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = $request->description ? $request->description : $request->comment;
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : "";
                $totalAmount = exportNumberFormat($request?->adjustment?->name == "Fine/fee" ? "-" . $request?->amount : $request?->amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields (not applicable for adjustments)
                $milestoneName = null;
                $trigger = null;
                break;
            case 'Reimbursement':
                $type = $request?->adjustment?->name;
                $pid = $request->req_no;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = date(self::DATE_FORMATE, strtotime($request->cost_date));
                $amount = null;
                $overrideOver = null;
                $overrideValue = null;
                $requestId = $request?->req_no;
                $approvedBy = ucfirst($request?->payrollCommentedBy?->first_name) . " " . ucfirst($request?->payrollCommentedBy?->last_name);
                $limit = null;
                $outstanding = null;
                $costCenter = ucfirst($request?->payrollCostCenter?->name);
                $deductionAmount = null;
                $comments = $request->description;
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : "";
                $totalAmount = exportNumberFormat($request?->amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields (not applicable for reimbursements)
                $milestoneName = null;
                $trigger = null;
                break;
            case 'Deduction':
                $type = null;
                $pid = null;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = null;
                $amount = null;
                $overrideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $limit = exportNumberFormat($request?->limit);
                $outstanding = exportNumberFormat($request?->outstanding);
                $costCenter = ucfirst($request?->payrollCostCenter?->name);
                $deductionAmount = exportNumberFormat($request?->amount);
                $comments = null;
                $datePaid = null;
                $totalAmount = exportNumberFormat($request?->total);
                $netPay = null;
                $paidExternally = null;
                
                // FirstCoast-specific fields (not applicable for deductions)
                $milestoneName = null;
                $trigger = null;
                break;
            case "Custom Fields":
                $type = $request?->getColumn?->field_name;
                $pid = null;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = null;
                $amount = null;
                $overrideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = $request?->comment;
                $totalAmount = $request?->value;
                $datePaid = $request->is_mark_paid == "1" ? Carbon::parse($request->updated_at)->format("m/d/Y") : "";
                $netPay = null;
                $paidExternally = $request->is_mark_paid == "1" ? "Paid Externally" : "";
                
                // FirstCoast-specific fields (not applicable for custom fields)
                $milestoneName = null;
                $trigger = null;
                break;
            default:
                $type = "";
                $pid = null;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = null;
                $amount = null;
                $overrideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $limit = null;
                $outstanding = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = null;
                $totalAmount = null;
                $datePaid = null;
                $netPay = exportNumberFormat($payrollDataResponse->net_pay);
                $paidExternally = null;
                
                // FirstCoast-specific fields (not applicable for net pay)
                $milestoneName = null;
                $trigger = null;
                break;
        }


        if ($this->isFirstCoast) {
            // Format redline as percentage (e.g., 1 as 1%)
            $formattedRedline = $redline ? $redline.'%' : null;

            // Format net_epc as percentage.
            // Mortgage: 4 decimals (e.g., 0.02574 => 2.5740%), others: 2 decimals.
            $netEpcDecimals = $this->isMortgageCompany ? 4 : 2;
            $formattedNetEpc = $netEpc ? number_format($netEpc * 100, $netEpcDecimals, '.', '').'%' : null;

            // Mortgage-only comp rate, shown as percent with 4 decimals.
            $formattedCompRate = null;
            if ($this->isMortgageCompany && is_numeric($compRate)) {
                $formattedCompRate = number_format((float) $compRate, 4, '.', '').'%';
            }

            // Format override_value as percentage (extract numeric value from formats like "$ 0.25 Replace" or "0.25 %")
            $formattedOverrideValue = null;
            if ($overrideValue) {
                // Extract numeric value using regex
                if (preg_match('/[\d.]+/', $overrideValue, $matches)) {
                    $numericValue = floatval($matches[0]);
                    $formattedOverrideValue = number_format($numericValue, 2, '.', '').'%';
                } else {
                    $formattedOverrideValue = $overrideValue;
                }
            }

            return [
                'user_id' => $payrollDataResponse->usersData->employee_id,
                'user_name_first' => ucfirst($payrollDataResponse->usersData->first_name),
                'user_name_last' => ucfirst($payrollDataResponse->usersData->last_name),
                'position' => ucfirst($payrollDataResponse?->positionDetail?->position_name),
                'borrower_id' => $pid,
                'borrower_name' => ucfirst($customerName),
                'office_fee' => $formattedRedline,
                'loan_amount' => $kw,
                'gross_fee' => $formattedNetEpc,
                'milestone_name' => $milestoneName,
                'date' => $date,
                'category' => ucfirst($categoryType),
                'type' => $this->processType($type),
                'override_over' => ucfirst($overrideOver),
                'override_value' => $formattedOverrideValue,
                'adjustment' => $amount ? '$ '.$amount : null,
                'amount' => $totalAmount ? '$ '.$totalAmount : null,
                'net' => $netPay ? '$ '.$netPay : null,
                'trigger' => $trigger,
                'state' => strtoupper($state),
                'paid_externally' => $paidExternally ? $paidExternally : null,
                'cost_head' => $costCenter,
                'limit' => @$limit,
                'total' => $deductionAmount,
                'outstanding' => @$outstanding,
                'date_paid' => $datePaid,
                'comments' => $comments,
                'comp_rate' => $formattedCompRate,
            ];
        }

        return [
            "user_name_first" => ucfirst($payrollDataResponse->payrollUser->first_name),
            "user_name_last" => ucfirst($payrollDataResponse->payrollUser->last_name),
            "position" => ucfirst($payrollDataResponse?->positionDetail?->position_name),
            "user_id" => $payrollDataResponse->payrollUser->employee_id,
            "category" => ucfirst($categoryType),
            "type" => $this->processType($type),
            "pid/reqId" => $pid,
            "customer_name" => ucfirst($customerName),
            "state" => strtoupper($state),
            "rep_redline" => $redline,
            "kw" => $kw,
            "net_epc" => $netEpc,
            "date" => $date,
            "address" => $address,
            "adjustment" => $amount ? "$ " . $amount : null,
            "override_over" => ucfirst($overrideOver),
            "override_value" => $overrideValue,
            "request_id" => $requestId,
            "adjustment_by" => $approvedBy,
            "cost_head" => $costCenter,
            "deduction_amount" => $deductionAmount,
            "limit" => $limit,
            "total" => $deductionAmount,
            "outstanding" => $outstanding,
            "comments" => $comments,
            "date_paid" => $datePaid,
            "amount" => $totalAmount ? "$ " . $totalAmount : null,
            "net" => $netPay ? "$ " . $netPay : null,
            "paid_externally" => $paidExternally ? $paidExternally : null
        ];
    }

    private function processType($type)
    {
        // Process type: if "M2" make it blank, if "M2 update" make it "update", if "direct" make it "recruit"
        if ($type === null || $type === '') {
            return '';
        }

        $typeTrimmed = trim((string)$type);
        $typeLower = strtolower($typeTrimmed);

        // Check for "M2 update" or "M2 lodate" first (before checking just "M2")
        if (stripos($typeTrimmed, 'm2 update') !== false || stripos($typeTrimmed, 'm2 lodate') !== false) {
            return 'Update';
        } elseif ($typeLower === 'm2') {
            return '';
        } elseif ($typeLower === 'direct') {
            return 'Recruit';
        }

        return ucfirst($typeTrimmed);
    }

    private function totalNetPay()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if ($value["category"] === self::NET_PAY && !empty($value['net'])) {
                $payValue = explode("$ ", $value["net"]);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }
        return array_sum($totalPay);
    }

    private function totalAmount()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if (!empty($value['amount'])) {
                $payValue = explode("$ ", $value["amount"]);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }
        return array_sum($totalPay);
    }
}
