<?php

namespace App\Exports\ExportReports\PendingInstalls;

use App\Models\ClawbackSettlement;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PendingInstalls implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
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

    public function collection()
    {
        if (isset($this->data['startDates']) && isset($this->data['endDates'])) {
            if ($this->data['officeId'] == 'all') {
                $records = SalesMaster::with('salesMasterProcess')
                    ->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')
                    ->where('install_complete_date', null)
                    ->whereBetween('customer_signoff', [$this->data['startDates'], $this->data['endDates']]);
            } else {
                $office_id = $this->data['officeId'];
                $userId = User::where('office_id', $office_id)->pluck('id');
                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)
                    ->orWhereIn('closer2_id', $userId)
                    ->orWhereIn('setter1_id', $userId)
                    ->orWhereIn('setter2_id', $userId)
                    ->pluck('pid');
                $records = SalesMaster::with('salesMasterProcess')
                    ->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')
                    ->where('install_complete_date', null)
                    ->whereBetween('customer_signoff', [$this->data['startDates'], $this->data['endDates']])
                    ->whereIn('pid', $salesPid);
            }
        } else {
            if ($this->data['officeId'] != 'all') {
                $officeId = $this->data['officeId'];
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = ClawbackSettlement::groupBy('pid')
                    ->pluck('pid')
                    ->whereIn('user_id', $userId)
                    ->toArray();
            } else {
                $userId = User::pluck('id');
            }
            $records = SalesMaster::with('salesMasterProcess')
                ->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')->where('install_complete_date', null);
        }
        $result = $records->get();
        $result->transform(function ($response) {
            $now = time(); // or your date as well
            $your_date = strtotime($response->customer_signoff);
            $datediff = $now - $your_date;
            $day = round($datediff / (60 * 60 * 24));

            return [
                'pid' => $response?->pid,
                'customer_name' => $response?->customer_name,
                'closer1_first_name' => $response->salesMasterProcess?->closer1Detail?->first_name,
                'closer1_last_name' => $response->salesMasterProcess?->closer1Detail?->last_name,
                'installer' => $response?->install_partner,
                'kw' => $response?->kw,
                'epc' => $response?->epc,
                'net_epc' => $response?->net_epc,
                'dealer_fees_percenntage' => $response?->dealer_fee_percentage * 100,
                'dealer_fee_amount' => $response->dealer_fee_amount ? '$'.$response->dealer_fee_amount : '$0',
                'Gross Account Value' => '$'.$response->gross_account_value ?? '$0',
                'Total $' => $response->total_amount_in_period ?? '$0',
                'M1 Date' => $response?->m1_date ?? '',
                'Status' => '',
                'Age (Days)' => intval($day),
            ];
        });

        return $result;
    }

    public function headings(): array
    {
        return [
            'PId',
            'Customer Name',
            'Closer-1 First Name',
            'Closer-1 Last Name',
            'Installer',
            'KW',
            'EPC',
            'Net Epc',
            'Dealer Fees %',
            'Dealer Fees $',
            'Gross Account Value',
            'Total $',
            'M1 Date',
            'Status',
            'Age (Days)',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:O1')->applyFromArray([
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
        return 'Clawback Report';
    }
}
