<?php

namespace App\Exports;

use App\Models\Locations;
use App\Models\Positions;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\State;
use App\Models\User;
use App\Models\UserOverrides;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportSalesExport implements FromCollection, WithHeadings
{
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection(): Collection
    {
        return collect($this->data);

        //        if ($this->officeId != '' && $this->startDate != '' && $this->endDate != '') {
        //            if ($this->officeId == 'all' && $this->search == '') {
        //                //dd($this->startDate);
        //                $records = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$this->startDate, $this->endDate])
        //                    ->when($this->closed && $this->closed != 0, function ($query) {
        //                        $query->where('date_cancelled', '!=', null);
        //                    })
        //                    ->when($this->m1 && $this->m1 != 0, function ($query) {
        //                        $query->where('m1_date', '!=', null);
        //                    })
        //                    ->when($this->m2 && $this->m2 != 0, function ($query) {
        //                        $query->where('m2_date', '!=', null);
        //                    })
        //                    ->get();
        //
        //                // if ($this->closed && $this->closed!='')
        //                //     {
        //                //         $records->where(function($query) {
        //                //             return $query->where('date_cancelled','!=', Null);
        //                //             });
        //                //     }
        //
        //                //     if ($this->m1 && $this->m1!='')
        //                //     {
        //                //         $records->where(function($query) {
        //                //             return $query->where('m1_date', '!=', null);
        //                //             });
        //                //     }
        //
        //                //     if ($this->m2 && $this->m2!='')
        //                //     {
        //                //         $records->where(function($query) {
        //                //             return $query->where('m2_date', '!=', null);
        //                //             });
        //                //     }
        //                //   $records->get();
        //
        //            } elseif ($this->officeId == 'all' && $this->search != '') {
        //
        //                $records = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$this->startDate, $this->endDate])
        //                    ->when($this->closed && $this->closed != 0, function ($query) {
        //                        $query->where('date_cancelled', '!=', null);
        //                    })
        //                    ->when($this->m1 && $this->m1 != 0, function ($query) {
        //                        $query->where('m1_date', '!=', null);
        //                    })
        //                    ->when($this->m2 && $this->m2 != 0, function ($query) {
        //                        $query->where('m2_date', '!=', null);
        //                    })
        //                    ->when($this->m2 && $this->m2 != 0, function ($query) {
        //                        $query->where('m2_date', '!=', null);
        //                    })
        //                    ->when($this->search, function ($query) {
        //                        $query->where('customer_name', 'LIKE', '%' . $this->search . '%');
        //                    })
        //                    ->get();
        //
        //            } elseif ($this->officeId != 'all' && $this->search != '') {
        //
        //                $office_id = $this->officeId;
        //                $userId = User::where('office_id', $office_id)->pluck('id');
        //                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        //                $records = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$this->startDate, $this->endDate])->whereIn('pid', $salesPid)
        //                    ->when($this->closed && ($this->closed != '' && $this->closed != 0), function ($query) {
        //                        $query->where('date_cancelled', '!=', null);
        //                    })
        //                    ->when($this->m1 && ($this->m1 != '' && $this->m1 != 0), function ($query) {
        //                        $query->where('m1_date', '!=', null);
        //                    })
        //                    ->when($this->m2 && ($this->m2 != '' && $this->m2 != 0), function ($query) {
        //                        $query->where('m2_date', '!=', null);
        //                    })
        //                    ->when($this->search, function ($query) {
        //                        $query->where('customer_name', 'LIKE', '%' . $this->search . '%');
        //                    })
        //                    ->get();
        //
        //            } else {
        //
        //                $office_id = $this->officeId;
        //                $userId = User::where('office_id', $office_id)->pluck('id');
        //                $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        //                $records = SalesMaster::with('salesMasterProcess')->whereBetween('customer_signoff', [$this->startDate, $this->endDate])->whereIn('pid', $salesPid)
        //                    ->when($this->closed && ($this->closed != '' && $this->closed != 0), function ($query) {
        //                        $query->where('date_cancelled', '!=', null);
        //                    })
        //                    ->when($this->m1 && ($this->m1 != '' && $this->m1 != 0), function ($query) {
        //                        $query->where('m1_date', '!=', null);
        //                    })
        //                    ->when($this->m2 && ($this->m2 != '' && $this->m2 != 0), function ($query) {
        //                        $query->where('m2_date', '!=', null);
        //                    })
        //                    ->get();
        //
        //                // if ($this->closed && $this->closed!='')
        //                //     {
        //                //         $records->where(function($query) {
        //                //             return $query->where('date_cancelled','!=', Null);
        //                //             });
        //                //     }
        //
        //                //     if ($this->m1 && $this->m1!='')
        //                //     {
        //                //         $records->where(function($query) {
        //                //             return $query->where('m1_date', '!=', null);
        //                //             });
        //                //     }
        //
        //                //     if ($this->m2 && $this->m2!='')
        //                //     {
        //                //         $records->where(function($query) {
        //                //             return $query->where('m2_date', '!=', null);
        //                //             });
        //                //     }
        //                //     $records->get();
        //            }
        //        } else {
        //            $userId = User::orderBy('id', 'desc')->pluck('id');
        //            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
        //            $records = SalesMaster::with('salesMasterProcess')->whereIn('pid', $salesPid)
        //                ->when($this->closed && ($this->closed != '' && $this->closed != 0), function ($query) {
        //                    $query->where('date_cancelled', '!=', null);
        //                })
        //                ->when($this->m1 && ($this->m1 != '' && $this->m1 != 0), function ($query) {
        //                    $query->where('m1_date', '!=', null);
        //                })
        //                ->when($this->m2 && ($this->m2 != '' && $this->m2 != 0), function ($query) {
        //                    $query->where('m2_date', '!=', null);
        //                })
        //                ->get();
        //            // if ($this->closed && $this->closed!='')
        //            //     {
        //            //         $records->where(function($query) {
        //            //             return $query->where('date_cancelled','!=', Null);
        //            //             });
        //            //     }
        //
        //            //     if ($this->m1 && $this->m1!='')
        //            //     {
        //            //         $records->where(function($query) {
        //            //             return $query->where('m1_date', '!=', null);
        //            //             });
        //            //     }
        //
        //            //     if ($this->m2 && $this->m2!='')
        //            //     {
        //            //         $records->where(function($query) {
        //            //             return $query->where('m2_date', '!=', null);
        //            //             });
        //            //     }
        //            // $records->get();
        //        }
        //        //dd($records);
        //
        //        $result = [];
        //        foreach ($records as $record) {
        //
        //            $closer1_detail = isset($record->salesMasterProcess->closer1Detail) ? $record->salesMasterProcess->closer1Detail : null;
        //            $setter1_detail = isset($record->salesMasterProcess->setter1_id) ? $record->salesMasterProcess->setter1Detail : null;
        //
        //            $closer1_m1 = isset($record->salesMasterProcess->closer1_m1) ? $record->salesMasterProcess->closer1_m1 : null;
        //            $setter1_m1 = isset($record->salesMasterProcess->setter1_m1) ? $record->salesMasterProcess->setter1_m1 : null;
        //            $closer1_m2 = isset($record->salesMasterProcess->closer1_m2) ? $record->salesMasterProcess->closer1_m2 : null;
        //            $setter1_m2 = isset($record->salesMasterProcess->setter1_m2) ? $record->salesMasterProcess->setter1_m2 : null;
        //
        //            $total_commission = ($record->salesMasterProcess->closer1_commission + $record->salesMasterProcess->closer2_commission + $record->salesMasterProcess->setter1_commission + $record->salesMasterProcess->setter2_commission);
        //            $account_override = UserOverrides::with('user')->where('pid', $record->pid)->get();
        //            if (count($account_override) > 0) {
        //                $account_overrides = $account_override;
        //            } else {
        //                $account_overrides = "";
        //            }
        //
        //            $saleMasterProcess = SaleMasterProcess::where('pid', $record->pid)->first();
        //            $account_override->transform(function ($data) use ($saleMasterProcess) {
        //                if ($data->sale_user_id == $saleMasterProcess->closer1_id || $data->sale_user_id == $saleMasterProcess->closer2_id) {
        //                    $positionName = 'Closer';
        //                } else {
        //                    $positionName = 'Setter';
        //                }
        //                $user = User::where('id', $data->sale_user_id)->first();
        //                $position = Positions::where('id', $user->position_id)->first();
        //                $image = isset($data->user->image) ? $data->user->image : null;
        //                $first_name = isset($data->user->first_name) ? $data->user->first_name : null;
        //                $last_name = isset($data->user->last_name) ? $data->user->last_name : null;
        //                return [
        //                    'through' => $positionName,
        //                    'image' => $image,
        //                    'first_name' => $first_name,
        //                    'last_name' => $last_name,
        //                    'type' => $data->type,
        //                    'amount' => $data->overrides_amount,
        //                    'weight' => $data->overrides_type,
        //                    'total' => $data->amount,
        //                    'calculated_redline' => $data->calculated_redline,
        //                    'assign_cost' => null
        //                ];
        //            });
        //
        //            $location = Locations::where('general_code', '=', $record->customer_state)->first();
        //            if ($location) {
        //                $redline_standard = $location->redline_standard;
        //            } else {
        //                $state = State::where('state_code', '=', $record->customer_state)->first();
        //                //echo $customer_state;die;
        //                if ($state) {
        //                    $location = Locations::where(['state_id' => $state->id, 'type' => 'Redline'])->first();
        //                    $redline_standard = isset($location->redline_standard) ? $location->redline_standard : null;
        //                } else {
        //                    $location = null;
        //                    $redline_standard = null;
        //                }
        //
        //            }
        //
        //            $result[] = array(
        //                'pid' => $record->pid,
        //                'customer_name' => $record->customer_name,
        //                'source' => $record->data_source_type,
        //                'status' => isset($record->salesMasterProcess->status->account_status) ? $record->salesMasterProcess->status->account_status : null,
        //                'state' => $record->customer_state,
        //                'closer' => isset($record->salesMasterProcess->closer1Detail) ? $record->salesMasterProcess->closer1Detail->first_name : null,
        //                'kw' => $record->kw,
        //                'm1' => $setter1_m1,
        //                'm1_date' => $record->m1_date,
        //                'm2' => $closer1_m2,
        //                'm2_date' => $record->m2_date,
        //                'epc' => $record->epc,
        //                'net_epc' => $record->net_epc,
        //                'adders' => $record->adders,
        //                'total_commission' => $total_commission,
        //                'installer' => isset($record->installer) ? $record->installer : "",
        //                'prospect_id' => isset($record->prospect_id) ? $record->prospect_id : "",
        //                'customer_address' => isset($record->customer_address) ? $record->customer_address : "",
        //                'homeowner_id' => isset($record->homeowner_id) ? $record->homeowner_id : "",
        //                'customer_city' => isset($record->customer_city) ? $record->customer_city : "",
        //                'customer_zip' => isset($record->customer_zip) ? $record->customer_zip : "",
        //                'customer_email' => isset($record->customer_email) ? $record->customer_email : "",
        //                'customer_phone' => isset($record->customer_phone) ? $record->customer_phone : "",
        //                'proposal_id' => isset($record->proposal_id) ? $record->proposal_id : "",
        //                'sale_state_redline' => isset($redline_standard) ? $redline_standard : "",
        //                'redline' => isset($record->redline) ? $record->redline : "",
        //                'redline_amount_type' => isset($record->redline_amount_type) ? $record->redline_amount_type : "",
        //                'date_cancelled' => isset($record->date_cancelled) ? $record->date_cancelled : "",
        //                'approved_date' => isset($record->approved_date) ? $record->approved_date : "",
        //                'product' => isset($record->product) ? $record->product : "",
        //                'gross_account_value' => isset($record->gross_account_value) ? $record->gross_account_value : "",
        //                'dealer_fee_percentage' => isset($record->dealer_fee_percentage) ? $record->dealer_fee_percentage : "",
        //                'dealer_fee_amount' => isset($record->dealer_fee_amount) ? $record->dealer_fee_amount : "",
        //                'show' => isset($record->show) ? $record->show : "",
        //                'adders_description' => isset($record->adders_description) ? $record->adders_description : "",
        //                'total_amount_for_acct' => isset($record->total_amount_for_acct) ? $record->total_amount_for_acct : "",
        //                'cancel_fee' => isset($record->cancel_fee) ? $record->cancel_fee : "",
        //                'cancel_deduction' => isset($record->cancel_deduction) ? $record->cancel_deduction : "",
        //                'account_status' => isset($record->account_status) ? $record->account_status : "",
        //                'info' => $account_overrides,
        //                'job_status' => isset($record->job_status) ? $record->job_status : ""
        //            );
        //            //dd($result);
        //        }
        //        return collect($result);
    }

    public function headings(): array
    {
        return [
            'Pid',
            'Customer',
            'Source',
            'Status',
            'State',
            'Rep Name',
            'KW',
            'M1',
            'M1 Date',
            'M2',
            'M2 Date',
            'Epc',
            'Net Epc',
            'Adders',
            'Total Commission',
            'Installer',
            'Prospect ID',
            'Customer Address',
            'Homeowner ID',
            'Customer City',
            'Customer Zip',
            'Customer Email',
            'Customer Phone',
            'Proposal ID',
            'Sale State Redline',
            'Redline',
            'Redline Amount Type',
            'Date Cancelled',
            'Approved Date',
            'Product',
            'Gross Account Value',
            'Dealer Fee Percentage',
            'Dealer Fee Amount',
            'Adders',
            'Adders Description',
            'Total Amount for Acct',
            'Cancel Fee',
            'Cancel Deduction',
            'Account Status',
            'Account Overrides',
            'Job Status',
        ];
    }
}
