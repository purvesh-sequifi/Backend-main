<?php

namespace App\Exports\Admin\PayrollExport;

use App\Models\User;
use Illuminate\Support\Carbon;
use App\Models\ApprovalsAndRequest;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RepaymentDetailExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithEvents
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        return $this->advanceNegativePaymentData();
    }

    public function headings(): array
    {
        $headings = [
            "Worker Name",
            "Details",
            "Date Paid / Age",
            "Approved by",
            "Amount",
            "Total Due"
        ];

        return [
            $headings
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:f1')->applyFromArray([
            'font' => [
                'bold' => true,
                "size" => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999', // Background color (light gray)
                ],
            ],
        ]);
    }

    public function registerEvents(): array
    {
        $collectionData = $this->collection();
        return [
            AfterSheet::class => function (AfterSheet $event) use ($collectionData) {
                $this->styleRows($event, $collectionData);
                $lastRow = $event->sheet->getDelegate()->getHighestDataRow();
                $lastColumn = $event->sheet->getDelegate()->getHighestDataColumn();
                $evenRowColor = 'f0f0f0'; // Light green
                $oddRowColor = 'FFFFFF'; // White

                for ($row = 2; $row <= $lastRow; $row++) {
                    $fillColor = $row % 2 == 0 ? $evenRowColor : $oddRowColor;
                    $event->sheet->getStyle("A$row:$lastColumn$row")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $fillColor],
                        ],
                    ]);
                }
                for ($i = 1; $i <= $lastRow; $i++) {
                    for ($col = 'A'; $col != 'AC'; ++$col) {
                        $event->sheet->getStyle("$col$i")->applyFromArray([
                            'borders' => $this->borderStyle(),
                        ]);
                    }
                }
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

    private function styleRows(AfterSheet $event, $collectionData): void
    {
        $rowCounter = 2;
        foreach ($collectionData as $val) {
            $this->applyCellStyle($event, $val, 'amount', 'E' . $rowCounter);
            $this->applyCellStyle($event, $val, 'total_amount', 'F' . $rowCounter);
            $rowCounter++;
        }
    }

    private function applyCellStyle(AfterSheet $event, $val, $field, $cellAddress): void
    {
        if (!empty($val[$field])) {
            $payValue = explode("$ ", $val[$field]);
            $totalPay = floatval(str_replace(',', '', $payValue[1]));
            $styleArray = [
                'font' => [
                    'bold' => false,
                    "size" => 12,
                ],
            ];
            if ($totalPay < 0) {
                $event->sheet->setCellValue($cellAddress, "$ (" . exportNumberFormat(abs($totalPay)) . ")");
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }

            if ($field == 'deduction') {
                $event->sheet->setCellValue($cellAddress, "$ (" . exportNumberFormat(abs($totalPay)) . ")");
                $styleArray['font']['color'] = ['rgb' => 'FF0000']; // Red color
            }
            $event->sheet->getStyle($cellAddress)->applyFromArray($styleArray);
        }
    }

    private function advanceNegativePaymentData()
    {
        try {
            $request = $this->request;
            $sortColumnName = 'created_at';
            $sortType = isset($request->sort_val) ? $request->sort_val : 'asc';
            if (isset($request->sort) && $request->sort == 'amount') {
                $sortColumnName = 'total_amount';
            } else if (isset($request->sort) && $request->sort == 'age') {
                $sortColumnName = 'daysDifference';
            }
            $searchText = $request->search;

            $repaymentData = User::whereHas('ApprovalsAndRequests', function ($query) {
                $query->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved');
            })->where(function ($query) use ($searchText) {
                $query->where('first_name', 'LIKE', '%' . $searchText . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $searchText . '%')
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ['%' . $searchText . '%'])
                    ->orWhereHas('ApprovalsAndRequests.ChildApprovalsAndRequests', function ($query) use ($searchText) {
                        $query->where('req_no', 'LIKE', '%' . $searchText . '%');
                    });
            })->with('ApprovalsAndRequests.approvedBy:id,first_name,last_name')
                ->with('ApprovalsAndRequests', function ($query) {
                    $query->where('adjustment_type_id', 4)->whereNull('req_no')->where('status', 'Approved')->select('id', 'parent_id', 'user_id', 'amount', 'txn_id', 'created_at', 'approved_by');
                })->select('first_name', 'last_name', 'image', 'id', 'manager_id', 'position_id', 'sub_position_id', 'is_super_admin', 'worker_type')
                ->when($request->user_id, function ($q) use ($request) {
                    $q->where("id", $request->user_id);
                })->get();

            $data = collect([]);
            foreach ($repaymentData as $value) {
                $date = Carbon::parse($value->ApprovalsAndRequests->min('created_at'));
                $currentDate = Carbon::now();

                $approve = $value->ApprovalsAndRequests[0] ?? null;
                $approved_by = $approve ? $approve->approvedBy->first_name . ' ' . $approve->approvedBy->last_name : null;

                $data[] = [
                    "user_name" => $value->first_name . ' ' . $value->last_name,
                    "total_request" => count($value->ApprovalsAndRequests),
                    "daysDifference" => $date->diffInDays($currentDate) . ' days',
                    "approved_by" => $approved_by,
                    "amount" => '',
                    "total_amount" => "$ " . exportNumberFormat($value->ApprovalsAndRequests->sum('amount') ?? "0")
                ];

                foreach ($value->ApprovalsAndRequests as $req) {
                    $childRequestAmount = ApprovalsAndRequest::where('parent_id', $req->id)->sum('amount');
                    $reqAmount = $req->amount - $childRequestAmount;
                    $req->amount = $reqAmount;
                    $req->req_no = $req->txn_id;
                    if (!$req->txn_id) {
                        $reqData = ApprovalsAndRequest::where('id', $req->parent_id)->whereNull('txn_id')->first();
                        $req->req_no = $reqData->req_no;
                    }

                    $data[] = [
                        "user_name" => '',
                        "total_request" => $req->req_no,
                        "daysDifference" => date('m/d/Y', strtotime($req->created_at)),
                        "approved_by" => $req->approvedBy->first_name . ' ' . $req->approvedBy->last_name,
                        "amount" => "$ " . exportNumberFormat($req->amount ?? "0"),
                        "total_amount" => ''
                    ];
                }
            }

            $data = collect($data);
            if ($sortType == "asc") {
                $sortedData = $data->sortBy($sortColumnName);
            } else {
                $sortedData = $data->sortByDesc($sortColumnName);
            }
            $data = $sortedData->values();
            return $data;
        } catch (\Exception $e) {
            return [];
        }
    }
}