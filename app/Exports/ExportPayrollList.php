<?php

namespace App\Exports;

use App\Models\CompanyProfile;
use App\Models\Payroll;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportPayrollList implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @return \Illuminate\Support\Collection
     */
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
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

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER,
            'F' => NumberFormat::FORMAT_NUMBER,
            'G' => NumberFormat::FORMAT_NUMBER,
            'H' => NumberFormat::FORMAT_NUMBER,
            'I' => NumberFormat::FORMAT_NUMBER,
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
        ];
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
                ->where('is_mark_paid', '!=', 1)
                ->where('is_next_payroll', '!=', 1)
                ->orderBy('id', 'desc');
        } else {
            $result = PayrollHistory::with('usersdata', 'positionDetail', 'payroll')
                ->whereHas('payroll', function ($query) {
                    $query->where('is_next_payroll', '!=', 1);
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
                if ($data->usersdata->entity_type == 'business') {
                    $contractor_type = 'business';
                    $first_name = null;
                    $last_name = null;
                    $ssn = null;
                } elseif ($data->usersdata->entity_type == 'individual') {
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
                $companyProfile = CompanyProfile::first();

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
        return 'Payroll List';
    }

    public function deduction_for_all_deduction_enable_users($start_date, $end_date, $pay_frequency)
    {
        // get users who's deductions status is ON.
        $deduction_enable_users = User::select('id', 'sub_position_id')->with('positionpayfrequencies', 'userDeduction', 'positionCommissionDeduction')
            ->with(['positionDeductionLimit' => function ($q) {
                $q->where('positions_duduction_limits.status', 1);
            }])
            ->whereHas('positionpayfrequencies', function ($qry) use ($pay_frequency) {
                $qry->where('position_pay_frequencies.frequency_type_id', '=', $pay_frequency);
            })
            ->where('is_super_admin', '!=', '1')
            ->get();

        if ($start_date && $end_date) {
            $paydata = Payroll::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->get();
            $payroll_data = [];
            if (count($paydata) > 0) {
                foreach ($paydata as $p) {
                    $payroll_data[$p->user_id] = $p;
                }
            }

            $payhistorydata = PayrollHistory::where(['pay_period_from' => $start_date, 'pay_period_to' => $end_date])->select('user_id')->get();
            $payroll_history_data = [];
            if (count($payhistorydata) > 0) {
                foreach ($payhistorydata as $p) {
                    $payroll_history_data[$p->user_id] = $p;
                }
            }

            $commission_deduction_amt = 0;
            $commission_deduction_percent_amt = 0;
            $commission_breakup_arr = [];
            $commission_deduction_amt_total = 0;
            $dediction_amount = 0;
            $position_deduction_limit = 0;
            $subtotal = 0;
            $prev_outstanding = 0;
            // $user_deduction = 0;
            foreach ($deduction_enable_users as $key => $data) {
                $user_deduction = [];
                $user_deduction = (count($data->userDeduction) > 0) ? $data->userDeduction : $data->positionCommissionDeduction;
                if (count($user_deduction) <= 0) {
                    continue;
                }
                if (isset($user_deduction[0]->ammount_par_paycheck)) {
                    $d1 = $user_deduction[0]->ammount_par_paycheck;
                }
                if (isset($user_deduction[1]->ammount_par_paycheck)) {
                    $d2 = $user_deduction[1]->ammount_par_paycheck;
                }

                $limit_type = isset($data->positionDeductionLimit->limit_type) ? $data->positionDeductionLimit->limit_type : '';
                $limit_amount = isset($data->positionDeductionLimit->limit_ammount) ? $data->positionDeductionLimit->limit_ammount : '0';

                if (array_key_exists($data->id, $payroll_data)) {
                    $payrolldata = $payroll_data[$data->id];

                    $subtotal = (($payrolldata->commission + $payrolldata->overrides) <= 0) ? 0 : round(($payrolldata->commission + $payrolldata->overrides) * ($limit_amount / 100), 2);

                    // getting previous payroll id by current payroll_id
                    $previous_id = Payroll::where('user_id', $data->id)->where('id', '<', $payrolldata->id)->orderBy('id', 'DESC')->pluck('id')->first();

                    $amount_total = 0;
                    $deduction_total = 0;
                    foreach ($user_deduction as $key => $d) {
                        $prev_outstanding = 0;
                        $prev = PayrollDeductions::where('user_id', $data->id)->where('cost_center_id', $d->cost_center_id)->where('payroll_id', $previous_id)->select('outstanding', 'cost_center_id')->first();
                        $prev_outstanding = (isset($prev->outstanding)) ? round($prev->outstanding, 2) : 0;
                        // Log::info('$prev_outstanding if '.$prev_outstanding);
                        $amount_total += $d->ammount_par_paycheck + (($prev_outstanding > 0) ? $prev_outstanding : 0);
                        $d->ammount_par_paycheck += (($prev_outstanding > 0) ? $prev_outstanding : 0);
                    }
                    $subtotal = ($amount_total < $subtotal) ? $amount_total : $subtotal;
                    $deduction_total = 0;
                    foreach ($user_deduction as $key => $d) {
                        $total = ($amount_total > 0) ? round($subtotal * ($d->ammount_par_paycheck / $amount_total), 2) : 0;
                        $outstanding = $d->ammount_par_paycheck - $total;

                        PayrollDeductions::updateOrCreate([
                            'payroll_id' => $payrolldata->id,
                            'user_id' => $data->id,
                            'cost_center_id' => $d->cost_center_id,
                        ], [
                            'amount' => round($d->ammount_par_paycheck, 2),
                            'limit' => round($limit_amount, 2),
                            'total' => round($total, 2),
                            'outstanding' => round($outstanding, 2),
                            'subtotal' => round($subtotal, 2),
                        ]);

                        $deduction_total += $total;
                    }

                    Payroll::where('id', $payrolldata->id)->update(['deduction' => $deduction_total, 'net_pay' => $payrolldata->net_pay - $deduction_total]);

                } elseif (! array_key_exists($data->id, $payroll_history_data)) {
                    $subtotal = 0; // ((0 + 0)<=0)?0:round((0 + 0)*($limit_amount/100),2);

                    // getting previous payroll id by current payroll_id
                    $previous_id = Payroll::where('user_id', $data->id)->orderBy('id', 'DESC')->pluck('id')->first();

                    $amount_total = 0;

                    $original = [];
                    foreach ($user_deduction as $key => $d) {
                        $original[$key] = $d;
                        $prev_outstanding = 0;
                        $prev = PayrollDeductions::where('user_id', $data->id)->where('cost_center_id', $d->cost_center_id)->where('payroll_id', $previous_id)->select('outstanding', 'user_id', 'payroll_id')->first();
                        $prev_outstanding = (isset($prev->outstanding)) ? round($prev->outstanding, 2) : 0;
                        $amount_total += $d->ammount_par_paycheck + (($prev_outstanding > 0) ? $prev_outstanding : 0);
                        $d->ammount_par_paycheck += (($prev_outstanding > 0) ? $prev_outstanding : 0);
                    }
                    $subtotal = ($amount_total < $subtotal) ? $amount_total : $subtotal;

                    $payroll_id = Payroll::insertGetId([
                        'user_id' => $data->id,
                        'position_id' => $data->sub_position_id,
                        'commission' => 0,
                        'override' => 0,
                        'reimbursement' => 0,
                        'clawback' => 0,
                        'deduction' => 0,
                        'adjustment' => 0,
                        'reconciliation' => 0,
                        'net_pay' => 0,
                        'pay_period_from' => $start_date,
                        'pay_period_to' => $end_date,
                        'status' => 1,
                    ]);
                    $deduction_total = 0;
                    foreach ($user_deduction as $key => $d) {
                        $total = ($amount_total > 0) ? round($subtotal * ($d->ammount_par_paycheck / $amount_total), 2) : 0;
                        $outstanding = $d->ammount_par_paycheck - $total;

                        PayrollDeductions::updateOrCreate([
                            'payroll_id' => $payroll_id,
                            'user_id' => $data->id,
                            'cost_center_id' => $d->cost_center_id,
                        ], [
                            'amount' => round($d->ammount_par_paycheck, 2),
                            'limit' => round($limit_amount, 2),
                            'total' => round($total, 2),
                            'outstanding' => round($outstanding, 2),
                            'subtotal' => round($subtotal, 2),
                        ]);
                        $deduction_total += $total;
                    }
                    Payroll::where('id', $payroll_id)->update([
                        'deduction' => $deduction_total,
                        'net_pay' => -$deduction_total,
                    ]);

                    if (isset($user_deduction[0]->ammount_par_paycheck)) {
                        $user_deduction[0]->ammount_par_paycheck = $d1;
                    }
                    if (isset($user_deduction[1]->ammount_par_paycheck)) {
                        $user_deduction[1]->ammount_par_paycheck = $d2;
                    }
                }
            }
        }
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
