<?php

namespace App\Exports\Admin\PayrollReportExport;

use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CustomFieldHistory;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollHistory;
use App\Models\User;
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

class WorkerAllDetailsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    private $request;

    private $rowCounter;

    const NET_PAY = 'Net Pay';

    const DATE_FORMATE = 'm/d/Y';

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection(): Collection
    {
        $requestData = $this->request;

        $payrollData = $this->getPayrollData($requestData);

        if ($payrollData->isEmpty()) {
            return collect([]);
        }
        $transformPayrollData = collect($this->transformPayrollData($payrollData));

        return $transformPayrollData;
    }

    /* Set header in the sheet */
    public function headings(): array
    {
        return [
            [
                'Pay Period',
                Carbon::parse($this->request->startDate)->format(self::DATE_FORMATE).' - '.Carbon::parse($this->request->endDate)->format(self::DATE_FORMATE),
            ],
            [
                'User Name First',
                'User Name Last',
                'Position',
                'User ID',
                'Category  ',
                'Type',
                'PID/Req ID',
                'Customer Name',
                'State',
                'Rep Redline',
                'KW  ',
                'Net EPC  ',
                'Date  ',
                'Adders  ',
                'Adjustment  ',
                'Override Over',
                'Override Value',
                'Request ID',
                'Adjustment By',
                'Cost Head  ',
                'Deduction Amount  ',
                'Limit  ',
                'Total',
                'Outstanding  ',
                'Comments  ',
                'Date Paid',
                'Amount',
                'Net  ',
                'Paid Externally',
            ],
        ];
    }

    /* Set style sheet */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:AC2')->applyFromArray([
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
        /* Set word wrap in Y column comments */
        $sheet->getStyle('Y1')->getAlignment()->setWrapText(true);
    }

    /* set footer value */
    public function registerEvents(): array
    {
        $secondHeader = $this->generateSecondHeader();
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($secondHeader, $collectionData) {
                $this->setupSheet($event, $secondHeader);
                $this->styleRows($event, $collectionData);

                /* Set styling net column negative value */
                $column = 'AB';

                /* set style and formate on net pay value */
                foreach ($event->sheet->getRowIterator(3) as $row) {
                    // Get the cell value in the specified column for the current row
                    $cellValue = $event->sheet->getCell($column.$row->getRowIndex())->getValue();
                    // Check if the cell value is empty or null
                    if ($cellValue) {
                        $payValue = explode('$ ', $cellValue);
                        $totalPay = floatval(str_replace(',', '', $payValue[1]));
                        if ($totalPay < 0) {
                            $event->sheet->setCellValue($column.$row->getRowIndex(), '$ ('.exportNumberFormat(abs(floatval($totalPay))).')');
                            $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color

                            $event->sheet->getStyle($column.$row->getRowIndex())->applyFromArray($styleArray);
                        }
                    }
                }
                /* set sytle annd formate on amount pay value */
                $column = 'AA';
                foreach ($event->sheet->getRowIterator(3) as $row) {
                    // Get the cell value in the specified column for the current row
                    $cellValue = $event->sheet->getCell($column.$row->getRowIndex())->getValue();
                    // dump($cellValue);/
                    // Check if the cell value is empty or null
                    if ($cellValue) {
                        $payValue = explode('$ ', $cellValue);
                        $totalPay = floatval(str_replace(',', '', $payValue[1]));
                        if ($totalPay < 0) {
                            $event->sheet->setCellValue($column.$row->getRowIndex(), '$ ('.exportNumberFormat(abs(floatval($totalPay))).')');
                            $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color

                            $event->sheet->getStyle($column.$row->getRowIndex())->applyFromArray($styleArray);
                        }
                    }
                }
            },
        ];
    }

    private function generateSecondHeader(): array
    {
        return [
            'Total',
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            '$ '.exportNumberFormat($this->totalAmount()),
            '$ '.exportNumberFormat($this->totalNetPay()),
        ];
    }

    private function setupSheet(AfterSheet $event, array $secondHeader): void
    {
        $event->sheet->freezePane('A3');
        $event->sheet->getStyle('Y:Y')->getAlignment()->setWrapText(true);

        $worksheet = $event->sheet->getDelegate();
        $this->mergeCellsAndInsertRows($worksheet);
        $this->populateSecondHeader($worksheet, $secondHeader);
        $this->styleFooterColumns($event, $worksheet);

        $lastRow = $event->sheet->getDelegate()->getHighestDataRow();

        // Define the colors for alternating rows
        $evenRowColor = 'f0f0f0'; // Light green
        $oddRowColor = 'FFFFFF'; // White

        for ($row = 4; $row <= $lastRow; $row += 2) {
            $event->sheet->getStyle("$row:$row")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $evenRowColor],
                ],
            ]);
        }

        for ($row = 3; $row <= $lastRow; $row += 2) {
            $event->sheet->getStyle("$row:$row")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $oddRowColor],
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
        $worksheet->fromArray([$secondHeader], null, 'A'.($lastRowIndex + 1), true);
    }

    private function styleFooterColumns(AfterSheet $event, $worksheet): void
    {
        $lastRowIndex = $worksheet->getHighestDataRow();
        $footerColumns = ['A'.($lastRowIndex), 'AA'.($lastRowIndex), 'AB'.($lastRowIndex)];
        foreach ($footerColumns as $value) {
            $event->sheet->getStyle($value)->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
            ]);
        }
    }

    //    /*  private function styleRows(AfterSheet $event, $collectionData): void
    //     {
    //         $rowCounter = 3;
    //         foreach ($collectionData as $val) {
    //             if (!empty($val['amount'])) {
    //                 $payValue1 = explode("$ ", $val["amount"]);
    //                 $totalPay1 = intval($payValue1["1"]);
    //                 // Calculate the cell address
    //                 $cellAddress1 = "AA" . $rowCounter;
    //                 // Apply styles based on the condition
    //                 $styleArray1 = [
    //                     'font' => [
    //                         'bold' => false,
    //                         "size" => 12,
    //                     ],
    //                 ];
    //                 $event->sheet->setCellValue($cellAddress1, "$ " . abs(floatval($totalPay1)));
    //                 if ($totalPay1 < 0) {
    //                     $event->sheet->setCellValue($cellAddress1, "$ (" . abs(floatval($totalPay1)) . ")");
    //                     // Set text color to red for negative values
    //                     $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
    //                 }
    //                 $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);
    //             }
    //             if ($val["category"] === self::NET_PAY && !empty($val['net'])) {
    //                 $payValue = explode("$ ", $val["net"]);
    //                 $totalPay = intval($payValue["1"]);
    //                 // Calculate the cell address
    //                 $cellAddress = "AB" . $rowCounter;
    //                 // Apply styles based on the condition
    //                 $styleArray = [
    //                     'font' => [
    //                         'bold' => false,
    //                         "size" => 12,
    //                     ],

    //                 ];
    //                 $event->sheet->setCellValue($cellAddress, "$ " . abs(floatval($totalPay)));
    //                 if ($totalPay < 0) {
    //                     $event->sheet->setCellValue($cellAddress, "$ (" . abs(floatval($totalPay)) . ")");
    //                     // Set text color to red for negative values
    //                     $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
    //                 }
    //                 $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
    //                 // Increment the row counter

    //             }
    //             /* adjustment value negetive font color */
    //             if (!empty($val['adjustment'])) {
    //                 $payValue2 = explode("$ ", $val['adjustment']);
    //                 $totalPay2 = intval($payValue2["1"]);
    //                 // Calculate the cell address
    //                 $cellAddress2 = "O" . $rowCounter;
    //                 $event->sheet->setCellValue($cellAddress2, "$ " . abs(floatval($totalPay2)));
    //                 $styleArray2 = [
    //                     'font' => [
    //                         'bold' => false,
    //                         "size" => 12,
    //                     ],

    //                 ];
    //                 if ($totalPay2 < 0) {
    //                     $event->sheet->setCellValue($cellAddress2, "$ (" . abs(floatval($totalPay2)) . ")");

    //                     $styleArray2['font']['color'] = ['rgb' => 'FF0000']; // Red color
    //                 }
    //                 $event->sheet->getStyle($cellAddress2)->applyFromArray($styleArray2);
    //             }
    //             $rowCounter++;
    //         }
    //     } */
    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $this->rowCounter = 3; // Reset the row counter before processing each collection
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'adjustment', 'O');
        }
    }

    private function applyCellStyle(AfterSheet $event, $val, $field, $columnLetter): void
    {
        if (! empty($val[$field])) {
            $payValue = explode('$ ', $val[$field]);
            $totalPay = floatval(str_replace(',', '', $payValue[1]));
            // Calculate the cell address
            $cellAddress = $columnLetter.($this->rowCounter++);
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

    private function getPayrollData($requestData)
    {
        $startDate = $requestData->startDate;
        $endDate = $requestData->endDate;
        $search = $requestData->search;

        if (! $this->isValidRequest($requestData, $startDate, $endDate)) {
            return collect([]);
        }

        $payrollHistory = PayrollHistory::where('payroll_history.payroll_id', '!=', 0)
            ->with('usersdata.positionDetail', 'positionDetail', 'payroll')
            ->when($search, function ($q) {
                $q->whereHas('usersdata', function ($q) {
                    $q->where('first_name', 'Like', '%'.request()->input('search').'%')->orwhere('last_name', 'Like', '%'.request()->input('search').'%');
                });
            })->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])->orderBy(
                User::select('first_name')
                    ->whereColumn('id', 'payroll_history.user_id')
                    ->orderBy('first_name', 'asc')
                    ->limit(1),
                'ASC'
            )->whereIn('payroll_history.everee_payment_status', [0, 3])->get();

        return $payrollHistory;

    }

    private function isValidRequest($requestData, $startDate, $endDate)
    {
        return $requestData->has('startDate') && $requestData->has('endDate') && $requestData->has('payFrequency') &&
        ! empty($startDate) && ! empty($endDate) && ! empty($requestData->payFrequency);
    }

    private function transformPayrollData($payrollData)
    {
        $finalData = [];
        foreach ($payrollData as $key => $response) {
            $payrollId = $response->id;
            $userId = $response->user_id;
            $commissionTotal = 0;

            $userCommission = UserCommissionLock::with('saledata')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($userCommission) > 0) {
                foreach ($userCommission as $userCommissionValue) {
                    $finalData[] = $this->sheetRowCreate($userCommissionValue, $response, 'Commission');
                }
            }

            // $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')
            //     ->where('type', 'commission')
            //     ->where([
            //         //'payroll_id'=>$response->payroll_id,
            //         'user_id' =>  $userId,
            //         'clawback_type' => 'next payroll',
            //         'pay_period_from' => $response->pay_period_from,
            //         'pay_period_to' => $response->pay_period_to
            //         ])
            //     ->get();

            // if (count($clawbackSettlement) > 0) {
            //     foreach ($clawbackSettlement as $keys => $val) {
            //         //$adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $val->pid, 'payroll_type' =>'commission', 'type'=> 'clawback'])->first();
            //         $amount = isset($val->clawback_amount) ? (0 - $val->clawback_amount) : 0;
            //         $data[] = [
            //             "user_name_first" => ucfirst($response->usersData->first_name),
            //             "user_name_last" => ucfirst($response->usersData->last_name),
            //             "user_id" => $response->usersData->employee_id,
            //             "category" => ucfirst('Commission'),
            //             "type" => ucfirst('Clawback'),
            //             "pid_reqid" => $val->pid,
            //             "customer_name" => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
            //             "comments" => '',
            //             "date_paid" => $val->is_mark_paid == "1" ? Carbon::parse($val->updated_at)->format("m/d/Y"): "",
            //             "amount" => "$ " . exportNumberFormat($amount),
            //             "net_pay" => '',
            //         ];

            //     }
            // }

            $userOverrides = UserOverridesLock::with('salesDetail')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($userOverrides) > 0) {
                foreach ($userOverrides as $userOverrideValue) {
                    $finalData[] = $this->sheetRowCreate($userOverrideValue, $response, 'Override');
                }
            }

            // $clawbackForOverride = ClawbackSettlementLock::with('salesDetail')->where([
            //     'type' => 'overrides',
            //     //'payroll_id' => $response->payroll_id,
            //     'user_id' => $userId,
            //     'clawback_type' => 'next payroll',
            //     'pay_period_from' => $response->pay_period_from,
            //     'pay_period_to' => $response->pay_period_to
            // ])->get();
            // if (count($clawbackForOverride) > 0) {
            //     foreach ($clawbackForOverride as $clawbackSettlement) {
            //         $amount = isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount) : 0;
            //         $data[] = [
            //             "user_name_first" => ucfirst($response->usersData->first_name),
            //             "user_name_last" => ucfirst($response->usersData->last_name),
            //             "user_id" => $response->usersData->employee_id,
            //             "category" => ucfirst('Override'),
            //             "type" => ucfirst('Clawback'),
            //             "pid_reqid" => $clawbackSettlement->pid,
            //             "customer_name" => isset($clawbackSettlement->salesDetail->customer_name) ? $clawbackSettlement->salesDetail->customer_name : null,
            //             "comments" => '',
            //             "date_paid" => $clawbackSettlement->is_mark_paid == "1" ? Carbon::parse($clawbackSettlement->updated_at)->format("m/d/Y"): "",
            //             "amount" => "$ " . exportNumberFormat($amount),
            //             "net_pay" => '',
            //         ];

            //     }
            // }

            $payrollAdjustment = PayrollAdjustmentDetailLock::where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($payrollAdjustment) > 0) {
                foreach ($payrollAdjustment as $userPayrollAdjustmentDetailsValue) {
                    $finalData[] = $this->sheetRowCreate($userPayrollAdjustmentDetailsValue, $response, 'Adjustment');
                }
            }

            $approvalsAndRequest = ApprovalsAndRequestLock::with('adjustment', 'comments')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->where('status', 'Accept')->get();
            if (count($approvalsAndRequest) > 0) {
                foreach ($approvalsAndRequest as $userAdjustmentValue) {
                    $finalData[] = $this->sheetRowCreate($userAdjustmentValue, $response, 'Adjustment');
                }
            }

            $customField = CustomFieldHistory::with('getColumn')->where(['user_id' => $userId, 'payroll_id' => $payrollId])->get();
            if (count($customField) > 0) {
                foreach ($customField as $customFieldsValue) {
                    $finalData[] = $this->sheetRowCreate($customFieldsValue, $response, 'Custom Fields');
                }
            }

            // if (!$payrollDataValue->userDeduction->isEmpty()) {
            //     foreach ($payrollDataValue->userDeduction as $payrollDataValue->userDeductionValue) {
            //         $paydata = PayrollDeductionLock::with('costcenter')
            //             ->leftjoin("payroll_adjustment_details",function($join){
            //                 $join->on("payroll_adjustment_details.payroll_id","=","payroll_deductions.payroll_id")
            //                     ->on("payroll_adjustment_details.cost_center_id","=","payroll_deductions.cost_center_id");
            //             })
            //             ->where('payroll_deductions.user_id', $payrollDataValue->userDeductionValue->user_id)
            //             ->where('payroll_deductions.cost_center_id', $payrollDataValue->userDeductionValue->cost_center_id)
            //             ->where('payroll_deductions.payroll_id',$payrollDataValue->id)
            //             ->select('payroll_deductions.*','payroll_adjustment_details.amount as adjustment_amount')
            //             ->first();

            //         $finalData[] = $this->sheetRowCreate($paydata, $payrollDataValue, "Deduction");
            //     }
            // }

            $finalData[] = $this->sheetRowCreate('', $response, self::NET_PAY);

        }

        return $finalData;
    }

    private function sheetRowCreate($request, $payrollDataResponse, $categoryType)
    {
        switch ($categoryType) {
            case 'Commission':
                $type = $request->amount_type;
                $pid = $request->pid;
                $customerName = ucfirst($request?->saledata?->customer_name);
                $state = strtoupper($request?->saledata?->customer_state);
                $redline = $request->redline;
                $kw = $request?->saledata?->kw;
                $netEpc = $request?->saledata?->net_epc;
                $address = $request?->saledata?->adders;
                $date = date(self::DATE_FORMATE, strtotime($request->date));
                $amount = null;
                $overideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = ($payrollDataResponse?->payrollAdjustmentDetails?->payroll_type == 'commission' && $payrollDataResponse?->payrollAdjustmentDetails?->type == $request?->amount_type)
                ? $payrollDataResponse?->payrollAdjustmentDetails?->comment : '';
                $totalAmount = exportNumberFormat($request?->amount);
                $datePaid = $request->is_mark_paid == '1' ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : '';
                $netPay = null;
                $paidExternally = $request->is_mark_paid == '1' ? 'Paid Externally' : '';
                break;

            case 'Override':
                $commentData = \App\Models\PayrollAdjustmentDetail::where('payroll_id', $payrollDataResponse->id)
                    ->where('payroll_type', 'overrides')
                    ->first();
                $type = $request->type;
                $pid = $request->pid;
                $customerName = ucfirst($request?->salesDetail?->customer_name);
                $state = null;
                $redline = null;
                $kw = $request?->salesDetail?->kw;
                $netEpc = null;
                $address = $request?->salesDetail?->adders;
                $date = $request?->salesDetail ? date(self::DATE_FORMATE, strtotime($request?->salesDetail?->m2_date)) : '';
                $amount = null;
                $overideOver = ucfirst($request?->userInfo?->first_name).' '.ucfirst($request?->userInfo?->last_name);
                $overrideValue = $request->type !== 'Stack' ? '$ '.$request?->overrides_amount.' '.$request?->overrides_type : $request?->overrides_amount.' %';
                $requestId = null;
                $approvedBy = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = $commentData?->pid == $request->pid ? $commentData?->comment : '';
                $datePaid = $request->is_mark_paid == '1' ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : '';
                $totalAmount = exportNumberFormat($request?->amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == '1' ? 'Paid Externally' : '';
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
                $overideOver = null;
                $overrideValue = null;
                $requestId = $request->req_no;
                $approvedBy = $request?->commented_by ? ucfirst($request?->commented_by?->first_name).' '.ucfirst($request?->commented_by?->last_name) : $request?->approvedBy?->first_name.' '.$request?->approvedBy?->last_name;
                $costCenter = null;
                $deductionAmount = null;
                $comments = $request->description ? $request->description : $request->comment;
                $datePaid = $request->is_mark_paid == '1' ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : '';
                $totalAmount = exportNumberFormat($request?->adjustment?->name == 'Fine/fee' ? '-'.$request?->amount : $request?->amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == '1' ? 'Paid Externally' : '';
                break;

            case 'Reimbursement':
                // $commentData = ApprovalAndRequestComment::where(['request_id'=> $request->id, 'type'=> 'comment'])->orderBy("id", "DESC")->first();

                $type = $request->type;
                $pid = $request->req_no;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = date(self::DATE_FORMATE, strtotime($request->cost_date));
                $amount = null;
                $overideOver = null;
                $overrideValue = null;
                $requestId = $request?->req_no;
                $approvedBy = ucfirst($request?->approvedBy?->first_name).' '.ucfirst($request?->approvedBy?->last_name);
                $costCenter = ucfirst($request?->costcenter?->name);
                $deductionAmount = null;
                $comments = $request->description;
                $datePaid = $request->is_mark_paid == '1' ? Carbon::parse($request->updated_at)->format(self::DATE_FORMATE) : '';
                $totalAmount = exportNumberFormat($request?->amount);
                $netPay = null;
                $paidExternally = $request->is_mark_paid == '1' ? 'Paid Externally' : '';
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
                $overideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $limit = exportNumberFormat($request?->limit);
                $outStanding = exportNumberFormat($request?->outstanding);
                $approvedBy = null;
                $costCenter = ucfirst($request?->costcenter?->name);
                $deductionAmount = exportNumberFormat($request?->amount);
                $comments = null;
                $datePaid = null;
                $totalAmount = exportNumberFormat($request?->total);
                $netPay = null;
                $paidExternally = null;
                break;

            case 'Custom Fields':
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
                $overideOver = null;
                $overrideValue = null;
                $requestId = $request?->req_no;
                $approvedBy = null;
                $costCenter = ucfirst($request?->costcenter?->name);
                $deductionAmount = $request?->ammount_par_paycheck;
                $comments = $request?->comment;
                $totalAmount = $request?->value;
                $datePaid = $request->is_mark_paid == '1' ? Carbon::parse($request->updated_at)->format('m/d/Y') : '';
                $netPay = null;
                $paidExternally = $request->is_mark_paid == '1' ? 'Paid Externally' : '';
                break;
            default:
                $type = '';
                $pid = null;
                $customerName = null;
                $state = null;
                $redline = null;
                $kw = null;
                $netEpc = null;
                $address = null;
                $date = null;
                $amount = null;
                $overideOver = null;
                $overrideValue = null;
                $requestId = null;
                $approvedBy = null;
                $costCenter = null;
                $deductionAmount = null;
                $comments = null;
                $totalAmount = null;
                $datePaid = null;
                $netPay = exportNumberFormat($payrollDataResponse->net_pay);
                $paidExternally = null;
                break;
        }

        return [
            'user_name_first' => '',
            'user_name_first' => ucfirst($payrollDataResponse->usersData->first_name),
            'user_name_last' => ucfirst($payrollDataResponse->usersData->last_name),
            'position' => ucfirst($payrollDataResponse?->positionDetail?->position_name),
            'user_id' => $payrollDataResponse->usersData->employee_id,
            'category' => ucfirst($categoryType),
            'type' => ucfirst($type),
            'pid/reqId' => $pid,
            'customer_name' => ucfirst($customerName),
            'state' => strtoupper($state),
            'rep_redline' => $redline,
            'kw' => $kw,
            'net_epc' => $netEpc,
            'date' => $date,
            'address' => $address,
            'adjustment' => $amount ? '$ '.$amount : null,
            'override_over' => ucfirst($overideOver),
            'override_value' => $overrideValue,
            'request_id' => $requestId,
            'adjustment_by' => $approvedBy,
            'cost_head' => $costCenter,
            'deduction_amount' => $deductionAmount,
            'limit' => @$limit,
            'total' => $deductionAmount,
            'outstanding' => @$outstanding,
            'comments' => $comments,
            // "date_paid" => $payrollDtaResponse->is_mark_paid == "1" ? Carbon::parse($payrollDataResponse->updated_at)->format("m/d/Y"): "",
            'date_paid' => $datePaid,
            'amount' => $totalAmount ? '$ '.$totalAmount : null,
            'net' => $netPay ? '$ '.$netPay : null,
            'paid_externally' => $paidExternally ? $paidExternally : null,
        ];
    }

    private function totalNetPay()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if ($value['category'] === self::NET_PAY && ! empty($value['net'])) {
                $payValue = explode('$ ', $value['net']);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }

        return array_sum($totalPay);
    }

    private function totalAmount()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if (! empty($value['amount'])) {
                $payValue = explode('$ ', $value['amount']);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }

        return array_sum($totalPay);
    }
}
