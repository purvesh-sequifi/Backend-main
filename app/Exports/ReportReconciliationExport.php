<?php

namespace App\Exports;

use App\Models\UserReconciliationWithholding;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportReconciliationExport implements FromCollection, WithHeadings
{
    private $startDates;

    private $endDates;

    public function __construct($office_id, $startDate, $endDate)
    {
        $this->location = $office_id;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        $records = [];
        if ($this->location != '' && $this->startDate != '' && $this->endDate != '') {
            if ($this->location == 'all') {
                $records = \DB::table('users as u')
                    ->select('u.id', 'u.first_name', 'u.last_name', 'u.image', 'u.position_id', 'u.state_id')
                    ->JOIN('states as s', 's.id', '=', 'u.state_id')
                    ->whereIn('u.position_id', [2, 3])
                    ->orderBy('u.id', 'asc')->get();
            } else {
                $records = \DB::table('users as u')
                    ->select('u.id', 'u.first_name', 'u.last_name', 'u.image', 'u.position_id', 'u.state_id')
                    ->JOIN('states as s', 's.id', '=', 'u.state_id')
                    ->where('s.id', '=', $this->location)
                    ->whereIn('u.position_id', [2, 3])
                    ->orderBy('u.id', 'asc')->get();
            }
        }
        // dd($records);

        $result = [];
        foreach ($records as $user) {

            if ($user->position_id == 2) {
                $closer_earn = UserReconciliationWithholding::where('closer_id', $user->id)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->sum('withhold_amount');
                $closer_paid = UserReconciliationWithholding::where('closer_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->sum('withhold_amount');

                $closer_unpaid = UserReconciliationWithholding::where('closer_id', $user->id)
                    ->where('status', 'unpaid')
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->sum('withhold_amount');

                if ($closer_earn) {
                    $result[] = [
                        'emp_name' => $user->first_name.' '.$user->last_name,
                        'total_earn' => $closer_earn,
                        'total_paid' => $closer_paid,
                        'commission_due' => $closer_unpaid,
                        'override_due' => '0',
                        'deduction_due' => '0',
                        'total_due' => '0',
                    ];
                }

            }

            if ($user->position_id == 3) {
                $setter_earn = UserReconciliationWithholding::where('setter_id', $user->id)
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->sum('withhold_amount');

                $setter_paid = UserReconciliationWithholding::where('setter_id', $user->id)
                    ->where('status', 'paid')
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->sum('withhold_amount');

                $setter_unpaid = UserReconciliationWithholding::where('setter_id', $user->id)
                    ->where('status', 'unpaid')
                    ->whereBetween('created_at', [$this->startDate, $this->endDate])
                    ->sum('withhold_amount');

                if ($setter_earn) {

                    $result[] = [
                        'emp_name' => $user->first_name.' '.$user->last_name,
                        'total_earn' => $setter_earn,
                        'total_paid' => $setter_paid,
                        'commission_due' => $setter_unpaid,
                        'override_due' => '0',
                        'deduction_due' => '0',
                        'total_due' => '0',
                    ];
                }

            }

        }

        // dd($result);
        return collect($result);
    }

    public function headings(): array
    {
        return [
            'Employee',
            'Total Earned',
            'Total Paid',
            'Commission Due',
            'Override Due',
            'Deduction Due',
            'Total Due',
        ];
    }
}
