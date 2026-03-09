<?php

namespace App\Exports;

use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconciliationFinalizeHistory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportReconPayrollListEmployee implements FromCollection, WithHeadings
{
    private $startDate;

    private $endDate;

    private $search;

    public function __construct($startDate = 0, $endDate = 0, $search = 0)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;

    }

    public function collection(): Collection
    {

        $startDate = $this->startDate;
        $endDate = $this->endDate;
        $search = $this->search;
        $data = ReconciliationFinalizeHistory::where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'payroll')->groupBy('user_id');
        if ($search) {
            $data->whereHas(
                'user', function ($query) use ($search) {
                    $query->where('first_name', 'LIKE', '%'.$search.'%')
                        ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                        ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
                });
        }

        $data = $data->with('user')->get();
        $data->transform(function ($data) use ($startDate, $endDate) {

            $officeId = explode(',', $data->office_id);
            if ($data->position_id == 'all') {
                $position = 'All Position';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($positionid as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }

            if ($data->office_id == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeId as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $userCalculation = ReconciliationFinalizeHistory::where('start_date', $startDate)->where('end_date', $endDate)->where('status', 'payroll')->where('user_id', $data->user_id);

            $commission = $userCalculation->sum('paid_commission');
            $overrideDue = $userCalculation->sum('paid_override');
            $clawbackDue = $userCalculation->sum('clawback');
            $totalAdjustments = $userCalculation->sum('adjustments');
            $totalDue = $userCalculation->sum('gross_amount');
            $netPay = $userCalculation->sum('net_amount');

            return $myArray[] = [
                'id' => $data->id,
                'user_id' => $data->user_id,
                'emp_name' => isset($data->user->first_name) ? $data->user->first_name.' '.$data->user->last_name : null,
                'commissionWithholding' => isset($commission) ? $commission : 0,
                'overrideDue' => isset($overrideDue) ? $overrideDue : 0,
                'total_due' => $commission + $overrideDue,
                'pay' => $data->payout,
                'total_pay' => ($commission + $overrideDue) * $data->payout / 100,
                'clawbackDue' => isset($clawbackDue) ? $clawbackDue : 0,
                'totalAdjustments' => isset($totalAdjustments) ? $totalAdjustments : 0,
                'payout' => $netPay,
                // 'net_pay' =>$netPay,
                'status' => $data->status,
            ];

        });

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Id No.',
            'User Id',
            'Employee Name',
            'Commissions Withheld',
            'Override Due',
            'Total Due',
            'Percentage',
            'Total Pay',
            'Clawbacks',
            'Adjustments',
            'Payout',
            'Status',

        ];
    }
}
