<?php

namespace App\Exports;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\ApprovalsAndRequest;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class requestApprovalsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    protected $request;
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $user = Auth::user();
        $apiType = $this->request->input('api_type');
        $paymentRequest = ApprovalsAndRequest::with('adjustment', 'user')->with(['approvedBy' => function ($query) {
            $query->select('id', 'first_name', 'last_name', 'is_super_admin');
        }])->when($this->request->office_id && $this->request->office_id !== 'all', function ($query) {
            $query->whereHas('user', function ($subQuery) {
                $subQuery->where('office_id', $this->request->office_id);
            });
        });
        if ($user->is_super_admin == 1) {
            if ($apiType == 'approval') {
                $paymentRequest->where(['status' => 'Pending']);
            } else {
                $paymentRequest->where('status', '!=', 'Pending')->whereNotNull('req_no');
            }
        } else if ($user->is_manager == 1) {
            if ($apiType == 'approval') {
                $paymentRequest->where(['manager_id' => $user->id, 'status' => 'Pending'])->where('adjustment_type_id', '!=', 5);
            } else {
                $paymentRequest->where(['manager_id' => $user->id])->where('adjustment_type_id', '!=', 5)->where('status', '!=', 'Pending')->whereNotNull('req_no');
            }
        } else {
            $paymentRequest->where(['user_id' => $user->id, 'status' => 'Approved']);
        }

        if ($this->request->filled('filter')) {
            $search = $this->request->input('filter');
            $paymentRequest->where(function ($query) use ($search) {
                $query->where('amount', 'LIKE', '%' . $search . '%')
                    ->orWhere('req_no', 'like', '%' . $search . '%');
            })->orWhereHas('user', function ($query) use ($search) {
                $query->where('first_name', 'like', '%' . $search . '%')
                    ->orWhere('last_name', 'like', '%' . $search . '%')
                    ->orWhereRaw('CONCAT(first_name, " ",last_name) LIKE ?', ['%' . $search . '%']);
            });
        }
        if ($this->request->filled('type')) {
            $type = $this->request->input('type');
            $paymentRequest->where(function ($query) use ($type) {
                $query->orWhereHas('adjustment', function ($query) use ($type) {
                    $query->where('name', 'like', '%' . $type . '%');
                });
            });
        }
        if ($this->request->filled('status')) {
            $status = $this->request->input('status');
            $paymentRequest->where(function ($query) use ($status) {
                $query->where('status', 'LIKE', '%' . $status . '%')
                    ->orWhere('req_no', 'like', '%' . $status . '%');
            });
        }
        $paymentRequest = $paymentRequest->get();

        $paymentRequest->transform(function ($data) {
            if ($data->adjustment_type_id == 5) {
                $data->amount = "$" . (0 - $data->amount);
            } else if ($data->adjustment_type_id == 7) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;

                $data->amount = $daysCount . ' Days';
            } else if ($data->adjustment_type_id == 8) {
                $start = Carbon::parse($data->start_date);
                $end = Carbon::parse($data->end_date);
                $daysCount = $start->diffInDays($end) + 1;
                $ptoHoursPerDay = ($data->pto_hours_perday * $daysCount);

                $data->amount = $ptoHoursPerDay . ' Hrs';
            } else if ($data->adjustment_type_id == 9) {
                $timeIn = new Carbon($data->clock_in);
                $timeOut = new Carbon($data->clock_out);
                $totalHoursWorkedSec = $timeIn->diffInSeconds($timeOut);
                $totalLunch = isset($data->lunch_adjustment) ? $data->lunch_adjustment : 0;
                $totalBreak = isset($data->break_adjustment) ? $data->break_adjustment : 0;
                $totalLunchBreakTime = ($totalLunch + $totalBreak) * 60;
                $totalWorkHrs = $totalHoursWorkedSec - $totalLunchBreakTime;
                $totalTime = gmdate('H:i', $totalWorkHrs);

                $data->amount = (isset($totalTime) ? $totalTime : 0)  . ' Hrs';
            } else {
                $data->amount = "$" . $data?->amount;
            }

            return [
                'req_no' => $data->req_no,
                'employee_name' => $data?->user?->name,
                'type' => $data?->adjustment?->name,
                'dispute_type' => $data?->dispute_type,
                'pay_period' => $data?->created_at->format("m/d/Y"),
                'amount' => $data?->amount,
                'pid' => $data?->payroll_id,
                'cost_head' => $data?->costcenter?->name,
                'status' => $data->status,
                'reason' => $data?->description,
            ];
        });
        return $paymentRequest;
    }

    public function headings(): array
    {
        return [
            'Request ID',
            'Employee Name',
            'Type',
            'Dispute Type',
            'Disputed Period',
            'Amount',
            'PID',
            'Cost Head',
            'Status',
            'Reason',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999'
                ],
            ],
        ]);
        $sheet->getStyle('J:J')->getAlignment()->setWrapText(true);
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->freezePane('A2');
            },
        ];
    }
}