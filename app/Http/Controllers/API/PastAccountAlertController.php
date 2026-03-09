<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EditSaleTrait;
use App\Http\Controllers\Controller;
use App\Models\LegacyApiNullData;
use App\Models\Locations;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UsersAdditionalEmail;
use Artisan;
use Illuminate\Http\Request;
use Validator;

class PastAccountAlertController extends Controller
{
    use EditSaleTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {}

    public function moveAlertCenter(Request $request)
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                // 'pid' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $startDate = '2023-09-05';
        $endDate = '2023-09-05';
        // $pids = UserCommission::where('pay_period_from','<','2023-09-05')->where('status',1)->distinct()->pluck('pid')->toArray();
        $pids = UserCommission::whereBetween('pay_period_from', [$startDate, $endDate])->where('status', 1)->distinct()->pluck('pid')->toArray();
        // return $pids;
        if (count($pids) > 0) {
            foreach ($pids as $key => $pid) {
                // $pid = 'LIS24205';
                $salesMaster = SalesMaster::with('salesMasterProcess', 'userCommission', 'userDetail')->where('pid', $pid)->first();
                // return $salesMaster;
                if ($salesMaster) {
                    $statusType = 'Payroll';

                    $userCommission = UserCommission::where('pid', $pid)->where('status', 1)->get();
                    $commtype = [];
                    foreach ($userCommission as $key1 => $comm) {
                        $commtype[] = $comm->amount_type;
                    }
                    $commtype = array_unique($commtype);
                    $cloasePayrollType = implode(',', $commtype);
                    // return $cloasePayrollType;
                    $apiData = [
                        'legacy_data_id' => null,
                        'pid' => $salesMaster->pid,
                        'weekly_sheet_id' => null,
                        'homeowner_id' => isset($salesMaster->homeowner_id) ? $salesMaster->homeowner_id : null,
                        'proposal_id' => isset($salesMaster->proposal_id) ? $salesMaster->proposal_id : null,
                        'customer_name' => isset($salesMaster->customer_name) ? $salesMaster->customer_name : null,
                        'customer_address' => isset($salesMaster->customer_address) ? $salesMaster->customer_address : null,
                        'customer_address_2' => isset($salesMaster->customer_address_2) ? $salesMaster->customer_address_2 : null,
                        'customer_city' => isset($salesMaster->customer_city) ? $salesMaster->customer_city : null,
                        'customer_state' => isset($salesMaster->customer_state) ? $salesMaster->customer_state : null,
                        'customer_zip' => isset($salesMaster->customer_zip) ? $salesMaster->customer_zip : null,
                        'customer_email' => isset($salesMaster->customer_email) ? $salesMaster->customer_email : null,
                        'customer_phone' => isset($salesMaster->customer_phone) ? $salesMaster->customer_phone : null,
                        'setter_id' => isset($salesMaster->salesMasterProcess) ? $salesMaster->salesMasterProcess->setter1_id : null,
                        'sales_setter_email' => isset($salesMaster->salesMasterProcess->setter1_detail) ? $salesMaster->salesMasterProcess->setter1_detail->email : null,
                        'employee_id' => null,
                        'sales_rep_name' => isset($salesMaster->salesMasterProcess->closer1_detail) ? $salesMaster->salesMasterProcess->closer1_detail->first_name : null,
                        'sales_rep_email' => isset($salesMaster->salesMasterProcess->closer1_detail) ? $salesMaster->salesMasterProcess->closer1_detail->email : null,
                        'install_partner' => isset($salesMaster->install_partner) ? $salesMaster->install_partner : null,
                        'install_partner_id' => null,
                        'customer_signoff' => isset($salesMaster->customer_signoff) ? $salesMaster->customer_signoff : null,
                        'm1_date' => isset($salesMaster->m1_date) ? $salesMaster->m1_date : null,
                        'scheduled_install' => null,
                        'install_complete_date' => null,
                        'm2_date' => isset($salesMaster->m2_date) ? $salesMaster->m2_date : null,
                        'date_cancelled' => isset($salesMaster->date_cancelled) ? $salesMaster->date_cancelled : null,
                        'return_sales_date' => null,
                        'gross_account_value' => isset($salesMaster->gross_account_value) ? $salesMaster->gross_account_value : null,
                        'cash_amount' => isset($salesMaster->cash_amount) ? $salesMaster->cash_amount : null,
                        'loan_amount' => isset($salesMaster->loan_amount) ? $salesMaster->loan_amount : null,
                        'kw' => isset($salesMaster->kw) ? $salesMaster->kw : null,
                        'dealer_fee_percentage' => isset($salesMaster->dealer_fee_percentage) ? $salesMaster->dealer_fee_percentage : null,
                        'adders' => isset($salesMaster->adders) ? $salesMaster->adders : null,
                        'cancel_fee' => isset($salesMaster->cancel_fee) ? $salesMaster->cancel_fee : null,
                        'adders_description' => isset($salesMaster->adders_description) ? $salesMaster->adders_description : null,
                        'funding_source' => null,
                        'financing_rate' => null,
                        'financing_term' => null,
                        'product' => isset($salesMaster->product) ? $salesMaster->product : null,
                        'epc' => isset($salesMaster->epc) ? $salesMaster->epc : null,
                        'net_epc' => isset($salesMaster->net_epc) ? $salesMaster->net_epc : null,
                        'status' => 'Unresolved',
                        'type' => $statusType,
                        'closedpayroll_type' => $cloasePayrollType,
                    ];
                    // return $apiData;
                    $getData = LegacyApiNullData::where('pid', $pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
                    if ($getData) {
                        $inserted = LegacyApiNullData::where('id', $getData->id)->update($apiData);
                    } else {
                        $inserted = LegacyApiNullData::create($apiData);
                    }
                }

            }

        }

        return response()->json([
            'ApiName' => 'move_alert_center',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);

    }

    public function payRollMissingDetailByPid($pid)
    {
        $data = LegacyApiNullData::where(['pid' => $pid, 'type' => 'Payroll'])->with('salesMasterProcess')->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
        // return $data->salesMasterProcess->closer1_id;
        if ($data) {

            if (isset($data->salesMasterProcess->closer1_id) && $data->salesMasterProcess->closer1_id != null) {
                // $closer1Id = $data->salesMasterProcess->closer1Detail->id;
                $closer1Id = $data->salesMasterProcess->closer1_id;
                $closer1PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $closer1Id)->where('amount_type', 'm1')->first();
                $closer1PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $closer1Id)->where('amount_type', 'm2')->first();
                $closer1OfficeId = User::select('office_id')->where('id', $closer1Id)->first();
                $closer1Office = Locations::where('id', $closer1OfficeId->office_id)->first();
                $closer1 = [
                    'closer1_id' => $closer1Id,
                    'closer1_name' => isset($data->salesMasterProcess->closer1Detail->first_name) ? $data->salesMasterProcess->closer1Detail->first_name.' '.$data->salesMasterProcess->closer1Detail->last_name : null,
                    'closer1_email' => isset($data->salesMasterProcess->closer1Detail->email) ? $data->salesMasterProcess->closer1Detail->email : null,
                    'closer1_m1_date' => isset($closer1PeriodM1->date) ? $closer1PeriodM1->date : null,
                    'closer1_m1' => isset($closer1PeriodM1->amount) ? $closer1PeriodM1->amount : null,
                    'closer1_pay_period_from_m1' => isset($closer1PeriodM1->pay_period_from) ? $closer1PeriodM1->pay_period_from : null,
                    'closer1_pay_period_to_m1' => isset($closer1PeriodM1->pay_period_to) ? $closer1PeriodM1->pay_period_to : null,
                    'closer1_paid_status_m1' => isset($closer1PeriodM1->status) ? $closer1PeriodM1->status : null,

                    'closer1_m2' => isset($closer1PeriodM2->amount) ? $closer1PeriodM2->amount : null,
                    'closer1_m2_date' => isset($closer1PeriodM2->date) ? $closer1PeriodM2->date : null,
                    'closer1_pay_period_from_m2' => isset($closer1PeriodM2->pay_period_from) ? $closer1PeriodM2->pay_period_from : null,
                    'closer1_pay_period_to_m2' => isset($closer1PeriodM2->pay_period_to) ? $closer1PeriodM2->pay_period_to : null,
                    'closer1_paid_status_m2' => isset($closer1PeriodM2->status) ? $closer1PeriodM2->status : null,
                    'closer1_office_name' => isset($closer1Office) ? $closer1Office->office_name.'|'.$closer1Office->general_code.'|'.$closer1Office->redline_standard : null,

                ];
            }

            if (isset($data->salesMasterProcess->setter1_id) && $data->salesMasterProcess->setter1_id != null) {
                $setter1Id = $data->salesMasterProcess->setter1_id;
                if ($data->salesMasterProcess->closer1_id != $data->salesMasterProcess->setter1_id) {
                    $setter1PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $setter1Id)->where('amount_type', 'm1')->first();
                    $setter1PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $setter1Id)->where('amount_type', 'm2')->first();
                }

                $setter1OfficeId = User::select('office_id')->where('id', $setter1Id)->first();
                $setter1Office = Locations::where('id', $setter1OfficeId->office_id)->first();
                $setter1 = [
                    'setter1_id' => $setter1Id,
                    'setter1_name' => isset($data->salesMasterProcess->setter1Detail->first_name) ? $data->salesMasterProcess->setter1Detail->first_name.' '.$data->salesMasterProcess->setter1Detail->last_name : null,
                    'setter1_email' => isset($data->salesMasterProcess->setter1Detail->email) ? $data->salesMasterProcess->setter1Detail->email : null,
                    'setter1_m1' => isset($setter1PeriodM1->amount) ? $setter1PeriodM1->amount : null,
                    'setter1_m1_date' => isset($setter1PeriodM1->date) ? $setter1PeriodM1->date : null,
                    'setter1_pay_period_from_m1' => isset($setter1PeriodM1->pay_period_from) ? $setter1PeriodM1->pay_period_from : null,
                    'setter1_pay_period_to_m1' => isset($setter1PeriodM1->pay_period_to) ? $setter1PeriodM1->pay_period_to : null,
                    'setter1_paid_status_m1' => isset($setter1PeriodM1->status) ? $setter1PeriodM1->status : null,
                    'setter1_m2' => isset($setter1PeriodM2->amount) ? $setter1PeriodM2->amount : null,
                    'setter1_m2_date' => isset($setter1PeriodM2->date) ? $setter1PeriodM2->date : null,
                    'setter1_pay_period_from_m2' => isset($setter1PeriodM2->pay_period_from) ? $setter1PeriodM2->pay_period_from : null,
                    'setter1_pay_period_to_m2' => isset($setter1PeriodM2->pay_period_to) ? $setter1PeriodM2->pay_period_to : null,
                    'setter1_paid_status_m2' => isset($setter1PeriodM2->status) ? $setter1PeriodM2->status : null,
                    'setter1_office_name' => isset($setter1Office) ? $setter1Office->office_name.'|'.$setter1Office->general_code.'|'.$setter1Office->redline_standard : null,

                ];
            }

            if (isset($data->salesMasterProcess->closer2_id) && $data->salesMasterProcess->closer2_id != null) {
                $closer2Id = $data->salesMasterProcess->closer2_id;
                $closer2PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $closer2Id)->where('amount_type', 'm1')->first();
                $closer2PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $closer2Id)->where('amount_type', 'm2')->first();
                $closer2OfficeId = User::select('office_id')->where('id', $closer2Id)->first();
                $closer2Office = Locations::where('id', $closer2OfficeId->office_id)->first();
                $closer2 = [
                    'closer2_id' => $closer2Id,
                    'closer2_name' => isset($data->salesMasterProcess->closer2Detail->first_name) ? $data->salesMasterProcess->closer2Detail->first_name.' '.$data->salesMasterProcess->closer1Detail->last_name : null,
                    'closer2_email' => isset($data->salesMadatesterProcess->closer2Detail->email) ? $data->salesMasterProcess->closer2Detail->email : null,
                    'closer2_m1_date' => isset($closer2PeriodM1->date) ? $closer2PeriodM1->date : null,
                    'closer2_m1' => isset($closer2PeriodM1->amount) ? $closer2PeriodM1->amount : null,
                    'closer2_pay_period_from_m1' => isset($closer2PeriodM1->pay_period_from) ? $closer2PeriodM1->pay_period_from : null,
                    'closer2_pay_period_to_m1' => isset($closer2PeriodM1->pay_period_to) ? $closer2PeriodM1->pay_period_to : null,
                    'closer2_paid_status_m1' => isset($closer2PeriodM1->status) ? $closer2PeriodM1->status : null,

                    'closer2_m2' => isset($closer2PeriodM2->amount) ? $closer2PeriodM2->amount : null,
                    'closer2_m2_date' => isset($closer2PeriodM2->date) ? $closer2PeriodM2->date : null,
                    'closer2_pay_period_from_m2' => isset($closer2PeriodM2->pay_period_from) ? $closer2PeriodM2->pay_period_from : null,
                    'closer2_pay_period_to_m2' => isset($closer2PeriodM2->pay_period_to) ? $closer2PeriodM2->pay_period_to : null,
                    'closer2_paid_status_m2' => isset($closer2PeriodM2->status) ? $closer2PeriodM2->status : null,
                    'closer2_office_name' => isset($closer2Office) ? $closer2Office->office_name.'|'.$closer2Office->general_code.'|'.$closer2Office->redline_standard : null,

                ];
            }

            if (isset($data->salesMasterProcess->setter2_id) && $data->salesMasterProcess->setter2_id != null) {
                $setter2Id = $data->salesMasterProcess->setter2_id;
                $setter2PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $setter2Id)->where('amount_type', 'm1')->first();
                $setter2PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $setter2Id)->where('amount_type', 'm2')->first();
                $setter2OfficeId = User::select('office_id')->where('id', $setter2Id)->first();
                $setter2Office = Locations::where('id', $setter2OfficeId->office_id)->first();

                $setter2 = [
                    'setter2_id' => $setter2Id,
                    'setter2_name' => isset($data->salesMasterProcess->setter2Detail->first_name) ? $data->salesMasterProcess->setter2Detail->first_name.' '.$data->salesMasterProcess->setter2Detail->last_name : null,
                    'setter2_email' => isset($data->salesMasterProcess->setter2Detail->email) ? $data->salesMasterProcess->setter2Detail->email : null,
                    'setter2_m1' => isset($setter2PeriodM1->amount) ? $setter2PeriodM1->amount : null,
                    'setter2_m1_date' => isset($setter2PeriodM1->date) ? $setter2PeriodM1->date : null,
                    'setter2_pay_period_from_m1' => isset($setter2PeriodM1->pay_period_from) ? $setter2PeriodM1->pay_period_from : null,
                    'setter2_pay_period_to_m1' => isset($setter2PeriodM1->pay_period_to) ? $setter2PeriodM1->pay_period_to : null,
                    'setter2_paid_status_m1' => isset($setter2PeriodM1->status) ? $setter2PeriodM1->status : null,

                    'setter2_m2' => isset($setter2PeriodM2->amount) ? $setter2PeriodM2->amount : null,
                    'setter2_m2_date' => isset($setter2PeriodM2->date) ? $setter2PeriodM2->date : null,
                    'setter2_pay_period_from_m2' => isset($setter2PeriodM2->pay_period_from) ? $setter2PeriodM2->pay_period_from : null,
                    'setter2_pay_period_to_m2' => isset($setter2PeriodM2->pay_period_to) ? $setter2PeriodM2->pay_period_to : null,
                    'setter2_paid_status_m2' => isset($setter2PeriodM2->status) ? $setter2PeriodM2->status : null,
                    'setter2_office_name' => isset($setter2Office) ? $setter2Office->office_name.'|'.$setter2Office->general_code.'|'.$setter2Office->redline_standard : null,

                ];
            }

            $val = [
                'pid' => $data->pid,
                'installer' => $data->install_partner,
                'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                'customer_address' => $data->customer_address,
                'customer_address2' => $data->customer_address_2,
                'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                'cancel_date' => isset($data->cancel_date) ? $data->cancel_date : null,
                'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                'redline' => isset($data->redline) ? $data->redline : null,
                'kw' => isset($data->kw) ? $data->kw : null,
                'rep_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                'm1_amount' => isset($data->m1_amount) ? $data->m1_amount : '',
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                'product' => isset($data->product) ? $data->product : '',
                'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                'gross_account_value' => isset($data->gross_account_value) ? $data->gross_account_value : null,
                'prev_paid' => isset($data->prev_amount_paid) ? $data->prev_amount_paid : null,
                'epc' => isset($data->epc) ? $data->epc : null,
                'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? $data->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : null,
                'prev_deducted_amount' => isset($data->prev_deducted_amount) ? $data->prev_deducted_amount : null,
                'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                'show' => isset($data->adders) ? $data->adders : null,
                'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($data->total_amount_in_period) ? $data->total_amount_in_period : null,
                'prospect_id ' => $data->pid,

                // "pid" => $data->pid,
                // "customer_name" => $data->customer_name,
                'closer1_data' => isset($closer1) ? $closer1 : null,
                'setter1_data' => isset($setter1) ? $setter1 : null,
                'closer2_data' => isset($closer2) ? $closer2 : null,
                'setter2_data' => isset($setter2) ? $setter2 : null,
            ];
        }

        if ($data) {
            return response()->json(['ApiName' => 'Get Missing sales By Pid', 'status' => true, 'data' => $val], 200);
        } else {
            return response()->json(['ApiName' => 'Get Missing sales By Pid', 'status' => true, 'data' => []], 200);
        }
    }

    public function getMissingDetailByPid($pid)
    {
        // echo"DASD";die;
        $data = LegacyApiNullData::where('pid', $pid)->with('salesMasterProcess')->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
        // return $data->salesMasterProcess->closer1_id;
        if ($data) {
            $User = User::where('email', $data->sales_rep_email)->first();
            // return $User;
            if (empty($User)) {
                $additional_user_id = UsersAdditionalEmail::where('email', $data->sales_rep_email)->value('user_id');
                if (! empty($additional_user_id)) {
                    $User = User::where('id', $additional_user_id)->first();
                }
            }

            if ($User) {
                // $closer1Id = $data->salesMasterProcess->closer1Detail->id;
                $closer1Id = $User->id;
                $closer1PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $closer1Id)->where('amount_type', 'm1')->first();
                $closer1PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $closer1Id)->where('amount_type', 'm2')->first();
                $closer1OfficeId = User::select('office_id')->where('id', $closer1Id)->first();
                $closer1Office = Locations::where('id', $closer1OfficeId->office_id)->first();
                $closer1 = [
                    'closer1_id' => $closer1Id,
                    'closer1_name' => isset($User->first_name) ? $User->first_name.' '.$User->last_name : null,
                    'closer1_email' => isset($User->email) ? $User->email : null,
                    'closer1_m1_date' => isset($closer1PeriodM1->date) ? $closer1PeriodM1->date : null,
                    'closer1_m1' => isset($closer1PeriodM1->amount) ? $closer1PeriodM1->amount : null,
                    'closer1_pay_period_from_m1' => isset($closer1PeriodM1->pay_period_from) ? $closer1PeriodM1->pay_period_from : null,
                    'closer1_pay_period_to_m1' => isset($closer1PeriodM1->pay_period_to) ? $closer1PeriodM1->pay_period_to : null,
                    'closer1_paid_status_m1' => isset($closer1PeriodM1->status) ? $closer1PeriodM1->status : null,

                    'closer1_m2' => isset($closer1PeriodM2->amount) ? $closer1PeriodM2->amount : null,
                    'closer1_m2_date' => isset($closer1PeriodM2->date) ? $closer1PeriodM2->date : null,
                    'closer1_pay_period_from_m2' => isset($closer1PeriodM2->pay_period_from) ? $closer1PeriodM2->pay_period_from : null,
                    'closer1_pay_period_to_m2' => isset($closer1PeriodM2->pay_period_to) ? $closer1PeriodM2->pay_period_to : null,
                    'closer1_paid_status_m2' => isset($closer1PeriodM2->status) ? $closer1PeriodM2->status : null,
                    'closer1_office_name' => isset($closer1Office) ? $closer1Office->office_name.'|'.$closer1Office->general_code.'|'.$closer1Office->redline_standard : null,

                ];
            }

            if ((isset($data->salesMasterProcess->setter1_id) && $data->salesMasterProcess->setter1_id != null) || (! empty($data->setter_id))) {
                $setter1Id = ! empty($data->salesMasterProcess->setter1_id) ? $data->salesMasterProcess->setter1_id : $data->setter_id;
                if (! empty($data->salesMasterProcess)) {
                    if ($data->salesMasterProcess->closer1_id != $data->salesMasterProcess->setter1_id) {
                        $setter1PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $setter1Id)->where('amount_type', 'm1')->first();
                        $setter1PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $setter1Id)->where('amount_type', 'm2')->first();
                    }
                }

                $setter1OfficeId = User::where('id', $setter1Id)->first();
                $setter1Office = Locations::where('id', $setter1OfficeId->office_id)->first();
                $setter1 = [
                    'setter1_id' => $setter1Id,
                    //    "setter1_name" => isset($data->salesMasterProcess->setter1Detail->first_name)?$data->salesMasterProcess->setter1Detail->first_name.' '.$data->salesMasterProcess->setter1Detail->last_name:null,
                    //    "setter1_email" => isset($data->salesMasterProcess->setter1Detail->email)?$data->salesMasterProcess->setter1Detail->email:null,
                    'setter1_name' => isset($setter1OfficeId->first_name) ? $setter1OfficeId->first_name.' '.$setter1OfficeId->last_name : null,
                    'setter1_email' => isset($setter1OfficeId->email) ? $setter1OfficeId->email : null,
                    'setter1_m1' => isset($setter1PeriodM1->amount) ? $setter1PeriodM1->amount : null,
                    'setter1_m1_date' => isset($setter1PeriodM1->date) ? $setter1PeriodM1->date : null,
                    'setter1_pay_period_from_m1' => isset($setter1PeriodM1->pay_period_from) ? $setter1PeriodM1->pay_period_from : null,
                    'setter1_pay_period_to_m1' => isset($setter1PeriodM1->pay_period_to) ? $setter1PeriodM1->pay_period_to : null,
                    'setter1_paid_status_m1' => isset($setter1PeriodM1->status) ? $setter1PeriodM1->status : null,
                    'setter1_m2' => isset($setter1PeriodM2->amount) ? $setter1PeriodM2->amount : null,
                    'setter1_m2_date' => isset($setter1PeriodM2->date) ? $setter1PeriodM2->date : null,
                    'setter1_pay_period_from_m2' => isset($setter1PeriodM2->pay_period_from) ? $setter1PeriodM2->pay_period_from : null,
                    'setter1_pay_period_to_m2' => isset($setter1PeriodM2->pay_period_to) ? $setter1PeriodM2->pay_period_to : null,
                    'setter1_paid_status_m2' => isset($setter1PeriodM2->status) ? $setter1PeriodM2->status : null,
                    'setter1_office_name' => isset($setter1Office) ? $setter1Office->office_name.'|'.$setter1Office->general_code.'|'.$setter1Office->redline_standard : null,

                ];
            }

            if (isset($data->salesMasterProcess->closer2_id) && $data->salesMasterProcess->closer2_id != null) {
                $closer2Id = $data->salesMasterProcess->closer2_id;
                $closer2PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $closer2Id)->where('amount_type', 'm1')->first();
                $closer2PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $closer2Id)->where('amount_type', 'm2')->first();
                $closer2OfficeId = User::select('office_id')->where('id', $closer2Id)->first();
                $closer2Office = Locations::where('id', $closer2OfficeId->office_id)->first();
                $closer2 = [
                    'closer2_id' => $closer2Id,
                    'closer2_name' => isset($data->salesMasterProcess->closer2Detail->first_name) ? $data->salesMasterProcess->closer2Detail->first_name.' '.$data->salesMasterProcess->closer1Detail->last_name : null,
                    'closer2_email' => isset($data->salesMadatesterProcess->closer2Detail->email) ? $data->salesMasterProcess->closer2Detail->email : null,
                    'closer2_m1_date' => isset($closer2PeriodM1->date) ? $closer2PeriodM1->date : null,
                    'closer2_m1' => isset($closer2PeriodM1->amount) ? $closer2PeriodM1->amount : null,
                    'closer2_pay_period_from_m1' => isset($closer2PeriodM1->pay_period_from) ? $closer2PeriodM1->pay_period_from : null,
                    'closer2_pay_period_to_m1' => isset($closer2PeriodM1->pay_period_to) ? $closer2PeriodM1->pay_period_to : null,
                    'closer2_paid_status_m1' => isset($closer2PeriodM1->status) ? $closer2PeriodM1->status : null,

                    'closer2_m2' => isset($closer2PeriodM2->amount) ? $closer2PeriodM2->amount : null,
                    'closer2_m2_date' => isset($closer2PeriodM2->date) ? $closer2PeriodM2->date : null,
                    'closer2_pay_period_from_m2' => isset($closer2PeriodM2->pay_period_from) ? $closer2PeriodM2->pay_period_from : null,
                    'closer2_pay_period_to_m2' => isset($closer2PeriodM2->pay_period_to) ? $closer2PeriodM2->pay_period_to : null,
                    'closer2_paid_status_m2' => isset($closer2PeriodM2->status) ? $closer2PeriodM2->status : null,
                    'closer2_office_name' => isset($closer2Office) ? $closer2Office->office_name.'|'.$closer2Office->general_code.'|'.$closer2Office->redline_standard : null,

                ];
            }

            if (isset($data->salesMasterProcess->setter2_id) && $data->salesMasterProcess->setter2_id != null) {
                $setter2Id = $data->salesMasterProcess->setter2_id;
                $setter2PeriodM1 = UserCommission::where('pid', $pid)->where('user_id', $setter2Id)->where('amount_type', 'm1')->first();
                $setter2PeriodM2 = UserCommission::where('pid', $pid)->where('user_id', $setter2Id)->where('amount_type', 'm2')->first();
                $setter2OfficeId = User::select('office_id')->where('id', $setter2Id)->first();
                $setter2Office = Locations::where('id', $setter2OfficeId->office_id)->first();

                $setter2 = [
                    'setter2_id' => $setter2Id,
                    'setter2_name' => isset($data->salesMasterProcess->setter2Detail->first_name) ? $data->salesMasterProcess->setter2Detail->first_name.' '.$data->salesMasterProcess->setter2Detail->last_name : null,
                    'setter2_email' => isset($data->salesMasterProcess->setter2Detail->email) ? $data->salesMasterProcess->setter2Detail->email : null,
                    'setter2_m1' => isset($setter2PeriodM1->amount) ? $setter2PeriodM1->amount : null,
                    'setter2_m1_date' => isset($setter2PeriodM1->date) ? $setter2PeriodM1->date : null,
                    'setter2_pay_period_from_m1' => isset($setter2PeriodM1->pay_period_from) ? $setter2PeriodM1->pay_period_from : null,
                    'setter2_pay_period_to_m1' => isset($setter2PeriodM1->pay_period_to) ? $setter2PeriodM1->pay_period_to : null,
                    'setter2_paid_status_m1' => isset($setter2PeriodM1->status) ? $setter2PeriodM1->status : null,

                    'setter2_m2' => isset($setter2PeriodM2->amount) ? $setter2PeriodM2->amount : null,
                    'setter2_m2_date' => isset($setter2PeriodM2->date) ? $setter2PeriodM2->date : null,
                    'setter2_pay_period_from_m2' => isset($setter2PeriodM2->pay_period_from) ? $setter2PeriodM2->pay_period_from : null,
                    'setter2_pay_period_to_m2' => isset($setter2PeriodM2->pay_period_to) ? $setter2PeriodM2->pay_period_to : null,
                    'setter2_paid_status_m2' => isset($setter2PeriodM2->status) ? $setter2PeriodM2->status : null,
                    'setter2_office_name' => isset($setter2Office) ? $setter2Office->office_name.'|'.$setter2Office->general_code.'|'.$setter2Office->redline_standard : null,

                ];
            }
            $rep_id = isset($User->id) ? $User->id : null;
            $val = [
                'pid' => $data->pid,
                'job_status' => $data->job_status,
                'installer' => $data->install_partner,
                'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                'customer_address' => $data->customer_address,
                'customer_address2' => $data->customer_address_2,
                'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                'customer_state' => isset($data->customer_state) ? $data->customer_state : null,
                'location_code' => isset($data->location_code) ? $data->location_code : null,
                'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                'cancel_date' => isset($data->date_cancelled) ? $data->date_cancelled : null,
                'homeowner_id' => isset($data->homeowner_id) ? $data->homeowner_id : null,
                'proposal_id' => isset($data->proposal_id) ? $data->proposal_id : null,
                'redline' => isset($data->redline) ? $data->redline : null,
                'kw' => isset($data->kw) ? $data->kw : null,
                'rep_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : $rep_id,
                'rep_email' => isset($data->sales_rep_email) ? $data->sales_rep_email : null,
                'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : $data->setter_id,
                'approved_date' => isset($data->customer_signoff) ? dateToYMD($data->customer_signoff) : null,
                'last_date_pd' => isset($data->last_date_pd) ? dateToYMD($data->last_date_pd) : null,
                'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                'm1_amount' => isset($data->m1_amount) ? $data->m1_amount : '',
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                'm2_amount' => isset($data->m2_amount) ? $data->m2_amount : '',
                'product' => isset($data->product) ? $data->product : '',
                'total_for_acct' => isset($data->total_for_acct) ? $data->total_for_acct : 0,
                'gross_account_value' => isset($data->gross_account_value) ? $data->gross_account_value : null,
                'prev_paid' => isset($data->prev_amount_paid) ? $data->prev_amount_paid : null,
                'epc' => isset($data->epc) ? $data->epc : null,
                'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                'dealer_fee_percentage' => isset($data->dealer_fee_percentage) ? $data->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($data->dealer_fee_amount) ? $data->dealer_fee_amount : null,
                'prev_deducted_amount' => isset($data->prev_deducted_amount) ? $data->prev_deducted_amount : null,
                'cancel_fee' => isset($data->cancel_fee) ? $data->cancel_fee : null,
                'show' => isset($data->adders) ? $data->adders : null,
                'cancel_deduction' => isset($data->cancel_deduction) ? $data->cancel_deduction : null,
                'adders_description' => isset($data->adders_description) ? $data->adders_description : null,
                'lead_cost_amount' => isset($data->lead_cost) ? $data->lead_cost : null,
                'adv_pay_back_amount' => isset($data->adv_pay_back_amount) ? $data->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($data->total_amount_in_period) ? $data->total_amount_in_period : null,
                'prospect_id' => $data->pid,

                // "pid" => $data->pid,
                // "customer_name" => $data->customer_name,
                'closer1_data' => isset($closer1) ? $closer1 : null,
                'setter1_data' => isset($setter1) ? $setter1 : null,
                'closer2_data' => isset($closer2) ? $closer2 : null,
                'setter2_data' => isset($setter2) ? $setter2 : null,
            ];
        }

        if ($data) {
            return response()->json(['ApiName' => 'Get Missing sales By Pid', 'status' => true, 'data' => $val], 200);
        } else {
            return response()->json(['ApiName' => 'Get Missing sales By Pid', 'status' => true, 'data' => []], 200);
        }
    }

    public function updatePayRollMissingPeriod(Request $request)
    {
        // return $request;
        $pid = $request->pid;
        $payroll = $request->payroll;
        if (count($payroll) > 0) {
            foreach ($payroll as $key => $val) {

                $userId = $val['user_id'];
                $pay_period_from_m1 = $val['pay_period_from_m1'];
                $pay_period_to_m1 = $val['pay_period_to_m1'];
                $pay_period_from_m2 = $val['pay_period_from_m2'];
                $pay_period_to_m2 = $val['pay_period_to_m2'];

                $commissionM1 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm1', 'status' => 1])->first();
                if ($commissionM1) {
                    $updatedata = ['pay_period_from' => $pay_period_from_m1, 'pay_period_to' => $pay_period_to_m1];
                    $update = UserCommission::where(['id' => $commissionM1->id])->update($updatedata);

                    updateExistingPayroll($userId, $pay_period_from_m1, $pay_period_to_m1, $commissionM1->amount, 'commission', $commissionM1->position_id, 0);

                    $datacreate = $this->closedPayrollDataHistory($pid, $pay_period_from_m1, $pay_period_to_m1);

                }

                $commissionM2 = UserCommission::where(['user_id' => $userId, 'pid' => $pid, 'amount_type' => 'm2', 'status' => 1])->first();
                if ($commissionM2) {
                    $updatedata = ['pay_period_from' => $pay_period_from_m2, 'pay_period_to' => $pay_period_to_m2];
                    $update = UserCommission::where(['id' => $commissionM2->id])->update($updatedata);

                    updateExistingPayroll($userId, $pay_period_from_m2, $pay_period_to_m2, $commissionM2->amount, 'commission', $commissionM2->position_id, 0);

                    $datacreate = $this->closedPayrollDataHistory($pid, $pay_period_from_m2, $pay_period_to_m2);

                }

                $overrrides = UserOverrides::where(['sale_user_id' => $userId, 'pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => 1])->get();
                if (count($overrrides) > 0) {
                    foreach ($overrrides as $key1 => $over) {
                        $updatedata = ['pay_period_from' => $pay_period_from_m2, 'pay_period_to' => $pay_period_to_m2];
                        $update = UserOverrides::where(['id' => $over->id])->update($updatedata);

                        updateExistingPayroll($over->userId, $pay_period_from_m2, $pay_period_to_m2, $over->amount, 'override', $over->position_id, 0);

                    }
                }

            }

            LegacyApiNullData::where('pid', $pid)->update(['status' => 'Resolved']);
        }
        Artisan::call('generate:alert');

        return response()->json([
            'ApiName' => 'update_closed_pid_data',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $data,
        ], 200);
    }
}
