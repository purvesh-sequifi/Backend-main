<?php

namespace App\Exports;

use App\Models\ClawbackSettlement;
use App\Models\SalesMaster;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClawbackDataExport implements FromCollection, WithHeadings
{
    private $startDates;

    private $endDates;

    private $officeId;

    private $search;

    public function __construct($officeId, $search, $startDate = 0, $endDate = 0)
    {
        $this->startDates = $startDate;
        $this->endDates = $endDate;
        $this->officeId = $officeId;
        $this->search = $search;

    }

    public function collection(): Collection
    {
        if ($this->startDates != 0 && $this->endDates != 0) {
            if ($this->officeId != 'all') {
                $officeId = $this->officeId;
                $userId = User::where('office_id', $officeId)->pluck('id');
                // $userPid = DB::table('sale_master_process')->whereIn('closer1_id',$userId)->orWhereIn('closer2_id',$userId)->orWhereIn('setter1_id',$userId)->orWhereIn('setter2_id',$userId)->pluck('pid');
                $pid = ClawbackSettlement::whereIn('user_id', $userId)->groupBy('pid')->pluck('pid')->toArray();
                $records = SalesMaster::with('salesMasterProcess')->where('customer_name', 'LIKE', '%'.$this->search.'%')->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$this->startDates, $this->endDates])->whereIn('pid', $pid)->get();
            } else {
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->toArray();

                $records = SalesMaster::with('salesMasterProcess')->where('customer_name', 'LIKE', '%'.$this->search.'%')->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$this->startDates, $this->endDates])->whereIn('pid', $pid)->get();
                // dd($records); die();
            }
        } else {

            if ($this->officeId != 'all') {
                $officeId = $this->officeId;
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->whereIn('user_id', $userId)->toArray();
            } else {
                $pid = ClawbackSettlement::groupBy('pid')->pluck('pid')->toArray();
            }

            $records = SalesMaster::with('salesMasterProcess')->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->get();
        }

        $result = [];
        foreach ($records as $record) {

            $timestamp = strtotime($record['salesMasterProcess']->updated_at);
            $new_date_format = date('Y-m-d', $timestamp);
            $clawAmount = $record->clawbackAmount;
            $claw = [];
            foreach ($clawAmount as $clawAmounts) {
                $claw[] = $clawAmounts->clawback_amount;
            }

            $result[] = [
                'customer_name' => isset($record->customer_name) ? $record->customer_name : null,
                'customer_state' => isset($record->customer_state) ? $record->customer_state : null,
                // 'setter_id' => isset($record['salesMasterProcess']->setter1Detail->id) ? $record['salesMasterProcess']->setter1Detail->id : null,
                'setter' => isset($record['salesMasterProcess']->setter1Detail->first_name) ? $record['salesMasterProcess']->setter1Detail->first_name : null,
                // 'closer_id' => isset($record['salesMasterProcess']->closer1Detail->id) ? $record['salesMasterProcess']->closer1Detail->id : null,
                'closer' => isset($record['salesMasterProcess']->closer1Detail->first_name) ? $record['salesMasterProcess']->closer1Detail->first_name : null,
                'clawback_date' => $record->date_cancelled,
                'last_payment' => isset($new_date_format) ? $new_date_format : null,
                'amount' => array_sum($claw),
                'info' => [
                    'pid' => $record->pid,
                    'installer' => isset($record->installer) ? $record->installer : '',
                    'prospect_id' => isset($record->prospect_id) ? $record->prospect_id : '',
                    'customer_address' => isset($record->customer_address) ? $record->customer_address : '',
                    'homeowner_id' => isset($record->homeowner_id) ? $record->homeowner_id : '',
                    'customer_city' => isset($record->customer_city) ? $record->customer_city : '',
                    'state_code' => isset($record->state) ? $record->state : '',
                    'customer_zip' => isset($record->customer_zip) ? $record->customer_zip : '',
                    'customer_email' => isset($record->customer_email) ? $record->customer_email : '',
                    'customer_phone' => isset($record->customer_phone) ? $record->customer_phone : '',
                    'proposal_id' => isset($record->proposal_id) ? $record->proposal_id : '',
                    'sale_state_redline' => isset($record->sale_state_redline) ? $record->sale_state_redline : '',
                    'epc' => isset($record->epc) ? $record->epc : '',
                    'net_epc' => isset($record->net_epc) ? $record->net_epc : '',
                    'kw' => isset($record->kw) ? $record->kw : '',
                    'redline' => isset($record->redline) ? $record->redline : '',
                    'redline_amount_type' => isset($record->redline_amount_type) ? $record->redline_amount_type : '',
                    'date_cancelled' => isset($record->date_cancelled) ? $record->date_cancelled : '',
                    'm1_date' => isset($record->m1_date) ? $record->m1_date : '',
                    'm2_date' => isset($record->m2_date) ? $record->m2_date : '',
                    'approved_date' => isset($record->approved_date) ? $record->approved_date : '',
                    'product' => isset($record->product) ? $record->product : '',
                    'gross_account_value' => isset($record->gross_account_value) ? $record->gross_account_value : '',
                    'dealer_fee_percentage' => isset($record->dealer_fee_percentage) ? $record->dealer_fee_percentage : '',
                    'dealer_fee_amount' => isset($record->dealer_fee_amount) ? $record->dealer_fee_amount : '',
                    'show' => isset($record->show) ? $record->show : '',
                    'adders_description' => isset($record->adders_description) ? $record->adders_description : '',
                    'total_amount_for_acct' => isset($record->total_amount_for_acct) ? $record->total_amount_for_acct : '',
                    'cancel_fee' => isset($record->cancel_fee) ? $record->cancel_fee : '',
                    'cancel_deduction' => isset($record->cancel_deduction) ? $record->cancel_deduction : '',
                    'account_status' => isset($record->account_status) ? $record->account_status : '',
                ],
            ];
        }

        return collect($result);
    }

    public function headings(): array
    {
        return [
            'Customer Name',
            'Customer state',
            'Setter',
            'Closer',
            'Clawback Date',
            'Last Payment',
            'Amount',
            'Plus all additional info when you click on the customer info page',
        ];
    }
}
