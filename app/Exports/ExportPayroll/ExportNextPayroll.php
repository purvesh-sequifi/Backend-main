<?php

namespace App\Exports\ExportPayroll;

use App\Models\Payroll;
use App\Models\PayrollHistory;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportNextPayroll implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $data = [];
        // dd($this->request['netpay_filter']); die();
        $positions = isset($this->request['position_filter']) ? $this->request['position_filter'] : '';
        $netPay = isset($this->request['netpay_filter']) ? $this->request['netpay_filter'] : '';
        $commission = isset($this->request['commission_filter']) ? $this->request['commission_filter'] : '';
        $pay_frequency = isset($this->request['pay_frequency']) ? $this->request['pay_frequency'] : '';
        $start_date = isset($this->request['start_date']) ? $this->request['start_date'] : '';
        $end_date = isset($this->request['end_date']) ? $this->request['end_date'] : '';
        $search = isset($this->request['search']) ? $this->request['search'] : '';
        $type = isset($this->request['type']) ? $this->request['type'] : '';

        if ($type == 'payroll_paid') {
            $result = Payroll::with('usersdata');
            $payroll_report = $result
                ->where('is_next_payroll', 1)
                ->orderBy('id', 'desc');
        } else {
            $result = PayrollHistory::with('usersdata', 'positionDetail', 'payroll')
                ->whereHas('payroll', function ($query) {
                    $query->where('is_next_payroll', 1);
                });
            $payroll_report = $result->where('payroll_id', '>', 0)->orderBy('id', 'desc');
        }
        if ($search) {
            $userids = User::where('first_name', 'Like', '%'.$search.'%')->orwhere('last_name', 'Like', '%'.$search.'%')->pluck('id')->toArray();
            $result->where(function ($query) use ($userids) {
                return $query->whereIn('user_id', $userids);
            });
        }

        if ($start_date && $end_date) {
            $result->where(function ($query) use ($start_date, $end_date) {
                return $query->where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date]);
            });
        }
        $payroll_report = $result->get();
        if ($payroll_report) {
            $payroll_report->transform(function ($data) {
                // dump($data);
                if (isset($data->usersdata->entity_type) && $data->usersdata->entity_type == 'business') {
                    $contractor_type = 'business';
                    $first_name = null;
                    $last_name = null;
                    $ssn = null;
                } elseif (isset($data->usersdata->entity_type) && $data->usersdata->entity_type == 'individual') {
                    $contractor_type = 'individual';
                    $first_name = isset($data->usersdata) ? $data->usersdata->first_name : null;
                    $last_name = isset($data->usersdata) ? $data->usersdata->last_name : null;
                    $ssn = isset($data->usersdata->social_sequrity_no) ? $data->usersdata->social_sequrity_no : null;
                } else {
                    $contractor_type = 'blank';
                    $first_name = isset($data->usersdata) ? $data->usersdata->first_name : null;
                    $last_name = isset($data->usersdata) ? $data->usersdata->last_name : null;
                    $ssn = null;
                }

                // $companyProfile = CompanyProfile::first();
                return [
                    'first_name' => ucfirst($first_name ?? $data->usersdata->first_name),
                    'last_name' => ucfirst($last_name ?? $data->usersdata->last_name),
                    'emp_id' => $data->usersdata->employee_id,
                    'pay_frequency' => \App\Models\FrequencyType::find(2)->pluck('name')->first(),
                    // 'pay_frequency' => \App\Models\FrequencyType::find(request()->pay_frequency)->pluck('name')->first(),
                    'commission' => '$'.$data->commission ?? '$0',
                    'override' => '$'.$data->override ?? '$0',
                    'hourlysalary' => '$'.$data->hourlysalary ?? '$0',
                    'overtime' => '$'.$data->overtime ?? '$0',
                    'adjustment' => '$'.$data->adjustment ?? '$0',
                    'reimbursement' => '$'.$data->reimbursement ?? '$0',
                    'deduction' => $data->deduction ? '$'.$data->deduction : '$0',
                    'reconciliation' => $data->reconciliation ? '$'.$data->reconciliation : '$0',
                    'net_pay' => '$'.$data->net_pay ?? '$0',

                    /* 'business_name' => isset($data->usersdata->business_name) ? $data->usersdata->business_name : null,
                    'ein' => isset($data->usersdata->business_ein) ? $data->usersdata->business_ein : null,
                    'memo' => null,
                    'hours_worked' => 0,
                    'wage' => round($data->net_pay, 2),
                    'reimbursement' => null,
                    'bonus' => $data->hiring_bonus_amount,
                    'invoice_number' => isset($data->id) ? $data->id : null, */
                ];

            });

            return $payroll_report;
        }
    }

    public function registerEvents(): array
    {
        $collectionData = $this->collection();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');

                $this->alternateRowColorStyle($event);

                // Determine the index of numerical negative value
                $headers = $this->headings();

                $fieldsToFormat = [
                    'Commission  ',
                    'OverRide  ',
                    'Adjustment    ',
                    'Reimbursement   ',
                    'Deduction  ',
                    'Reconciliation  ',
                    'NET PAY  ',
                ];

                $fieldIndexes = [];
                foreach ($fieldsToFormat as $field) {
                    $index = array_search($field, $headers);
                    if ($index !== false) {
                        $fieldIndexes[$field] = $index + 1; // Adding 1 because Excel columns are 1-based
                    }
                }

                // Iterate through the collection data
                $rowCounter = 2; // Assuming data starts from the 2nd row
                foreach ($collectionData as $value) {
                    foreach ($fieldIndexes as $field => $columnIndex) {
                        if (isset($value[strtolower(str_replace(' ', '_', trim($field)))])) {
                            $fieldValue = $value[strtolower(str_replace(' ', '_', trim($field)))];
                            $this->setCellValueAndStyle($event, $columnIndex, $rowCounter, $fieldValue);
                        }
                    }
                    $rowCounter++;
                }
            },
        ];
    }

    private function setCellValueAndStyle($event, $columnIndex, $rowCounter, $value)
    {
        $cell = $event->sheet->getDelegate()->getCellByColumnAndRow($columnIndex, $rowCounter);
        $numericValue = explode('$ ', $value);
        $cell->setValue('$ '.exportNumberFormat(floatval($numericValue[1])));

        if ($numericValue[1] < 0) {
            $cell->setValue('$ ('.exportNumberFormat(abs(floatval($numericValue[1]))).')');
            $event->sheet->getDelegate()->getStyle($cell->getCoordinate())
                ->applyFromArray([
                    'font' => [
                        'color' => ['rgb' => 'FF0000'],
                    ],
                ]);
        }
    }

    private function alternateRowColorStyle($event)
    {
        // Define the colors for alternating rows
        $evenRowColor = 'f0f0f0'; // Light green
        $oddRowColor = 'FFFFFF'; // White
        $lastRow = $event->sheet->getDelegate()->getHighestDataRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            // Apply background color based on row parity
            $fillColor = $row % 2 == 0 ? $evenRowColor : $oddRowColor;
            $event->sheet->getStyle("$row:$row")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $fillColor],
                ],
            ]);
        }
        for ($i = 1; $i <= $lastRow; $i++) {
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

    public function headings(): array
    {
        return [
            'First Name',
            'Last Name',
            'Emp Id',
            'Pay Frequency',
            'Commissiom',
            'OverRide',
            'HourlySalary',
            'Overtime',
            'ADJUSTMENT',
            'REIMBURSTMENT ',
            'Deduction',
            'RECONCILIATION',
            'NET PAY',
        ];
    }

    public function title(): string
    {
        return 'Next Payroll';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:M1')->applyFromArray([
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
    }
}
