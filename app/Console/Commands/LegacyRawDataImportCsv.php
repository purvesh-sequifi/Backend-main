<?php

namespace App\Console\Commands;

use App\Core\Traits\SubroutineListTrait;
use App\Models\LegacyApiRowData;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithStartRow;

class LegacyRawDataImportCSV implements ToModel, WithCustomCsvSettings, WithStartRow
{
    use SubroutineListTrait;

    public function startRow(): int
    {
        return 2;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
        ];
    }

    public function model(array $row): ?Model
    {
        Log::info('import excel execution starts');
        $salemaster_array = [];
        $status_array = [];
        try {
            $data = [
                'legacy_data_id' => $row[1],
                'aveyo_hs_id' => null,
                'pid' => $row[3],
                'prospect_id' => $row[3],
                'weekly_sheet_id' => $row[5],
                'homeowner_id' => $row[6],
                'proposal_id' => $row[7],
                'customer_name' => $row[8],
                'customer_address' => $row[9],
                'customer_address_2' => $row[10],
                'customer_city' => $row[11],
                'customer_state' => $row[12],
                'customer_zip' => $row[13],
                'customer_email' => $row[14],
                'customer_phone' => $row[15],
                'setter_id' => $row[16],
                'employee_id' => $row[17],
                'sales_rep_name' => $row[18],
                'rep_name' => $row[18],
                'sales_rep_email' => $row[19],
                'rep_email' => $row[19],
                'install_partner' => $row[20],
                'install_partner_id' => $row[21],
                'customer_signoff' => $row[22],
                'm1' => $row[23],
                'scheduled_install' => $row[24],
                'install_complete' => $row[25],
                'm2' => $row[26],
                'date_cancelled' => $row[27],
                'return_sales_date' => $row[28],
                'gross_account_value' => $row[29],
                'cash_amount' => $row[30],
                'loan_amount' => $row[31],
                'kw' => $row[32],
                'dealer_fee_percentage' => $row[33],
                'adders' => $row[34],
                'cancel_fee' => $row[35],
                'adders_description' => $row[36],
                'funding_source' => $row[37],
                'financing_rate' => $row[38],
                'financing_term' => $row[39],
                'product' => $row[40],
                'epc' => $row[42], // $row[41],
                'net_epc' => $row[42],
                'source_created_at' => $row[43],
                'source_updated_at' => $row[4],
                'created_at' => $row[45],
                'updated_at' => $row[46],
            ];
            $checked = json_decode(json_encode($data));
            $lid['pageid'] = 1;
            $status_array[] = $subroutineTwo = $this->subroutineTwo($checked, $lid);

            // $legacy_check = LegacyApiRowData::where('pid',$row[3])->first();
            // if(empty($legacy_check)){
            //     $insert = LegacyApiRowData::create($data);
            // }else{
            //     $update = LegacyApiRowData::where('pid',$row[3])->update($data);
            // }
            $checked = \DB::table('legacy_api_raw_data as lad')->select('lad.pid', 'lad.weekly_sheet_id', 'lad.install_partner', 'lad.homeowner_id', 'lad.proposal_id', 'lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state', 'lad.customer_zip', 'lad.customer_email', 'lad.customer_phone', 'lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email', 'lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term', 'lad.product', 'lad.epc', 'lad.net_epc', 'lad.cancel_fee')
                ->where('lad.install_complete_date', null)
                ->where('lad.pid', $row[3])
                ->first();

            $excelData = \DB::table('legacy_excel_raw_data as ld')->select('ld.id', 'ld.sales_setter_email', 'ld.epc', 'ld.net_epc', 'ld.m1_date', 'ld.m2_date', 'ld.cancel_date', 'ld.redline', 'ld.m1_this_week', 'ld.install_m2_this_week', 'ld.total_in_period', 'ld.last_date_pd', 'ld.prev_paid', 'ld.total_for_acct', 'ld.approved_date', 'ld.cancel_fee', 'ld.cancel_deduction', 'ld.lead_cost', 'ld.adv_pay_back_amount', 'ld.dealer_fee_dollar', 'ld.prev_deducted')
                ->where('ld.pid', $row[3])
                ->latest()->first();

            if (! empty($checked)) {

                $dateCancelled = $checked->date_cancelled;
                $m1_date = $checked->m1_date;

                if ($checked->m2_date != null) {
                    $m2_date = $checked->m2_date;
                } else {
                    $m2_date = isset($excelData->m2_date) ? $excelData->m2_date : null;
                }

                $m1_this_week = isset($excelData->m1_this_week) ? $excelData->m1_this_week : '0';
                $m2_this_week = isset($excelData->install_m2_this_week) ? $excelData->install_m2_this_week : '0';

                // check return sales date
                if (isset($checked->customer_state) && $checked->customer_state) {

                    $sale_state = $checked->customer_state;
                } else {
                    $sale_state = isset($excelData->state) ? $excelData->state : '';
                }
                $salesMaster = SalesMaster::where('pid', $checked->pid)->first();
                if ($salesMaster) {
                    $m1_date = ($salesMaster->m1_date == null) ? $m1_date : $salesMaster->m1_date;
                    $m2_date = ($salesMaster->m2_date == null) ? $m2_date : $salesMaster->m2_date;
                    $dateCancelled = ($salesMaster->date_cancelled == null) ? $dateCancelled : $salesMaster->date_cancelled;
                }

                $val = [
                    'pid' => $checked->pid,
                    'weekly_sheet_id' => $checked->weekly_sheet_id,
                    'install_partner' => $checked->install_partner,
                    'install_partner_id' => $checked->install_partner_id,
                    'customer_name' => $checked->customer_name,
                    'customer_address' => $checked->customer_address,
                    'customer_address_2' => $checked->customer_address_2,
                    'customer_city' => $checked->customer_city,
                    'customer_state' => $sale_state,
                    'customer_zip' => $checked->customer_zip,
                    'customer_email' => $checked->customer_email,
                    'customer_phone' => $checked->customer_phone,
                    'homeowner_id' => $checked->homeowner_id,
                    'proposal_id' => $checked->proposal_id,
                    'sales_rep_name' => $checked->sales_rep_name,
                    'employee_id' => $checked->employee_id,
                    'sales_rep_email' => $checked->sales_rep_email,
                    'kw' => $checked->kw,
                    'date_cancelled' => $dateCancelled,
                    'customer_signoff' => $checked->customer_signoff,
                    'm1_date' => $m1_date,
                    'm2_date' => $m2_date,
                    'product' => $checked->product,
                    'epc' => isset($checked->epc) ? $checked->epc : null,
                    'net_epc' => isset($checked->net_epc) ? $checked->net_epc : null,
                    'gross_account_value' => $checked->gross_account_value,
                    'dealer_fee_percentage' => $checked->dealer_fee_percentage,
                    'adders' => $checked->adders,
                    'adders_description' => $checked->adders_description,
                    'funding_source' => $checked->funding_source,
                    'financing_rate' => $checked->financing_rate,
                    'financing_term' => $checked->financing_term,
                    'scheduled_install' => $checked->scheduled_install,
                    'install_complete_date' => $checked->install_complete_date,
                    'return_sales_date' => $checked->return_sales_date,
                    'cash_amount' => $checked->cash_amount,
                    'loan_amount' => $checked->loan_amount,
                    'dealer_fee_amount' => isset($excelData->dealer_fee_dollar) ? $excelData->dealer_fee_dollar : null,
                    'redline' => isset($excelData->redline) ? $excelData->redline : null,
                    'total_amount_for_acct' => isset($excelData->total_for_acct) ? $excelData->total_for_acct : null,
                    'prev_amount_paid' => isset($excelData->prev_paid) ? $excelData->prev_paid : null,
                    'last_date_pd' => isset($excelData->last_date_pd) ? $excelData->last_date_pd : null,
                    'm1_amount' => $m1_this_week,
                    'm2_amount' => $m2_this_week,
                    'prev_deducted_amount' => isset($excelData->prev_deducted) ? $excelData->prev_deducted : null,
                    'cancel_fee' => isset($checked->cancel_fee) ? $checked->cancel_fee : null,
                    'cancel_deduction' => isset($excelData->cancel_deduction) ? $excelData->cancel_deduction : null,
                    'lead_cost_amount' => isset($excelData->lead_cost) ? $excelData->lead_cost : null,
                    'adv_pay_back_amount' => isset($excelData->adv_pay_back_amount) ? $excelData->adv_pay_back_amount : null,
                    'total_amount_in_period' => isset($excelData->total_in_period) ? $excelData->total_in_period : null,
                    'data_source_type' => 'import',
                ];

                // if(isset($excelData->sales_setter_email) && $excelData->sales_setter_email !='')
                // {
                //  $userEmail = User::where('email',$excelData->sales_setter_email)->where('position_is',3)->first();
                if (! empty($checked->net_epc)) {
                    $calculate = SalesMaster::where('pid', $checked->pid)->first();
                    // dd($calculate);
                    if (empty($calculate)) {
                        $insertData = '';
                        $user = User::where('email', $checked->sales_rep_email)->first();
                        if (empty($user)) {
                            $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->value('user_id');
                            if (! empty($additional_user_id)) {
                                $user = User::where('id', $additional_user_id)->first();
                            }
                        }
                        $insertData = SalesMaster::create($val);
                        $data = [
                            'sale_master_id' => $insertData->id,
                            'weekly_sheet_id' => $insertData->weekly_sheet_id,
                            'pid' => $checked->pid,
                            'closer1_id' => isset($user->id) ? $user->id : null,
                        ];
                        SaleMasterProcess::create($data);
                        // if(!empty($insertData)){
                        //     $status_array[] = 'SalesMaster_insert';
                        //     $salemaster_array['insert'][] = $checked->pid;
                        // }
                    } else {
                        $updateData = '';
                        $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
                        // if(!empty($updateData)){
                        //     $status_array[] = 'SalesMaster_update';
                        //     $salemaster_array['update'][] = $checked->pid;
                        // }
                    }

                }
            }
        } catch (Exception $e) {
            Log::info($e);
        }

        return $excelData;
    }
}
