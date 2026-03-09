<?php

namespace App\Imports;

use App\Models\CustomField;
use App\Models\Payroll;
use App\Models\PayrollSsetup;
use Maatwebsite\Excel\Concerns\ToModel;

class PayrollCustomImport implements ToModel
{
    /**
     * @param  array  $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public $row_num = 1;

    public $MyfirstRow = [];

    public function model(array $array)
    {
        $total = '';
        $start_date = '';
        $end_date = '';
        $payroll = '';
        if ($this->row_num == 1) {
            $this->row_num++;
            $result = [];
            for ($i = 2; $i < count($array); $i++) {
                if ($i % 2 == 0 && $array[$i] != 'comment' && $array[$i] != 'Start date' && $array[$i] != 'End date') {
                    if (is_string($array[$i])) {
                        $result[$i] = $array[$i];
                    }
                }
            }
            foreach ($result as $key => $value) {
                if (is_string($value)) {
                    $PayrollSsetup = PayrollSsetup::select(['id'])->where('field_name', 'like', '%'.$value.'%')->first();
                    if (empty($column_id[$key])) {
                        $column_id[$key] = @$PayrollSsetup->id;
                    }
                }
            }
            $this->MyfirstRow = $column_id;

        } else {
            $total = count($array);
            $start_date = isset($array[$total - 2]) && $array[$total - 2] != '' ? gmdate('Y-m-d', ($array[$total - 2] - 25569) * 86400) : null;
            $end_date = isset($array[$total - 1]) && $array[$total - 1] != '' ? gmdate('Y-m-d', ($array[$total - 1] - 25569) * 86400) : null;
            $payroll = Payroll::where(['id' => $array[1], 'pay_period_from' => $start_date, 'pay_period_to' => $end_date])->first();
            unset($array[$total - 2]);
            unset($array[$total - 1]);

        }

        if ($payroll) {
            $t = 1;
            $column = $this->MyfirstRow;
            for ($i = 0; $i < count($array); $i += 2) {
                if ($i >= 2) {
                    if ($array[$i]) {
                        CustomField::updateOrCreate(
                            [
                                'payroll_id' => $payroll->id,
                                'column_id' => $column[$i],
                            ],
                            [
                                'user_id' => $payroll->user_id ?? null,
                                'value' => $array[$i] ?? 0,
                                'comment' => $array[$i + 1] ?? '',
                                'pay_period_from' => $payroll->pay_period_from,
                                'pay_period_to' => $payroll->pay_period_to,
                            ]
                        );

                    }
                }
                $t++;
            }
        }

    }
}
