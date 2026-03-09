<?php

namespace App\Exports\Admin\PayrollReportExport;

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
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PidDetailExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
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
        $b = $this->transformPayrollData($payrollData);
        usort($b, function ($key, $value) {
            // return strcmp($key['pid'], $value["pid"]);
            return strcmp($value['pid'], $key['pid']);
        });

        return collect($b);
        // return collect($this->transformPayrollData($payrollData));
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
                'PID',
                'Payment Type',
                'Category',
                'User Name',
                'User ID',
                'Customer Name',
                'Comments',
                'Date Paid',
                'Amount  ',
                'Paid Externally',
            ],
        ];
    }

    /* Set style sheet */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:L2')->applyFromArray([
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
            '$ '.($this->totalAmount()),

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
                        $cellAddress1 = 'I'.$rowCounter;
                        // dump(abs(exportNumberFormat(floatval($totalPay1))));
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
                    $rowCounter++;

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
                /* Set footer values */
                $event->sheet->setCellValue('A'.$lastRowIndex + 2, 'Total');
                $event->sheet->setCellValue('I'.$lastRowIndex + 2, '$ '.exportNumberFormat($this->totalAmount()));
                $styleArray['font'] = ['bold' => true]; // Red color
                if ($this->totalAmount() < 0) {
                    $event->sheet->setCellValue('I'.$lastRowIndex + 2, '$ ('.exportNumberFormat(abs($this->totalAmount())).')');
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle('I'.$lastRowIndex + 2)->applyFromArray($styleArray);
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

        if (! $this->isValidRequest($requestData, $startDate, $endDate)) {
            return collect([]);
        }

        return PayrollHistory::with(['usersData'])
            ->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate])
            ->when($requestData->has('search'), function ($query) use ($requestData) {
                $search = $requestData->search;
                $userIds = User::where(DB::raw("concat(first_name, ' ', last_name)"), 'LIKE', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->pluck('id')
                    ->toArray();

                return $query->whereIn('user_id', $userIds);
            })
            ->get();
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

            $userCommission = UserCommissionLock::with('saledata')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($userCommission) > 0) {
                foreach ($userCommission as $key1 => $commission) {
                    $data[] = [
                        'pid' => $commission?->pid,
                        'payment_type' => 'Commission',
                        'category' => ucfirst($commission?->amount_type),
                        'user_name' => ucfirst($response?->usersData?->first_name).' '.ucfirst($response?->usersData?->last_name),
                        'user_id' => $response?->usersData?->employee_id,
                        'customer_name' => isset($commission?->saledata?->customer_name) ? $commission?->saledata?->customer_name : null,
                        'comments' => '',
                        'date_paid' => ($commission?->is_mark_paid == '1' && $commission?->updated_at) ? Carbon::parse($commission?->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($commission?->amount),
                        'Paid_externally' => $commission?->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];

                }
            }

            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')
                ->where('type', 'commission')
                ->where([
                    // 'payroll_id'=>$response->id,
                    'user_id' => $userId,
                    'clawback_type' => 'next payroll',
                    'pay_period_from' => $response->pay_period_from,
                    'pay_period_to' => $response->pay_period_to,
                ])
                ->get();
            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $keys => $val) {
                    $amount = isset($val?->clawback_amount) ? (0 - $val?->clawback_amount) : 0;
                    $data[] = [
                        'pid' => $val?->pid,
                        'payment_type' => ucfirst('Commission'),
                        'category' => ucfirst('Clawback'),
                        'user_name' => ucfirst($response?->usersData?->first_name).' '.ucfirst($response?->usersData?->last_name),
                        'user_id' => $response?->usersData?->employee_id,
                        'customer_name' => isset($val?->salesDetail?->customer_name) ? $val?->salesDetail?->customer_name : null,
                        'comments' => '',
                        'date_paid' => ($val?->is_mark_paid == '1' && $val?->updated_at) ? Carbon::parse($val?->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($amount),
                        'Paid_externally' => $val?->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];

                }
            }

            $userOverrides = UserOverridesLock::with('salesDetail')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->get();
            if (count($userOverrides) > 0) {
                foreach ($userOverrides as $key2 => $overrides) {
                    $data[] = [
                        'pid' => $overrides?->pid,
                        'payment_type' => ucfirst('Override'),
                        'category' => ucfirst($overrides?->type),
                        'user_name' => ucfirst($response?->usersData?->first_name).' '.ucfirst($response?->usersData?->last_name),
                        'user_id' => $response?->usersData?->employee_id,
                        'customer_name' => isset($overrides?->salesDetail?->customer_name) ? $overrides?->salesDetail?->customer_name : null,
                        'comments' => '',
                        'date_paid' => ($overrides?->is_mark_paid == '1' && $overrides?->updated_at) ? Carbon::parse($overrides?->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($overrides?->amount),
                        'Paid_externally' => $overrides?->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            $clawbackForOverride = ClawbackSettlementLock::with('salesDetail')->where([
                'type' => 'overrides',
                // 'payroll_id' => $response->id,
                'user_id' => $userId,
                'clawback_type' => 'next payroll',
                'pay_period_from' => $response->pay_period_from,
                'pay_period_to' => $response->pay_period_to,
            ])->get();
            if (count($clawbackForOverride) > 0) {
                foreach ($clawbackForOverride as $clawbackSettlement) {
                    $amount = isset($clawbackSettlement?->clawback_amount) ? (0 - $clawbackSettlement?->clawback_amount) : 0;
                    $data[] = [
                        'pid' => $clawbackSettlement?->pid,
                        'payment_type' => ucfirst('Override'),
                        'category' => ucfirst('Clawback'),
                        'user_name' => ucfirst($response?->usersData?->first_name).' '.ucfirst($response?->usersData?->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'customer_name' => isset($clawbackSettlement?->salesDetail?->customer_name) ? $clawbackSettlement?->salesDetail?->customer_name : null,
                        'comments' => '',
                        'date_paid' => $clawbackSettlement?->is_mark_paid == '1' ? Carbon::parse($clawbackSettlement?->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($amount),
                        'Paid_externally' => $clawbackSettlement?->is_mark_paid == '1' ? 'Paid Externally' : '',
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
                        'pid' => $adjustment?->pid,
                        'payment_type' => ucfirst('Adjustment'),
                        'category' => ucfirst($adjustment?->payroll_type),
                        'user_name' => ucfirst($response?->usersData?->first_name).' '.ucfirst($response?->usersData?->last_name),
                        'user_id' => $response?->usersData?->employee_id,
                        'customer_name' => $salesMaster?->customer_name ?? '',
                        'comments' => $adjustment?->comment,
                        'date_paid' => ($adjustment?->is_mark_paid == '1' && $adjustment?->updated_at) ? Carbon::parse($adjustment?->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($adjustment?->amount),
                        'Paid_externally' => $adjustment?->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            $approvalsAndRequest = ApprovalsAndRequestLock::with('adjustment', 'comments')->where(['user_id' => $userId, 'pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to])->where('status', 'Accept')->get();
            if (count($approvalsAndRequest) > 0) {
                foreach ($approvalsAndRequest as $key4 => $val) {

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
                        'pid' => '',
                        'payment_type' => ucfirst($category),
                        'category' => ucfirst($type),
                        'user_name' => ucfirst($response->usersData->first_name).' '.ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'customer_name' => '',
                        'comments' => $val?->description,
                        'date_paid' => $val->is_mark_paid == '1' ? Carbon::parse($val->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.exportNumberFormat($amount),
                        'Paid_externally' => $val->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

            $customField = CustomFieldHistory::with('getColumn')->where(['user_id' => $userId, 'payroll_id' => $payrollId])->get();
            if (count($customField) > 0) {
                foreach ($customField as $key5 => $val) {

                    $data[] = [
                        'pid' => '',
                        'payment_type' => 'Custom Field',
                        'category' => isset($val->getColumn->field_name) ? $val->getColumn->field_name : '',
                        'user_name' => ucfirst($response->usersData->first_name).' '.ucfirst($response->usersData->last_name),
                        'user_id' => $response->usersData->employee_id,
                        'customer_name' => '',
                        'comments' => $val?->comment,
                        'date_paid' => $val->is_mark_paid == '1' ? Carbon::parse($val->updated_at)->format('m/d/Y') : '',
                        'amount' => '$ '.$val?->value,
                        'Paid_externally' => $val->is_mark_paid == '1' ? 'Paid Externally' : '',
                    ];
                }
            }

        }

        return $data;
    }

    // private function totalNetPay()
    // {
    //     $totalPay = [];
    //     foreach ($this->collection() as $value) {
    //         if ($value["category"] == 'Net Pay') {
    //             $payValue = explode("$ ", $value["net_pay"]);
    //             $totalPay[] = floatval($payValue["1"]);
    //         }
    //     }
    //     return array_sum($totalPay);
    // }

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
