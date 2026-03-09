<?php

namespace App\Exports\ExportReports\Clawback;

use App\Models\ClawbackSettlement;
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

class ClawbackDetailsExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        if (isset($this->data['startDates']) && isset($this->data['endDates'])) {
            if ($this->data['officeId'] != 'all') {
                $officeId = $this->data['officeId'];
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = ClawbackSettlement::whereIn('user_id', $userId)->groupBy('pid')->pluck('pid')->toArray();
                $records = SalesMaster::with('salesMasterProcess', 'clawbackAmount')
                    ->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')
                    ->where('date_cancelled', '!=', null)
                    ->whereBetween('customer_signoff', [$this->data['startDates'], $this->data['endDates']])
                    ->whereIn('pid', $pid);
            } else {
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->toArray();

                $records = SalesMaster::with('salesMasterProcess')
                    ->where('customer_name', 'LIKE', '%'.$this->data['search'].'%')
                    ->where('date_cancelled', '!=', null)
                    ->whereBetween('customer_signoff', [$this->data['startDates'], $this->data['endDates']])
                    ->whereIn('pid', $pid);
            }
        } else {
            if ($this->data['officeId'] != 'all') {
                $officeId = $this->data['officeId'];
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->whereIn('user_id', $userId)->toArray();
            } else {
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->toArray();
            }

            $records = SalesMaster::with('salesMasterProcess', 'clawbackAmount')
                ->where('date_cancelled', '!=', null)
                ->whereIn('pid', $pid);
        }
        $result = $records->get();
        $result->transform(function ($result) {
            if ($result) {
                $pidDetails = SalesMaster::with('salesMasterProcess', 'userDetail')->where('pid', $result->pid)->first();

                // dd($pidDetails);
                return [
                    'PID' => $pidDetails['pid'],
                    'Customer Name' => $pidDetails['customer_name'],
                    'Prospect ID' => $pidDetails['pid'],
                    'Customer Address' => $pidDetails['customer_address'] ?? '-',
                    'Homeowner ID' => $pidDetails['homeowner_id'] ?? '-',
                    'Customer Address2' => $pidDetails['customer_address_2'] ?? '-',
                    'Closer-1' => $pidDetails->salesMasterProcess?->closer1Detail?->first_name.' '.$pidDetails->salesMasterProcess?->closer1Detail?->last_name,
                    'Closer-2' => $pidDetails->salesMasterProcess?->closer2Detail?->first_name.' '.$pidDetails->salesMasterProcess?->closer2Detail?->last_name,
                    'Setter-1' => $pidDetails->salesMasterProcess?->setter1Detail?->first_name.' '.$pidDetails->salesMasterProcess?->setter1Detail?->last_name,
                    'Setter-2' => $pidDetails->salesMasterProcess?->setter2Detail?->first_name.' '.$pidDetails->salesMasterProcess?->setter2Detail?->last_name,
                    'Proposal ID' => $pidDetails['proposal_id'] ?? '-',
                    'Customer City' => $pidDetails['customer_city'] ?? '-',
                    'Product' => $pidDetails['product'] ?? '-',
                    'Customer State' => $pidDetails['customer_state'] ?? '-',
                    'Gross Account value' => $pidDetails['gross_account_value'] ?? '-',
                    'Location Code' => $pidDetails['location_code'] ?? '-',
                    'Installer' => $pidDetails['install_partner'] ?? '-',
                    'Customer Zip' => $pidDetails['customer_zip'] ?? '-',
                    'KW' => $pidDetails['kw'] ?? '-',
                    'Customer Email' => $pidDetails['customer_email'] ?? '-',
                    'EPC' => '$'.$pidDetails['epc'] ?? '$0',
                    'Customer Phone' => $pidDetails[''] ?? '-',
                    'Net EPC' => $pidDetails['net_epc'] ?? '-',
                    'Approved Date' => $pidDetails['customer_signoff'] ?? '-',
                    'Dealer Fee %' => $pidDetails['dealer_fee_percentage'] ?? '-',
                    'M1 Date' => $pidDetails['m1_date'] ?? '-',
                    'M2 Date' => $pidDetails['m2_date'] ?? '-',
                    'Dealer Fee $' => $pidDetails['dealer_fee_amount'] ?? '-',
                    'SOW ' => $pidDetails['sow'] ?? '-',
                    'Cancel Date' => $pidDetails['date_cancelled'] ?? '-',
                ];
            }
        });

        return $result;

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
            'PID',
            'Customer Name',
            'Prospect ID',
            'Customer Address',
            'Homeowner ID',
            'Customer Address2',
            'Closer-1',
            'Closer-2',
            'Setter-1',
            'Setter-2',
            'Proposal ID',
            'Customer City',
            'Product',
            'Customer State',
            'Gross Account value',
            'Location Code',
            'Installer',
            'Customer Zip',
            'KW',
            'Customer Email',
            'EPC',
            'Customer Phone',
            'Net EPC',
            'Approved Date',
            'Dealer Fee %',
            'M1 Date',
            'M2 Date',
            'Dealer Fee $',
            'SOW ',
            'Cancel Date',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:AD1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '999999',
                ],
            ],
        ]);
    }

    public function title(): string
    {
        return 'Clawback Customer Report';
    }
}
