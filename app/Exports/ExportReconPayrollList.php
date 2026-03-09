<?php

namespace App\Exports;

use App\Models\Locations;
use App\Models\Positions;
use App\Models\ReconciliationFinalizeHistory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportReconPayrollList implements FromCollection, WithHeadings
{
    private $executedOn;

    public function __construct($executedOn = 0)
    {
        $this->executedOn = $executedOn;

    }

    public function collection(): Collection
    {
        $date = $this->executedOn;
        if ($date != '') {
            $data = ReconciliationFinalizeHistory::whereYear('executed_on', $date)->orderBy('id', 'asc')->groupBy('start_date')->where('status', 'payroll')->get();
        } else {
            $data = ReconciliationFinalizeHistory::orderBy('id', 'asc')->groupBy('start_date')->where('status', 'payroll')->get();
        }

        $totalCommision = 0;
        $totalOverride = 0;
        $totalClawback = 0;
        $totalAdjustments = 0;
        $grossAmount = 0;
        $payout = 0;
        $data->transform(function ($data) use ($date) {
            $total = [];
            $positionId = ReconciliationFinalizeHistory::whereYear('executed_on', $date)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')->pluck('position_id');
            $officeId = ReconciliationFinalizeHistory::whereYear('executed_on', $date)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll')->pluck('office_id');
            $uniqueArray = collect($positionId)->unique()->values()->all();
            if ($uniqueArray[0] == 'all') {
                $position = 'All office';
            } else {
                $positionid = explode(',', $data->position_id);
                foreach ($uniqueArray as $positions) {
                    $positionvalu = Positions::where('id', $positions)->first();
                    $val[] = $positionvalu->position_name;
                }
                $position = implode(',', $val);
            }
            $officeIdArray = collect($officeId)->unique()->values()->all();
            if ($officeIdArray[0] == 'all') {
                $office = 'All office';
            } else {
                $officeId = explode(',', $data->office_id);
                foreach ($officeIdArray as $offices) {
                    $positionvalu = Locations::where('id', $offices)->first();
                    $vals[] = $positionvalu->office_name;
                }
                $office = implode(',', $vals);
            }
            $val = ReconciliationFinalizeHistory::whereYear('executed_on', $date)->orderBy('id', 'asc')->where('start_date', $data->start_date)->where('end_date', $data->end_date)->where('status', 'payroll');
            $sumComm = $val->sum('commission');
            $sumOver = $val->sum('override');
            $sumClaw = $val->sum('clawback');
            $sumAdju = $val->sum('adjustments');
            $sumGross = $val->sum('gross_amount');
            $sumPayout = $val->sum('net_amount');

            return [
                'start_date' => $data->start_date,
                'end_date' => $data->end_date,
                'executed_on' => $data->executed_on,
                'office' => $office,
                'position' => $position,
                'commission' => $sumComm,
                'overrides' => $sumOver,
                'clawback' => $sumClaw,
                'adjustments' => $sumAdju,
                'gross_amount' => $sumGross,
                'payout' => $data->payout,
                // 'net_amount' => $sumPayout,
                // 'status' => $data->status
            ];

        });

        $dataCalculate = ReconciliationFinalizeHistory::whereYear('executed_on', $date)->orderBy('id', 'asc')->groupBy('start_date')->where('status', 'payroll')->get();

        foreach ($dataCalculate as $dataCalculates) {
            $vals = ReconciliationFinalizeHistory::whereYear('executed_on', $date)->orderBy('id', 'asc')->where('start_date', $dataCalculates->start_date)->where('end_date', $dataCalculates->end_date)->where('status', 'payroll');
            $sumComm = $vals->sum('commission');
            $sumOver = $vals->sum('override');
            $sumClaw = $vals->sum('clawback');
            $sumAdju = $vals->sum('adjustments');
            $sumGross = $vals->sum('gross_amount');
            $sumPayout = $vals->sum('net_amount');

            $totalCommision += $sumComm;
            $totalOverride += $sumOver;
            $totalClawback += $sumClaw;
            $totalAdjustments += $sumAdju;
            $grossAmount += $sumGross;
            $payout += $sumPayout;
        }

        $total = [
            'totalCommision' => $totalCommision,
            'override' => $totalOverride,
            'clawback' => $totalClawback,
            'adjustments' => $totalAdjustments,
            'gross_amount' => $grossAmount,
            'payout' => $payout,
            'year' => isset($date) ? $date : date('Y'),
            'nextRecon' => $grossAmount - $payout,
        ];

        // $data['Heading_total'] =  $total;
        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Start Date',
            'End Date',
            'Exicute On',
            'Location',
            'Position',
            'Commissions',
            'Overrides',
            'Clawbacks',
            'Adjustments',
            'Total',
            'Payout',

        ];
    }
}
