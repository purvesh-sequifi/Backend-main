<?php

namespace App\Console\Commands;

use App\Core\Traits\HubspotTrait;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\LegacyWeeklySheet;
use App\Models\Payroll;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Traits\EmailNotificationTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Log;

class HubspotInsertData extends Command
{
    use EmailNotificationTrait;
    use HubspotTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubspot:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert Hubspot data in table from api';

    /**
     * Execute the console command.
     */
    public function handle($url = ''): int
    {
        Log::info('Hubspot:insert executed');
        if (empty($url)) {
            $url = 'https://api.hubapi.com/crm/v3/objects/p_installs?properties=first_name%2Clast_name%2Chubspot_owner_id%2Chs_created_by_user_id%2Caccount_manager%2Cproject%2Cemail%2Cfull_name%2Chubspot_owner_id%2Cadders_total%2Caddress%2Cappointment_date%2Capr%2Ccancelation_date%2Ccity%2Cclawback%2Ccloser%2Ccontract_hia%2Ccontract_loan%2Ccontract_agreement_hia%2Ccontract_sign_date%2Ccontract_signed%2Ccounty%2Cdays_in_stage%2Cdealer_fee_%2Cdealer_fee_amount%2Cdesign_approved%2Cdesign_time%2Cdiscounts%2Cemail%2Cenefro_source%2Cenerflo%2Cenerflo_install_id%2Cengineering_status%2Cest_commissions%2Cestimated_first_year_production%2Cfinance_product%2Cfinancer%2Cfull_address%2Cfull_name%2Cgross_ppw%2Chs_object_id%2Cinspection_complete%2Cinspection_status%2Cm1_com_approved%2Cm2_com_date%2Cnet_ppw_calc%2Cphone%2Cpostal_code%2Cproject_status%2Crep_redline%2Cpto%2Cpto_status%2Csetter%2Csigned_to_install%2Cstate%2Csystem_size%2Csystem_size_kw%2Cteam%2Ctoday_s_date%2Ctotal_cost%2Cutility_bill%2Cutility_company%2Cuuid_sales_rep%2Cproject%2Cadder_%2Cadders_description,dealer_fee,setter_id,closer_id';
        }

        if (1 == 1) {

            // Sale Can't be updated while payroll is being finalized
            $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
            if ($payroll) {
                echo 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.';
                exit;
            }

            $tokens = 'pat-na1-e6d7ca8e-8fbd-460a-a3df-8fa64cb12641';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url, // your preferred url
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                // CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'content-type: application/json',
                    "Authorization:Bearer $tokens",
                ],
            ]
            );
            $response = curl_exec($ch);
            $err = curl_error($ch);
            $res = (object) json_decode($response);
            // $newData['link'] = ($res->paging->next->link);

            $newData = $res->results;
            // total page get from hubspot api

            $year = date('Y');
            $month = date('m');
            $date = date('Y-m-d');
            $dt = Carbon::parse($date);
            // $uid = Auth()->user()->id;
            $dateWeek = $dt->weekOfMonth;
            $sheet = LegacyWeeklySheet::where('week', $dateWeek)->where('month', $month)->where('year', $year)->first();
            if ($sheet == null) {
                $legacyWeekly = LegacyWeeklySheet::create(
                    [
                        'user_id' => null,
                        'year' => $year,
                        'month' => $month,
                        'week' => $dateWeek,
                        'week_date' => $date,
                        'in_process' => '1', // To Prevent Finalization While Sales Are Being Updated
                    ]);
                $sheetId = $legacyWeekly->id;
                $lid['weekid'] = $legacyWeekly->id;
            } else {
                $dateWeek = $sheet->week;
                $lid['weekid'] = $sheet->id;
                // To Prevent Finalization While Sales Are Being Updated
                $sheetId = $sheet->id;
                $sheet->update(['in_process' => '1']);
            }
            // Log::info(json_encode($newData));exit();
            // // $data = json_encode($newData['results']);

            foreach ($newData as $key => $value) {

                $check = LegacyApiRowData::where('legacy_data_id', $value->id)->first();
                $checknull = LegacyApiNullData::where('legacy_data_id', $value->id)->first();
                if (empty($check) && empty($checknull)) {
                    // Run hubspotSubroutine
                    $hubspotSubroutine = $this->hubspotSubroutine($value, $lid);
                }
            }

            if (! empty($res->paging->next->link)) {
                $count = LegacyApiNullData::count();
                if ($count > 3000) {
                    exit();
                }
                $this->handle($res->paging->next->link);
            }

            curl_close($ch);

            // Send email to admin ........................................................................................
            $legacyNullData = LegacyApiNullData::where('email_status', 0)->get();
            if ($legacyNullData) {
                $legacyNullData = LegacyApiNullData::where('email_status', 0)->update(['email_status' => 1]);
            }

            // Get data api table and excel sheet table.....................................................................
            $newData = \DB::table('legacy_api_raw_data as lad')->select('lad.pid', 'lad.weekly_sheet_id', 'lad.install_partner', 'lad.homeowner_id', 'lad.proposal_id', 'lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state', 'lad.customer_zip', 'lad.customer_email', 'lad.customer_phone', 'lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email', 'lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term', 'lad.product', 'lad.epc', 'lad.net_epc', 'lad.cancel_fee', 'lad.closer_id')
                ->where('lad.install_complete_date', null)
                ->get();
            // Update data by previous comparison in Sales_Master
            foreach ($newData as $checked) {

                $excelData = \DB::table('legacy_excel_raw_data as ld')->select('ld.id', 'ld.sales_setter_email', 'ld.epc', 'ld.net_epc', 'ld.m1_date', 'ld.m2_date', 'ld.cancel_date', 'ld.redline', 'ld.m1_this_week', 'ld.install_m2_this_week', 'ld.total_in_period', 'ld.last_date_pd', 'ld.prev_paid', 'ld.total_for_acct', 'ld.approved_date', 'ld.cancel_fee', 'ld.cancel_deduction', 'ld.lead_cost', 'ld.adv_pay_back_amount', 'ld.dealer_fee_dollar', 'ld.prev_deducted')
                    ->where('ld.pid', $checked->pid)
                    ->latest()->first();

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
                    $sale_state = $excelData->state;
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
                    'data_source_type' => 'api',
                ];

                // if(isset($excelData->sales_setter_email) && $excelData->sales_setter_email !='')
                // {
                //  $userEmail = User::where('email',$excelData->sales_setter_email)->where('position_is',3)->first();
                if (! empty($checked->net_epc)) {
                    $calculate = SalesMaster::where('pid', $checked->pid)->first();
                    // dd($calculate);
                    if (empty($calculate)) {
                        $insertData = '';
                        $user = User::where('employee_id', $checked->closer_id)->first();
                        $insertData = SalesMaster::create($val);
                        $data = [
                            'sale_master_id' => $insertData->id,
                            'weekly_sheet_id' => $insertData->weekly_sheet_id,
                            'pid' => $checked->pid,
                            'closer1_id' => $user->id,
                        ];
                        SaleMasterProcess::create($data);
                        if (! empty($insertData)) {
                            $status_array[] = 'SalesMaster_insert';
                            // $salemaster_array['insert'][] = $checked->pid;
                        }
                    } else {
                        $updateData = '';
                        $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
                        if (! empty($updateData)) {
                            $status_array[] = 'SalesMaster_update';
                            // $salemaster_array['update'][] = $checked->pid;
                        }
                    }
                    $status_array[] = 'SalesMaster_loop';
                }
                // }

            }
            // dd('check salemaster');die;

            // To Prevent Finalization While Sales Are Being Updated
            LegacyWeeklySheet::where('id', $sheetId)->update(['in_process' => '0']);
        }
        exit('check');
        // subroutine code start

    }
}
