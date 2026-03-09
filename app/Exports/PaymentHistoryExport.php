<?php

namespace App\Exports;

use App\Models\OneTimePayments;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentHistoryExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    protected $request;

    public $model;

    public function __construct($request)
    {
        $this->request = $request;
        $this->model = new OneTimePayments;
    }

    public function collection()
    {
        $data = $this->request->all();

        $data['filter'] = ! empty($data['filter']) ? $data['filter'] : 'this_year';
        $data['status'] = ! empty($data['status']) ? $data['status'] : 'all_status';

        $oneTimePaymentData = $this->model->newQuery();
        if ($this->request->has('search')) {
            $search = $this->request->input('search');
            $oneTimePaymentData->whereHas('userData', function ($query) use ($search) {
                $query->where('first_name', 'LIKE', '%'.$search.'%')
                    ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%'.$search.'%']);
            });
        }

        if ($this->request->has('filter')) {
            $filterDataDateWise = $data['filter'];

            if ($filterDataDateWise == 'custom') {
                $startDate = date('Y-m-d', strtotime($this->request->input('start_date')));
                $endDate = date('Y-m-d', strtotime($this->request->input('end_date').' +1 day'));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_week') {
                $startDate = date('Y-m-d', strtotime(now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()->endOfWeek()));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'this_month') {

                $startOfMonth = Carbon::now()->subDays(0)->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_month') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(1)->endOfMonth()));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'this_quarter') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d');
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_quarter') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5)->addDays(0)->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);

            }
        } else {
            $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
            $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
            $oneTimePaymentData->whereBetween('created_at', [$startDate, $endDate]);
        }
        if ($this->request->has('status')) {
            $filterDataStatusWise = $data['status'];
            if ($filterDataStatusWise == 'pending') {
                $statusFilter = 0;
                $oneTimePaymentData->where('everee_payment_status', $statusFilter);
            } elseif ($filterDataStatusWise == 'success') {
                $statusFilter = 1;
                $oneTimePaymentData->where('everee_payment_status', $statusFilter);
            } elseif ($filterDataStatusWise == 'failed') {
                $statusFilter = 2;
                $oneTimePaymentData->where('everee_payment_status', $statusFilter);
            } elseif ($filterDataStatusWise == 'all_status') {
                $statusFilter = [0, 1, 2];
                $oneTimePaymentData->whereIn('everee_payment_status', $statusFilter);
            }
        } else {
            $statusFilter = [0, 1, 2];
            $oneTimePaymentData->whereIn('everee_payment_status', $statusFilter);
        }

        $paymentHistory = $oneTimePaymentData
            ->with('userData', 'adjustment')
            ->select('id', 'user_id', 'adjustment_type_id', 'description', 'req_no', 'amount',
                'created_at', 'everee_payment_status', 'payment_status', 'everee_external_id', 'everee_paymentId')
            ->orderBy('id', 'desc')
            ->get();

        /* if ($data['status'] === "pending") {
            $oneTimePaymentData->where('payment_status', 3);
        }elseif ($data['status'] === "success") {
            $oneTimePaymentData->where('payment_status', 1);
        }elseif ($data['status'] === "failed") {
            $oneTimePaymentData->where('payment_status', 2);
        }*/

        // $paymentHistory = $oneTimePaymentData

        $paymentHistory->transform(function ($result) {
            $paymentStatus = 'Pending';
            if ($result?->everee_payment_status == 1) {
                $paymentStatus = 'Success';
            } elseif ($result?->everee_payment_status == 2) {
                $paymentStatus = 'Failed';
            }

            return [
                'Date' => $result?->created_at->format('m/d/Y'),
                'Time' => $result?->created_at->format('h:i A'),
                'Employee' => $result?->userdata->first_name.' '.$result?->userdata->last_name,
                'Description' => $result?->description,
                'TXN Id' => $result?->everee_paymentId,
                'Status' => $paymentStatus,
                'Amount' => '$'.$result?->amount,
            ];
        });

        return $paymentHistory;

    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Freeze the first row (header row)
                $event->sheet->freezePane('A2');
            },
        ];
    }

    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'Employee',
            'Description',
            'TXN Id',
            'Status',
            'Amount',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
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

    public function title(): string
    {
        return 'One Time Payment';
    }
}
