<?php

namespace App\Exports\Admin\PayrollReportExport;

use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlementLock;
use App\Models\CustomFieldHistory;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollHistory;
use App\Models\PayrollSsetup;
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

class WorkerBasicExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
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
        $transformPayrollData = $this->transformPayrollData($payrollData);

        return $transformPayrollData;
    }

    /* Set header in the sheet */
    public function headings(): array
    {
        $customFields = PayrollSsetup::orderBy('id', 'Asc')->where(['status' => 1])->pluck('field_name')->toArray();
        $headings = [
            'User Name First',
            'User Name Last ',
            'User ID',
            'Contractor Type',
            'SSN ',
            'Business Name ',
            'EIN ',
            'Memo  ',
            'Commissions  ',
            'Overrides  ',
            'Adjustments  ',
            'Reimbursements  ',
            'Deductions ',
            'Net Pay',
            'Invoice Number',
            'Paid Externally',
        ];

        if (! empty($customFields)) {
            $headings = array_merge($headings, $customFields);
        }

        return [
            [
                'Pay Period',
                Carbon::parse($this->request->startDate)->format('m/d/Y').' - '.Carbon::parse($this->request->endDate)->format('m/d/Y'),
                '',
                'Date Paid',
                '',
            ],
            $headings,
        ];
    }

    /* Set style sheet */
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A2:T2')->applyFromArray([
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
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                $this->setHeadersAndFooter($event);
                $this->styleRows($event, $collectionData);
                $this->formatSSNAndEIN($event);

                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();

                // Define the colors for alternating rows
                $evenRowColor = 'f0f0f0'; // Light green
                $oddRowColor = 'FFFFFF'; // White

                for ($row = 4; $row <= $lastRow; $row += 2) {
                    $event->sheet->getStyle("A$row:U$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $evenRowColor],
                        ],
                    ]);
                }

                for ($row = 3; $row <= $lastRow; $row += 2) {
                    $event->sheet->getStyle("A$row:U$row")->applyFromArray([
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
                /* Set footer values */
                $event->sheet->setCellValue('A'.$lastRow + 1, 'Total');
                $event->sheet->setCellValue('N'.$lastRow + 1, '$ '.$this->totalNetPay());
                $styleArray['font'] = ['bold' => true];
                if ($this->totalNetPay() < 0) {
                    $event->sheet->setCellValue('N'.$lastRow + 1, '$ ('.exportNumberFormat(abs($this->totalNetPay())).')');
                    $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
                }
                $event->sheet->getStyle('N'.$lastRow + 1)->applyFromArray($styleArray);
                $event->sheet->getStyle('A'.$lastRow + 1)->applyFromArray($styleArray);
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

        $event->sheet->mergeCells('G1:P1');
        $event->sheet->setCellValue('G1', 'Note : The "Date Paid" field would only have a data once this payroll is executed and this report is found in payroll reports');
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
            $this->applyCellStyle($event, $val, 'commission', 'I'.$rowCounter);
            $this->applyCellStyle($event, $val, 'overrides', 'J'.$rowCounter);
            $this->applyCellStyle($event, $val, 'adjustment', 'K'.$rowCounter);
            $this->applyCellStyle($event, $val, 'reimbursement', 'L'.$rowCounter);
            $this->applyCellStyle($event, $val, 'deduction', 'M'.$rowCounter);
            $this->applyCellStyle($event, $val, 'net_pay', 'N'.$rowCounter);
            $this->applyCellStyle($event, $val, 'paid_externally', 'U'.$rowCounter);
            $rowCounter++;
        }
    }

    /**
     * Method applyCellStyle: set cell styling and formatting
     *
     * @param  AfterSheet  $event  [explicite description]
     * @param  $val  $val [explicite description]
     * @param  $field  $field [explicite description]
     * @param  $cellAddress  $cellAddress [explicite description]
     */
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

            if ($field == 'deduction') {
                $event->sheet->setCellValue($cellAddress, '$ ('.exportNumberFormat(abs($totalPay)).')');
                // Set text color to red for negative values
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
        }
    }

    /**
     * Method formatSSNAndEIN: formate SSN and EIN values
     *
     * @param  AfterSheet  $event  [explicite description]
     */
    private function formatSSNAndEIN(AfterSheet $event): void
    {
        $collectionData = $this->collection();
        $rowCounter = 3;

        foreach ($collectionData as $value) {
            // Format SSN
            if (! empty($value['ssn'])) {
                $string = str_replace('-', '', $value['ssn']);
                $formattedString = substr($string, 0, 3).'-'.substr($string, 3, 2).'-'.substr($string, 5);
                $event->sheet->setCellValue('E'.$rowCounter, $formattedString);
            }

            // Format EIN
            if (! empty($value['ein'])) {
                $string = str_replace('-', '', $value['ein']);
                $formattedString = substr($string, 0, 2).'-'.substr($string, 2);
                $event->sheet->setCellValue('G'.$rowCounter, $formattedString);
            }

            $rowCounter++;
        }
    }

    /**
     * Method getPayrollData: getting payroll data from db as per body request
     *
     * @param  $requestData  $requestData [explicite description]
     */
    private function getPayrollData($requestData): object
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

    /**
     * Method isValidRequest: check request parameter is valid or not
     *
     * @param  $requestData  $requestData [explicite description]
     * @param  $startDate  $startDate [explicite description]
     * @param  $endDate  $endDate [explicite description]
     */
    private function isValidRequest($requestData, $startDate, $endDate): bool
    {
        return $requestData->has('startDate') && $requestData->has('endDate') && $requestData->has('payFrequency') &&
        ! empty($startDate) && ! empty($endDate) && ! empty($requestData->payFrequency);
    }

    /**
     * Method transformPayrollData: Create export data array response
     *
     * @param  $payrollData  $payrollData [explicite description]
     */
    private function transformPayrollData($payrollData)
    {
        return $payrollData->transform(function ($response) {
            $userIds = $response->user_id;
            $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::where('user_id', $userIds)->where(['pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

            $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::where('user_id', $userIds)->where(['pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to, 'status' => 'Paid'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

            $userCommissionPayrollIDs = UserCommissionLock::where('user_id', $userIds)->where(['pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

            $overridePayrollIDs = UserOverridesLock::where('user_id', $userIds)->where(['pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to, 'status' => '3'])->where('is_mark_paid', '!=', '1')->pluck('payroll_id')->toArray();

            $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::where('user_id', $userIds)->whereIn('payroll_id', $overridePayrollIDs)->orWhereIn('payroll_id', $userCommissionPayrollIDs)->pluck('payroll_id')->toArray();

            $adjustmentIds = array_merge($approvalsAndRequestPayrollIDs, $PayrollAdjustmentDetailPayRollIDS, $ClawbackSettlementPayRollIDS);

            $miscellaneous = PayrollHistory::where('user_id', $userIds)->where('payroll_id', $response->payroll_id)->where(['pay_period_from' => $response->pay_period_from, 'pay_period_to' => $response->pay_period_to, 'status' => '3'])->where('payroll_id', '!=', 0)->whereIn('payroll_id', $adjustmentIds)->sum('adjustment');

            $setting = PayrollSsetup::orderBy('id', 'Asc')->get();
            $custom_field_data = [];
            if (! empty($setting)) {
                foreach ($setting as $value) {
                    $payroll_data = CustomFieldHistory::where(['column_id' => $value['id'], 'payroll_id' => $response->id])->first();
                    $custom_field_data[$value['field_name']] = (isset($payroll_data->value)) ? '$ '.exportNumberFormat($payroll_data->value) : '$ 0.00';
                }
            }

            $returnData = [
                'user_name_first' => $response->usersData->first_name ?? '',
                'user_name_last' => $response->usersData->last_name ?? '',
                'user_id' => $response->usersData->employee_id ?? '',
                'contractor_type' => $response->usersData->entity_type ?? '',
                'ssn' => $response->usersData->social_sequrity_no ?? '',
                'buisnness_name' => $response->usersData->business_name ?? '',
                'ein' => $response->usersData->business_ein ?? '',
                'memo' => '',
                'commission' => '$ '.exportNumberFormat($response->commission),
                'overrides' => '$ '.exportNumberFormat($response->override),
                'adjustment' => '$ '.exportNumberFormat($miscellaneous),
                'reimbursement' => '$ '.exportNumberFormat($response?->reimbursement ?? '0'),
                'deduction' => '$ '.exportNumberFormat($response?->deduction ?? '0'),
                'net_pay' => '$ '.exportNumberFormat($response->net_pay),
                'invoice_number' => '',
                'paid_external' => $response->is_mark_paid ? 'Paid Externally' : '',
            ];

            if (! empty($custom_field_data)) {
                $returnData = $returnData + $custom_field_data;
            }

            return $returnData;
        });
    }

    /**
     * Method totalNetPay: Sum of all net pay amount
     *
     * @return float|int
     */
    private function totalNetPay()
    {
        $totalPay = [];
        foreach ($this->collection() as $value) {
            $payValue = explode('$ ', $value['net_pay']);
            $totalPay[] = floatval(str_replace(',', '', $payValue[1]));
        }

        return array_sum($totalPay);
    }
}
