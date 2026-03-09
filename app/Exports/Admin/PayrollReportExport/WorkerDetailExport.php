<?php

namespace App\Exports\Admin\PayrollReportExport;

use App\Models\ApprovalAndRequestComment;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CustomFieldHistory;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollHistory;
use App\Models\SalesMaster;
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

class WorkerDetailExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
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
                'Pay Period:',
                Carbon::parse($this->request->startDate)->format('m/d/Y').' - '.Carbon::parse($this->request->endDate)->format('m/d/Y'),
            ],
            [
                'User Name First',
                'User Name Last',
                'User ID',
                'Category',
                'Type',
                'PID / Req ID',
                'Customer Name  ',
                'Comments',
                'Date Paid',
                'Amount  ',
                'Net  ',
                'Paid Externally',
            ],
        ];
    }

    /* Set style sheet */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:K2')->applyFromArray([
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
            '$ '.(exportNumberFormat($this->totalAmount())),
            '$ '.(exportNumberFormat($this->totalNetPay())),

        ];
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($secondHeader, $collectionData) {
                $event->sheet->freezePane('A3');

                $worksheet = $event->sheet->getDelegate();
                // Merge cells B1:C1
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
                /* Data columnn styling */
                $rowCounter = 3;
                foreach ($collectionData as $val) {
                    if (! empty($val['amount'])) {
                        $payValue1 = explode('$ ', $val['amount']);
                        // $totalPay1 = intval($payValue1["1"]);
                        $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));
                        // Calculate the cell address
                        $cellAddress1 = 'J'.$rowCounter;
                        $event->sheet->setCellValue($cellAddress1, '$ '.exportNumberFormat($totalPay1));
                        // Apply styles based on the condition
                        $styleArray1 = [
                            'font' => [
                                'bold' => false,
                                'size' => 12,
                            ],

                        ];
                        if ($totalPay1 < 0) {
                            $event->sheet->setCellValue($cellAddress1, '$ ('.exportNumberFormat(abs($totalPay1)).')');
                            // Set text color to red for negative values
                            $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }

                        $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);
                    }

                    if (! empty($val['amount'])) {
                        $payValue1 = explode('$ ', $val['amount']);
                        // $totalPay1 = intval($payValue1["1"]);
                        $totalPay1 = floatval(str_replace(',', '', $payValue1[1]));
                        // Calculate the cell address
                        $cellAddress1 = 'J'.$rowCounter;
                        $event->sheet->setCellValue($cellAddress1, '$ '.exportNumberFormat($totalPay1));
                        // Apply styles based on the condition
                        $styleArray1 = [
                            'font' => [
                                'bold' => false,
                                'size' => 12,
                            ],

                        ];
                        if ($totalPay1 < 0 || $val['category'] == 'Deduction') {
                            $event->sheet->setCellValue($cellAddress1, '$ ('.exportNumberFormat(abs($totalPay1)).')');
                            // Set text color to red for negative values
                            $styleArray1['font']['color'] = ['rgb' => 'FF0000']; // Red color
                        }

                        $event->sheet->getStyle($cellAddress1)->applyFromArray($styleArray1);

                    }
                    $rowCounter++;

                }

                if ($this->totalNetPay() < 0) {
                    $event->sheet->setCellValue('J'.$lastRowIndex + 2, '$ ('.exportNumberFormat(abs($this->totalAmount())).')');
                    $event->sheet->setCellValue('K'.$lastRowIndex + 2, '$ ('.exportNumberFormat(abs($this->totalNetPay())).')');
                    $event->sheet->getStyle('K'.$lastRowIndex + 2)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => [
                                'rgb' => 'FF0000',
                            ],
                        ],
                    ]);
                    $event->sheet->getStyle('J'.$lastRowIndex + 2)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => [
                                'rgb' => 'FF0000',
                            ],
                        ],
                    ]);
                }

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

                /* Header color */
                $event->sheet->getStyle('A2:L2')->applyFromArray([
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

    private function getPayrollData($requestData)
    {
        $startDate = $requestData->startDate;
        $endDate = $requestData->endDate;
        $search = $requestData->search;

        if (! $this->isValidRequest($requestData, $startDate, $endDate)) {
            return collect([]);
        }

        $payrollHistory = PayrollHistory::where('payroll_history.payroll_id', '!=', 0)
            ->selectRaw('payroll_history.*, payroll_history.created_at as get_date')
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
        $data = [];
        foreach ($payrollData as $key => $response) {
            $payrollId = $response->id;
            $userId = $response->user_id;
            $commissionTotal = 0;
            $userCommission = UserCommissionLock::with('saledata')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($userCommission) > 0) {
                foreach ($userCommission as $key1 => $commission) {
                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => ucfirst('Commission'),
                        'type' => ucfirst($commission->amount_type),
                        'pid_reqid' => $commission->pid,
                        'customer_name' => isset($commission->saledata->customer_name) ? $commission->saledata->customer_name : null,
                        'comments' => '',
                        'date_paid' => $commission->is_mark_paid == '1' ? Carbon::parse($commission->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($commission->amount),
                        'net_pay' => '',
                        'Paid_externally' => $commission->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];

                    $commissionTotal = ($commissionTotal + $commission->amount);
                }
            }

            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')
                ->where('type', 'commission')
                ->where([
                    // 'payroll_id'=>$response->payroll_id,
                    'user_id' => $userId,
                    'clawback_type' => 'next payroll',
                    'pay_period_from' => $response->pay_period_from,
                    'pay_period_to' => $response->pay_period_to,
                ])
                ->get();

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $keys => $val) {
                    // $adjustmentAmount = PayrollAdjustmentDetail::where(['payroll_id'=> $id, 'user_id'=> $Payroll->user_id, 'pid'=> $val->pid, 'payroll_type' =>'commission', 'type'=> 'clawback'])->first();
                    $amount = isset($val->clawback_amount) ? (0 - $val->clawback_amount) : 0;
                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => ucfirst('Commission'),
                        'type' => ucfirst('Clawback'),
                        'pid_reqid' => $val->pid,
                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'comments' => '',
                        'date_paid' => $val->is_mark_paid == '1' ? Carbon::parse($val->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($amount),
                        'net_pay' => '',
                        'Paid_externally' => $val->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];

                }
            }

            $userOverrides = UserOverridesLock::with('salesDetail')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($userOverrides) > 0) {
                foreach ($userOverrides as $key2 => $overrides) {
                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => ucfirst('Override'),
                        'type' => ucfirst($overrides->type),
                        'pid_reqid' => $overrides->pid,
                        'customer_name' => isset($overrides->salesDetail->customer_name) ? $overrides->salesDetail->customer_name : null,
                        'comments' => '',
                        'date_paid' => $overrides->is_mark_paid == '1' ? Carbon::parse($overrides->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($overrides->amount),
                        'net_pay' => '',
                        'Paid_externally' => $overrides->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            $clawbackForOverride = ClawbackSettlementLock::with('salesDetail')->where([
                'type' => 'overrides',
                // 'payroll_id' => $response->payroll_id,
                'user_id' => $userId,
                'clawback_type' => 'next payroll',
                'pay_period_from' => $response->pay_period_from,
                'pay_period_to' => $response->pay_period_to,
            ])->get();
            if (count($clawbackForOverride) > 0) {
                foreach ($clawbackForOverride as $clawbackSettlement) {
                    $amount = isset($clawbackSettlement->clawback_amount) ? (0 - $clawbackSettlement->clawback_amount) : 0;
                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => ucfirst('Override'),
                        'type' => ucfirst('Clawback'),
                        'pid_reqid' => $clawbackSettlement->pid,
                        'customer_name' => isset($clawbackSettlement->salesDetail->customer_name) ? $clawbackSettlement->salesDetail->customer_name : null,
                        'comments' => '',
                        'date_paid' => $clawbackSettlement->is_mark_paid == '1' ? Carbon::parse($clawbackSettlement->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($amount),
                        'net_pay' => '',
                        'Paid_externally' => $clawbackSettlement->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];

                }
            }

            $payrollAdjustment = PayrollAdjustmentDetailLock::where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($payrollAdjustment) > 0) {
                foreach ($payrollAdjustment as $key3 => $adjustment) {
                    $salesMaster = [];
                    if (! empty($adjustment->pid)) {
                        $salesMaster = SalesMaster::select('customer_name')->where(['pid' => $adjustment->pid])->first();
                    }
                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => ucfirst('Adjustment'),
                        'type' => ucfirst($adjustment->payroll_type),
                        'pid_reqid' => $adjustment->pid,
                        'customer_name' => $salesMaster->customer_name ?? '',
                        'comments' => $adjustment->comment,
                        'date_paid' => $adjustment->is_mark_paid == '1' ? Carbon::parse($adjustment->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($adjustment->amount),
                        'net_pay' => '',
                        'Paid_externally' => $adjustment->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            $approvalsAndRequest = ApprovalsAndRequestLock::with('adjustment', 'comments')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->where('status', 'Accept')->get();
            if (count($approvalsAndRequest) > 0) {
                foreach ($approvalsAndRequest as $key4 => $val) {
                    // $comment = ApprovalAndRequestComment::where(['request_id'=> $val->id, 'type'=> 'comment'])->orderBy("id", "DESC")->first();

                    if ($val->adjustment_type_id == 5) {
                        $category = 'Adjustment';
                        $type = isset($val->adjustment->name) ? $val->adjustment->name : '';
                        $amount = ($val->amount < 0) ? $val->amount : (0 - $val->amount);

                    } else {
                        $category = isset($val->adjustment->name) ? $val->adjustment->name : '';
                        $type = '';
                        $amount = $val->amount;
                    }

                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => ucfirst($category),
                        'type' => ucfirst($type),
                        'pid_reqid' => $val->req_no,
                        'customer_name' => '',
                        'comments' => $val?->description,
                        'date_paid' => $val->is_mark_paid == '1' ? Carbon::parse($val->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($amount),
                        'net_pay' => '',
                        'Paid_externally' => $val->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            $customField = CustomFieldHistory::with('getColumn')->where(['user_id' => $userId, 'payroll_id' => $payrollId])->get();
            if (count($customField) > 0) {
                foreach ($customField as $key5 => $val) {

                    $data[] = [
                        'user_name_first' => ucfirst($response->usersData->first_name),
                        'user_name_last' => ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'category' => 'Custom Field',
                        'type' => isset($val->getColumn->field_name) ? $val->getColumn->field_name : '',
                        'pid_reqid' => '',
                        'customer_name' => '',
                        'comments' => $val?->comment,
                        'date_paid' => $val->is_mark_paid == '1' ? Carbon::parse($val->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.$val?->value,
                        'net_pay' => '',
                        'Paid_externally' => $val->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            if ($response->net_pay != 0) {
                $data[] = [
                    'user_name_first' => ucfirst($response->usersData->first_name),
                    'user_name_last' => ucfirst($response->usersData->last_name),
                    'user_id' => $response->usersData->employee_id,
                    'category' => ucfirst('Net Pay'),
                    'type' => '',
                    'pid_reqid' => '',
                    'customer_name' => '',
                    'comments' => '',
                    'date_paid' => $response->is_mark_paid == '1' ? Carbon::parse($response->updated_at)->format('m/d/Y') : '',
                    'amount' => '',
                    'net_pay' => '$ '.exportNumberFormat($response->net_pay),
                    'Paid_externally' => $response->is_mark_paid == '1' ? 'Paid Externally' : '',
                ];
            }

            if ($response->deduction) {
                $amount = isset($response->deduction) ? (0 - $response->deduction) : 0;
                $data[] = [
                    'user_name_first' => ucfirst($response->usersData->first_name ?? ''),
                    'user_name_last' => ucfirst($response->usersData->last_name ?? ''),
                    'user_id' => $response->usersData->employee_id ?? '',
                    'category' => ucfirst('Deduction'),
                    'type' => '',
                    'pid_reqid' => '',
                    'customer_name' => '',
                    'comments' => '',
                    'date_paid' => '',
                    'amount' => '$ '.$amount,
                    'net_pay' => '',
                    'Paid_externally' => null,
                ];
            }

        }

        return $data;
    }

    private function totalNetPay()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            if ($value['category'] == 'Net Pay') {
                $payValue = explode('$ ', $value['net_pay']);
                // $totalPay[] = floatval($payValue["1"]);
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
                // $totalPay[] = floatval($payValue["1"]);
                $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
            }
        }

        return array_sum($totalPay);
    }
}
