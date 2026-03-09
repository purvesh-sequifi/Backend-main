<?php

namespace App\Core\Traits;

use App\Models\BackendSetting;
use App\Models\ClawbackSettlement;
use App\Models\CloserIdentifyAlert;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\DeductionAlert;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LegacyApiRowData;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\Payroll;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\SaleDataUpdateLogs;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SetterIdentifyAlert;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserOverrides;
use App\Models\UserReconciliationCommission;
use App\Models\UserReconciliationWithholding;
use App\Models\UserRedlines;
use App\Models\UsersAdditionalEmail;
use App\Models\UserSelfGenCommmissionHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWithheldHistory;
use Log;

trait SubroutineListTrait
{
    use EditSaleTrait;
    use OverrideCommissionTrait;
    use OverrideStackTrait;
    use PayFrequencyTrait;
    use PayRollClawbackTrait;
    use PayRollCommissionTrait;
    use PayRollDeductionTrait;
    use ReconciliationPeriodTrait;

    public function subroutineOne($checked)
    {
        $rep_email = isset($checked->sales_rep_email) ? $checked->sales_rep_email : null;
        $setterId = isset($checked->salesMasterProcess->setter1_id) ? $checked->salesMasterProcess->setter1_id : null;

        $closer = User::where('email', $rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        if ($closer) {
            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            $UpdateData->closer1_id = isset($closer->id) ? $closer->id : 0;
            $UpdateData->save();

            // Identify Setter
            if ($setterId != null) {
                // $setterId = '565656';
                $setterIdCheck = User::where('id', $setterId)->first();
                if (isset($setterIdCheck) && $setterIdCheck != '') {
                    //  Setter Data is updated
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    $UpdateData->setter1_id = isset($setterId) ? $setterId : 0;
                    $UpdateData->save();

                } else {
                    $setter['pid'] = $checked->pid;
                    $setter['sales_rep_email'] = $checked->sales_rep_email;
                    $saleMasterProcess = SetterIdentifyAlert::where(['pid' => $checked->pid, 'sales_rep_email' => $checked->sales_rep_email])->first();
                    if (empty($saleMasterProcess)) {

                        SetterIdentifyAlert::create($setter);

                        return false;
                    }

                }
            } else {
                $setter['pid'] = $checked->pid;
                $setter['sales_rep_email'] = $checked->sales_rep_email;
                $saleMasterProcess = SetterIdentifyAlert::where(['pid' => $checked->pid, 'sales_rep_email' => $checked->sales_rep_email])->first();
                if (empty($saleMasterProcess)) {
                    SetterIdentifyAlert::create($setter);

                    return false;
                }

            }

        } else {
            $closers['pid'] = $checked->pid;
            $closers['sales_rep_email'] = $checked->sales_rep_email;

            $saleMasterProcess = CloserIdentifyAlert::where(['pid' => $checked->pid, 'sales_rep_email' => $checked->sales_rep_email])->first();
            if (empty($saleMasterProcess)) {
                $close = CloserIdentifyAlert::create($closers);

                return false;
            }

        }

        return true;
    }

    public function subroutineTwo($val, $lid = '')
    {
        // $data['ct'] = null;
        $status = '';
        $response = [];
        $updated_pid_raw = '';
        $new_pid_raw = '';
        $updated_pid_null = '';
        $new_pid_null = '';

        // if ($val->prospect_id != null && $val->customer_name != null && $val->kw != null && $val->customer_state != null && $val->rep_name != null && $val->rep_email != null) {
        $user = User::where('email', $val->rep_email)->first();
        if (empty($user)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $val->rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $user = User::where('id', $additional_user_id)->first();
            }
        }
        if (! empty($val->prospect_id) && ! empty($val->customer_signoff) && ! empty($val->gross_account_value) && ! empty($val->epc) && ! empty($val->net_epc) && ! empty($val->dealer_fee_percentage) && ! empty($val->customer_name) && ! empty($val->kw) && ! empty($val->customer_state) && ! empty($val->rep_name) && ! empty($val->rep_email) && ! empty($val->setter_id) && $user != '') {
            $updated = '';
            $inserted = '';
            $checkPid = LegacyApiRowData::where('pid', $val->prospect_id)->first();
            $data['legacy_data_id'] = check_null_and_matching_data($checkPid, 'legacy_data_id', $val, 'id');
            // $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
            // $data['page'] = isset($lid['pageid']) ? $lid['pageid'] : null;
            $data['pid'] = check_null_and_matching_data($checkPid, 'pid', $val, 'prospect_id'); // isset($val->prospect_id) ? $val->prospect_id : null;
            $data['homeowner_id'] = check_null_and_matching_data($checkPid, 'homeowner_id', $val, 'homeowner_id'); // isset($val->homeowner_id) ? $val->homeowner_id : null;
            $data['proposal_id'] = check_null_and_matching_data($checkPid, 'proposal_id', $val, 'proposal_id'); // isset($val->proposal_id) ? $val->proposal_id : null;
            $data['customer_name'] = check_null_and_matching_data($checkPid, 'customer_name', $val, 'customer_name'); // isset($val->customer_name) ? $val->customer_name : null;
            $data['customer_address'] = check_null_and_matching_data($checkPid, 'customer_address', $val, 'customer_address'); // isset($val->customer_address) ? $val->customer_address : null;
            $data['customer_address_2'] = check_null_and_matching_data($checkPid, 'customer_address_2', $val, 'customer_address_2'); // isset($val->customer_address_2) ? $val->customer_address_2 : null;
            $data['customer_city'] = check_null_and_matching_data($checkPid, 'customer_city', $val, 'customer_city'); // isset($val->customer_city) ? $val->customer_city : null;
            $data['customer_state'] = check_null_and_matching_data($checkPid, 'customer_state', $val, 'customer_state'); // isset($val->customer_state) ? $val->customer_state : null;
            $data['customer_zip'] = check_null_and_matching_data($checkPid, 'customer_zip', $val, 'customer_zip'); // isset($val->customer_zip) ? $val->customer_zip : null;
            $data['customer_email'] = check_null_and_matching_data($checkPid, 'customer_email', $val, 'customer_email'); // $val->customer_email) ? $val->customer_email : null;
            $data['customer_phone'] = check_null_and_matching_data($checkPid, 'customer_phone', $val, 'customer_phone'); // $val->customer_phone) ? $val->customer_phone : null;
            $data['setter_id'] = check_null_and_matching_data($checkPid, 'setter_id', $val, 'setter_id'); // $val->setter_id) ? $val->setter_id : null;
            $data['employee_id'] = check_null_and_matching_data($checkPid, 'employee_id', $val, 'employee_id'); // $val->employee_id) ? $val->employee_id : null;
            $data['sales_rep_name'] = check_null_and_matching_data($checkPid, 'sales_rep_name', $val, 'rep_name'); // $val->rep_name) ? $val->rep_name : null;
            $data['sales_rep_email'] = check_null_and_matching_data($checkPid, 'sales_rep_email', $val, 'rep_email'); // $val->rep_email) ? $val->rep_email : null;
            $data['install_partner'] = check_null_and_matching_data($checkPid, 'install_partner', $val, 'install_partner'); // $val->install_partner) ? $val->install_partner : null;
            $data['install_partner_id'] = check_null_and_matching_data($checkPid, 'install_partner_id', $val, 'install_partner_id'); // $val->install_partner_id) ? $val->install_partner_id : null;
            // $data['customer_signoff'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
            $data['customer_signoff'] = check_null_and_matching_data($checkPid, 'customer_signoff', $val, 'customer_signoff'); // $val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
            // $data['m1_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
            $data['m1_date'] = check_null_and_matching_data($checkPid, 'm1_date', $val, 'm1'); // $val->m1) ? $val->m1 : null;
            // $data['scheduled_install'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
            $data['scheduled_install'] = check_null_and_matching_data($checkPid, 'scheduled_install', $val, 'scheduled_install'); // $val->scheduled_install) ? $val->scheduled_install : null;
            $data['install_complete_date'] = check_null_and_matching_data($checkPid, 'install_complete_date', $val, 'install_complete'); // $val->install_complete)?$val->install_complete:null;
            // $data['m2_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
            $data['m2_date'] = check_null_and_matching_data($checkPid, 'm2_date', $val, 'm2'); // $val->m2) ? $val->m2 : null;
            // $data['date_cancelled'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
            $date_cancelled = check_null_and_matching_data($checkPid, 'date_cancelled', $val, 'date_cancelled');
            $data['date_cancelled'] = empty($date_cancelled) ? null : $date_cancelled; // $val->date_cancelled) ? $val->date_cancelled : null;
            // $data['return_sales_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
            $data['return_sales_date'] = check_null_and_matching_data($checkPid, 'return_sales_date', $val, 'return_sales_date'); // $val->return_sales_date) ? $val->return_sales_date : null;
            $data['gross_account_value'] = check_null_and_matching_data($checkPid, 'gross_account_value', $val, 'gross_account_value'); // $val->gross_account_value) ? $val->gross_account_value : null;
            $data['cash_amount'] = check_null_and_matching_data($checkPid, 'cash_amount', $val, 'cash_amount'); // $val->cash_amount) ? $val->cash_amount : null;
            $data['loan_amount'] = check_null_and_matching_data($checkPid, 'loan_amount', $val, 'loan_amount'); // $val->loan_amount) ? $val->loan_amount : null;
            $data['kw'] = check_null_and_matching_data($checkPid, 'kw', $val, 'kw'); // $val->kw) ? $val->kw : null;
            $data['dealer_fee_percentage'] = check_null_and_matching_data($checkPid, 'dealer_fee_percentage', $val, 'dealer_fee_percentage'); // $val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            $data['adders'] = check_null_and_matching_data($checkPid, 'adders', $val, 'adders'); // $val->adders) ? $val->adders : null;
            $data['cancel_fee'] = check_null_and_matching_data($checkPid, 'cancel_fee', $val, 'cancel_fee'); // $val->cancel_fee) ? $val->cancel_fee : null;
            $data['adders_description'] = check_null_and_matching_data($checkPid, 'adders_description', $val, 'adders_description'); // $val->adders_description) ? $val->adders_description : null;
            $data['funding_source'] = check_null_and_matching_data($checkPid, 'funding_source', $val, 'funding_source'); // $val->funding_source) ? $val->funding_source : null;
            $data['financing_rate'] = check_null_and_matching_data($checkPid, 'financing_rate', $val, 'financing_rate'); // $val->financing_rate) ? $val->financing_rate : 0.00;
            $data['financing_term'] = check_null_and_matching_data($checkPid, 'financing_term', $val, 'financing_term'); // $val->financing_term) ? $val->financing_term : null;
            $data['product'] = check_null_and_matching_data($checkPid, 'product', $val, 'product'); // $val->product) ? $val->product : null;
            $data['epc'] = check_null_and_matching_data($checkPid, 'epc', $val, 'epc'); // $val->epc) ? $val->epc : null;
            $data['net_epc'] = check_null_and_matching_data($checkPid, 'net_epc', $val, 'net_epc'); // $val->net_epc) ? $val->net_epc : null;
            $source_created_at = check_null_and_matching_data($checkPid, 'source_created_at', $val, 'created');
            $data['source_created_at'] = empty($source_created_at) ? null : date('Y-m-d H:i:s', strtotime($source_created_at)); // $val->created) ? date('Y-m-d H:i:s',strtotime($val->created)) : null;
            $source_updated_at = check_null_and_matching_data($checkPid, 'source_updated_at', $val, 'modified');
            $data['source_updated_at'] = empty($source_updated_at) ? null : date('Y-m-d H:i:s', strtotime($source_updated_at)); // $val->modified) ? date('Y-m-d H:i:s',strtotime($val->modified)) : null;
            $data['data_source_type'] = 'api';
            // Log::info("UPDATE:".$data['date_cancelled']);

            if (! empty($checkPid)) {
                // m1 m2 kw epc ,net_epc, rep_email, type =api
                $check_keys = ['m1_date', 'm2_date', 'epc', 'net_epc', 'sales_rep_email', 'date_cancelled'];
                $val_check_keys = ['m1', 'm2', 'epc', 'net_epc', 'rep_email', 'date_cancelled'];

                $create_history = false;
                $old_data_arr = [];
                $new_data_arr = [];
                $checkPid_arr = $checkPid->toArray();
                $message_str = [];
                foreach ($val_check_keys as $postion => $check_key) {
                    if (($checkPid_arr[$check_keys[$postion]] == null && $val->$check_key != null) || ($checkPid_arr[$check_keys[$postion]] != null && $checkPid_arr[$check_keys[$postion]] != $val->$check_key)) {
                        $old_data_arr[$check_keys[$postion]] = $checkPid_arr[$check_keys[$postion]];
                        $new_data_arr[$check_keys[$postion]] = $val->$check_key;
                        $create_history = true;
                        if ($check_keys[$postion] == 'm1_date' || $check_keys[$postion] == 'm2_date' || $check_keys[$postion] == 'date_cancelled') {
                            $date_new = get_date_only($val->$check_key);
                            $date_old = get_date_only($checkPid_arr[$check_keys[$postion]]);
                            if ($date_new != $date_old) {
                                $payroll_data = Payroll::join('user_commission', 'user_commission.user_id', '=', 'payrolls.user_id')->where('user_commission.pid', $val->prospect_id)->where('user_commission.amount_type', $check_key)->get();
                                foreach ($payroll_data as $payroll) {
                                    if ($payroll['is_mark_paid'] == 1) {
                                        $data[$check_keys[$postion]] = $checkPid_arr[$check_keys[$postion]];
                                    }
                                }
                                $message_str[] = $check_keys[$postion].'_old:'.$date_old.','.$check_keys[$postion].'_new:'.$date_new;
                            }
                        } else {
                            $message_str[] = $check_keys[$postion].'_old:'.$checkPid_arr[$check_keys[$postion]].','.$check_keys[$postion].'_new:'.$val->$check_key;
                        }
                    }
                }
                // Log::info($message_str);
                if (! empty($message_str)) {
                    $message = implode('|', $message_str);
                    $log_data = ['pid' => $val->prospect_id, 'message_text' => $message];
                    SaleDataUpdateLogs::create($log_data);
                }

                // $old_data_arr['source_created_at'] = $checkPid_arr['updated_at'];
                // $new_data_arr['source_created_at'] = date('Y-m-d H:i:s');
                // $data_source_type = $checkPid_arr['data_source_type'];
                // if($data_source_type == 'api' && $create_history){
                //     $historyData = [
                //         'pid' => $val->prospect_id
                //         ,'old_data' => json_encode($old_data_arr)
                //         ,'new_data' => json_encode($new_data_arr)
                //     ];
                //     $LegacyApiRawDataUpdateHistory = LegacyApiRawDataHistory::create($historyData);
                // }

                $updated = LegacyApiRowData::where('pid', $val->prospect_id)->update($data);
                if (! empty($updated)) {
                    $updated_pid_raw = $val->prospect_id;
                    $status = 'LegacyApiRowData_update';
                }
            } else {
                $inserted = LegacyApiRowData::create($data);
                if (! empty($inserted)) {
                    $new_pid_raw = $val->prospect_id;
                    $status = 'LegacyApiRowData_insert';
                }
            }

        } else {
            $updated = '';
            $inserted = '';
            // Insert null data in table for alert admin...............................................
            $checkPid = LegacyApiNullData::where('pid', $val->prospect_id)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();

            // $data['weekly_sheet_id'] = isset($lid['weekid']) ? $lid['weekid'] : null;
            $data['legacy_data_id'] = check_null_and_matching_data($checkPid, 'legacy_data_id', $val, 'id');
            $data['pid'] = check_null_and_matching_data($checkPid, 'pid', $val, 'prospect_id'); // isset($val->prospect_id) ? $val->prospect_id : null;
            $data['homeowner_id'] = check_null_and_matching_data($checkPid, 'homeowner_id', $val, 'homeowner_id'); // isset($val->homeowner_id) ? $val->homeowner_id : null;
            $data['proposal_id'] = check_null_and_matching_data($checkPid, 'proposal_id', $val, 'proposal_id'); // isset($val->proposal_id) ? $val->proposal_id : null;
            $data['customer_name'] = check_null_and_matching_data($checkPid, 'customer_name', $val, 'customer_name'); // isset($val->customer_name) ? $val->customer_name : null;
            $data['customer_address'] = check_null_and_matching_data($checkPid, 'customer_address', $val, 'customer_address'); // isset($val->customer_address) ? $val->customer_address : null;
            $data['customer_address_2'] = check_null_and_matching_data($checkPid, 'customer_address_2', $val, 'customer_address_2'); // isset($val->customer_address_2) ? $val->customer_address_2 : null;
            $data['customer_city'] = check_null_and_matching_data($checkPid, 'customer_city', $val, 'customer_city'); // isset($val->customer_city) ? $val->customer_city : null;
            $data['customer_state'] = check_null_and_matching_data($checkPid, 'customer_state', $val, 'customer_state'); // isset($val->customer_state) ? $val->customer_state : null;
            $data['customer_zip'] = check_null_and_matching_data($checkPid, 'customer_zip', $val, 'customer_zip'); // isset($val->customer_zip) ? $val->customer_zip : null;
            $data['customer_email'] = check_null_and_matching_data($checkPid, 'customer_email', $val, 'customer_email'); // $val->customer_email) ? $val->customer_email : null;
            $data['customer_phone'] = check_null_and_matching_data($checkPid, 'customer_phone', $val, 'customer_phone'); // $val->customer_phone) ? $val->customer_phone : null;
            $data['setter_id'] = check_null_and_matching_data($checkPid, 'setter_id', $val, 'setter_id'); // $val->setter_id) ? $val->setter_id : null;
            $data['employee_id'] = check_null_and_matching_data($checkPid, 'employee_id', $val, 'employee_id'); // $val->employee_id) ? $val->employee_id : null;
            $data['sales_rep_name'] = check_null_and_matching_data($checkPid, 'sales_rep_name', $val, 'rep_name'); // $val->rep_name) ? $val->rep_name : null;
            $data['sales_rep_email'] = check_null_and_matching_data($checkPid, 'sales_rep_email', $val, 'rep_email'); // $val->rep_email) ? $val->rep_email : null;
            $data['install_partner'] = check_null_and_matching_data($checkPid, 'install_partner', $val, 'install_partner'); // $val->install_partner) ? $val->install_partner : null;
            $data['install_partner_id'] = check_null_and_matching_data($checkPid, 'install_partner_id', $val, 'install_partner_id'); // $val->install_partner_id) ? $val->install_partner_id : null;
            // $data['customer_signoff'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
            $data['customer_signoff'] = check_null_and_matching_data($checkPid, 'customer_signoff', $val, 'customer_signoff'); // $val->customer_signoff) && $val->customer_signoff != null ? $val->customer_signoff : null;
            // $data['m1_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
            $data['m1_date'] = check_null_and_matching_data($checkPid, 'm1_date', $val, 'm1'); // $val->m1) ? $val->m1 : null;
            // $data['scheduled_install'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
            $data['scheduled_install'] = check_null_and_matching_data($checkPid, 'scheduled_install', $val, 'scheduled_install'); // $val->scheduled_install) ? $val->scheduled_install : null;
            $data['install_complete_date'] = check_null_and_matching_data($checkPid, 'install_complete_date', $val, 'install_complete'); // $val->install_complete)?$val->install_complete:null;
            // $data['m2_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
            $data['m2_date'] = check_null_and_matching_data($checkPid, 'm2_date', $val, 'm2'); // $val->m2) ? $val->m2 : null;
            // $data['date_cancelled'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
            $data['date_cancelled'] = check_null_and_matching_data($checkPid, 'date_cancelled', $val, 'date_cancelled'); // $val->date_cancelled) ? $val->date_cancelled : null;
            // $data['return_sales_date'] = check_null_and_matching_data($checkPid,'homeowner_id',$val,'homeowner_id');//$val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
            $data['return_sales_date'] = check_null_and_matching_data($checkPid, 'return_sales_date', $val, 'return_sales_date'); // $val->return_sales_date) ? $val->return_sales_date : null;
            $data['gross_account_value'] = check_null_and_matching_data($checkPid, 'gross_account_value', $val, 'gross_account_value'); // $val->gross_account_value) ? $val->gross_account_value : null;
            $data['cash_amount'] = check_null_and_matching_data($checkPid, 'cash_amount', $val, 'cash_amount'); // $val->cash_amount) ? $val->cash_amount : null;
            $data['loan_amount'] = check_null_and_matching_data($checkPid, 'loan_amount', $val, 'loan_amount'); // $val->loan_amount) ? $val->loan_amount : null;
            $data['kw'] = check_null_and_matching_data($checkPid, 'kw', $val, 'kw'); // $val->kw) ? $val->kw : null;
            $data['dealer_fee_percentage'] = check_null_and_matching_data($checkPid, 'dealer_fee_percentage', $val, 'dealer_fee_percentage'); // $val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            $data['adders'] = check_null_and_matching_data($checkPid, 'adders', $val, 'adders'); // $val->adders) ? $val->adders : null;
            $data['cancel_fee'] = check_null_and_matching_data($checkPid, 'cancel_fee', $val, 'cancel_fee'); // $val->cancel_fee) ? $val->cancel_fee : null;
            $data['adders_description'] = check_null_and_matching_data($checkPid, 'adders_description', $val, 'adders_description'); // $val->adders_description) ? $val->adders_description : null;
            $data['funding_source'] = check_null_and_matching_data($checkPid, 'funding_source', $val, 'funding_source'); // $val->funding_source) ? $val->funding_source : null;
            $data['financing_rate'] = check_null_and_matching_data($checkPid, 'financing_rate', $val, 'financing_rate'); // $val->financing_rate) ? $val->financing_rate : 0.00;
            $data['financing_term'] = check_null_and_matching_data($checkPid, 'financing_term', $val, 'financing_term'); // $val->financing_term) ? $val->financing_term : null;
            $data['product'] = check_null_and_matching_data($checkPid, 'product', $val, 'product'); // $val->product) ? $val->product : null;
            $data['epc'] = check_null_and_matching_data($checkPid, 'epc', $val, 'epc'); // $val->epc) ? $val->epc : null;
            $data['net_epc'] = check_null_and_matching_data($checkPid, 'net_epc', $val, 'net_epc'); // $val->net_epc) ? $val->net_epc : null;
            $source_created_at = check_null_and_matching_data($checkPid, 'source_created_at', $val, 'created');
            $data['source_created_at'] = empty($source_created_at) ? null : date('Y-m-d H:i:s', strtotime($source_created_at)); // $val->created) ? date('Y-m-d H:i:s',strtotime($val->created)) : null;
            $source_updated_at = check_null_and_matching_data($checkPid, 'source_updated_at', $val, 'modified');
            $data['source_updated_at'] = empty($source_updated_at) ? null : date('Y-m-d H:i:s', strtotime($source_updated_at)); // $val->modified) ? date('Y-m-d H:i:s',strtotime($val->modified)) : null;
            $data['email_status'] = 0;
            $data['data_source_type'] = 'api';
            // Log::info('LegacyApiNullData :'.json_encode($data));

            if ($checkPid) {
                $updated = LegacyApiNullData::where('id', $checkPid->id)->update($data);
                if (! empty($updated)) {
                    $updated_pid_null = $val->prospect_id;
                    $status = 'LegacyApiNullData_update';
                }
            } else {
                $inserted = LegacyApiNullData::create($data);
                if (! empty($inserted)) {
                    $new_pid_null = $val->prospect_id;
                    $status = 'LegacyApiNullData_insert';
                }
            }

        }

        return ['status' => $status, 'new_pid_null' => $new_pid_null, 'updated_pid_null' => $updated_pid_null, 'updated_pid_raw' => $updated_pid_raw, 'new_pid_raw' => $new_pid_raw];
    }

    public function subroutine_code_api_excel_old()
    {
        $newData = SalesMaster::with('salesMasterProcess')->orderBy('id', 'asc')->get();
        // Is there a clawback date = dateCancelled ?
        foreach ($newData as $checked) {
            $dateCancelled = $checked->date_cancelled;
            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            $epc = $checked->epc;
            $netEpc = $checked->net_epc;
            $approvedDate = $checked->customer_signoff;
            $customerState = $checked->customer_state;
            $kw = $checked->kw;

            $m1_paid_status = $checked->salesMasterProcess->setter1_m1_paid_status;
            $m2_paid_status = $checked->salesMasterProcess->setter1_m2_paid_status;

            $closer1_id = $checked->salesMasterProcess->closer1_id;
            $closer2_id = $checked->salesMasterProcess->closer2_id;
            $setter1_id = $checked->salesMasterProcess->setter1_id;
            $setter2_id = $checked->salesMasterProcess->setter2_id;

            // check return sales date
            if ($dateCancelled) {
                if ($checked->salesMasterProcess->mark_account_status_id != 1 || $checked->salesMasterProcess->mark_account_status_id != 6) {
                    // 'Have any payments already been issued? ';
                    $check_commission_m1 = UserCommission::where('pid', $checked->pid)->where('amount_type', 'm1')->first();
                    $check_commission_m2 = UserCommission::where('pid', $checked->pid)->where('amount_type', 'm2')->first();

                    // $subroutineFive = $this->subroutineFive($checked);

                    if (! empty($check_commission_m1) || ! empty($check_commission_m2)) {
                        // run subroutine 5
                        // Log::info('data_cancelled');
                        $subroutineFive = $this->subroutineFive($checked);
                    } else {
                        // All pending payments or due payments are set to zero.
                        $reconciliationWithholding = UserReconciliationWithholding::where(['pid' => $checked->pid])
                            ->update(['withhold_amount' => '0', 'status' => 'canceled']);

                        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        $saleMasterProcess->closer1_m1 = 0;
                        $saleMasterProcess->closer2_m1 = 0;
                        $saleMasterProcess->setter1_m1 = 0;
                        $saleMasterProcess->setter2_m1 = 0;
                        $saleMasterProcess->closer1_m2 = 0;
                        $saleMasterProcess->closer2_m2 = 0;
                        $saleMasterProcess->setter1_m2 = 0;
                        $saleMasterProcess->setter2_m2 = 0;
                        $saleMasterProcess->mark_account_status_id = 6;
                        $saleMasterProcess->save();

                    }

                }

            } else {
                $subRoutine = $this->subroutineOne($checked);
                if ($subRoutine == false) {
                    continue;
                }
                // check Is there an M1 Date?
                if ($m1_date) {

                    // check Has M1 already been paid? and check for calculated.
                    $check_commission_m1 = UserCommission::where('pid', $checked->pid)->where('status', '1')->where('amount_type', 'm1')->first();
                    // check Has M1 already been paid? or calculated
                    if (empty($check_commission_m1)) {
                        // Log::info('not empty check_commission_m1');
                        // check  Is there an M2 Date?
                        if ($m2_date != null) {
                            // Run Subroutine 6 (Redline) commented due to no use.
                            // $subroutineSix = $this->subroutineSix($checked);

                            // Run Subroutine #8 (Total Commission) [override]
                            $check_override = UserOverrides::where('pid', $checked->pid)->first();
                            if (empty($check_override)) {
                                $subroutineEight = $this->SubroutineEight($checked);
                            } else {
                                $subroutineEight['closer_commission'] = 0;
                                $subroutineEight['setter_commission'] = 0;
                            }

                            $check_commission_m2 = UserCommission::where('pid', $checked->pid)->where('status', '1')->where('amount_type', 'm2')->first();
                            // Has M2 already been paid?
                            if (! empty($check_commission_m2)) { // && empty($check_override)
                                // Log::info('not empty check_commission_m1 , not empty check_commission_m2 '.$checked->pid);
                                // Does total paid match total from Subroutine #8?
                                $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                                $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                                if (round($totalPaid) != round($pullTotalCommission)) {
                                    // Run Subroutine #12 (Sale Adjustments)
                                    $subroutineTwelve = $this->SubroutineTwelve($checked);
                                }
                            } else {
                                // Log::info('not empty check_commission_m1 , empty check_commission_m2 '.$checked->pid);
                                if (isset($setter1_id) && $setter1_id != '') {
                                    // Log::info('setter1_id');
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');
                                    // echo $closer->id;die;

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);

                                    }
                                }

                                if (isset($setter2_id) && $setter2_id != '') {
                                    // Log::info('setter2_id');
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {
                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);

                                    }
                                }

                            }
                        } else {
                            // No Further Action Required
                        }
                    } else {
                        // Log::info('empty check_commission_m1');
                        // Run Subroutine 1
                        $subRoutine = $this->subroutineOne($checked);
                        if ($subRoutine == false) {
                            continue;
                        }
                        if ($m2_date != null) {

                            // Run Subroutine #8 (Total Commission)
                            $check_override = UserOverrides::where('pid', $checked->pid)->first();
                            if (empty($check_override)) {
                                $subroutineEight = $this->SubroutineEight($checked);
                            } else {
                                $subroutineEight['closer_commission'] = 0;
                                $subroutineEight['setter_commission'] = 0;
                            }

                            $check_commission_m2 = UserCommission::where('pid', $checked->pid)->where('status', '1')->where('amount_type', 'm2')->first();

                            // Has M2 already been paid?
                            if (empty($check_commission_m2)) { // && empty($check_override)
                                // Log::info('empty check_commission_m1 , not empty check_commission_m2'.$checked->pid);
                                // Does total paid match total from Subroutine #8?
                                $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                                $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                                if (round($totalPaid) != round($pullTotalCommission)) {
                                    // Run Subroutine #12 (Sale Adjustments)
                                    $subroutineTwelve = $this->SubroutineTwelve($checked);
                                }

                            } else {
                                // Log::info('empty check_commission_m1 , empty check_commission_m2'.$checked->pid);

                                if (isset($closer1_id) && $closer1_id != '') {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');
                                    // echo $closer->id;die;

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);

                                    }
                                }

                                if (isset($closer2_id) && $closer2_id != '') {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');
                                    // echo $closer->id;die;

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                    }
                                }

                            }

                        } else {
                            // Run Subroutine #3 (M1 Payment)
                            $subroutineThree = $this->subroutineThree($checked);

                            // No Further Action Required

                        }

                    }

                } else {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    if (isset($UpdateData) && $UpdateData != '') {
                        $UpdateData->mark_account_status_id = 2;
                        $UpdateData->save();
                    }
                }
            }
        }
    }

    public function subroutine_code_api_excel()
    {
        $newData = SalesMaster::with('salesMasterProcess')->orderBy('id', 'asc')->get();
        // $newData = SalesMaster::with('salesMasterProcess')->whereIn('pid', ['LIS28646'])->orderBy('id','asc')->get();
        // Is there a clawback date = dateCancelled ?
        foreach ($newData as $checked) {
            $dateCancelled = $checked->date_cancelled;
            $returnSalesDate = $checked->return_sales_date;
            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            $epc = $checked->epc;
            $netEpc = $checked->net_epc;
            $approvedDate = $checked->customer_signoff;
            $customerState = $checked->customer_state;
            $kw = $checked->kw;

            $m1_paid_status = $checked->salesMasterProcess->setter1_m1_paid_status;
            $m2_paid_status = $checked->salesMasterProcess->setter1_m2_paid_status;

            $closer1_id = $checked->salesMasterProcess->closer1_id;
            $closer2_id = $checked->salesMasterProcess->closer2_id;
            $setter1_id = $checked->salesMasterProcess->setter1_id;
            $setter2_id = $checked->salesMasterProcess->setter2_id;

            // check return sales date
            if ($dateCancelled || $returnSalesDate) {
                $dateCancelled = isset($dateCancelled) ? $dateCancelled : $returnSalesDate;
                $clawbackSettlement = ClawbackSettlement::where('pid', $checked->pid)->where('status', '3')->first();
                if ($clawbackSettlement) {
                    continue;
                }

                if ($checked->salesMasterProcess->mark_account_status_id == 1 || $checked->salesMasterProcess->mark_account_status_id == 6) {
                    // 'No clawback calculations required ';
                } else {

                    $check_commission_m1 = UserCommission::where('pid', $checked->pid)->where('amount_type', 'm1')->first();
                    $check_commission_m2 = UserCommission::where('pid', $checked->pid)->where('amount_type', 'm2')->first();

                    // $subroutineFive = $this->subroutineFive($checked);

                    if (! empty($check_commission_m1) || ! empty($check_commission_m2)) {
                        // run subroutine 5
                        $subroutineFive = $this->subroutineFive($checked);
                    } else {
                        // All pending payments or due payments are set to zero.
                        $reconciliationWithholding = UserReconciliationWithholding::where(['pid' => $checked->pid])
                            ->update(['withhold_amount' => '0', 'status' => 'canceled']);

                        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        $saleMasterProcess->closer1_m1 = 0;
                        $saleMasterProcess->closer2_m1 = 0;
                        $saleMasterProcess->setter1_m1 = 0;
                        $saleMasterProcess->setter2_m1 = 0;
                        $saleMasterProcess->closer1_m2 = 0;
                        $saleMasterProcess->closer2_m2 = 0;
                        $saleMasterProcess->setter1_m2 = 0;
                        $saleMasterProcess->setter2_m2 = 0;
                        $saleMasterProcess->mark_account_status_id = 6;
                        $saleMasterProcess->save();

                    }
                }

            } else {
                $checkCommission = UserCommission::where('pid', $checked->pid)->where('status', '3')->where('amount_type', 'm2')->first();
                if ($checkCommission) {
                    continue;
                }

                $legacyApiNullData = LegacyApiNullData::where('pid', $checked->pid)->where(['type' => 'Payroll', 'status' => 'Resolved'])->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
                if ($legacyApiNullData) {
                    continue;
                }

                $subRoutine = $this->subroutineOne($checked);
                if ($subRoutine == false) {
                    continue;
                }
                // check Is there an M1 Date?
                if ($m1_date) {

                    // check Has M1 already been paid? and check for calculated.
                    $check_commission_m1 = UserCommission::where('pid', $checked->pid)->where('status', '3')->where('amount_type', 'm1')->first();
                    // check Has M1 already been paid? or calculated
                    if (! empty($check_commission_m1)) {

                        // check  Is there an M2 Date?
                        if ($m2_date != null) {

                            // Run Subroutine #8 (Total Commission) [override]
                            // $check_override = UserOverrides::where('pid',$checked->pid)->first();
                            $check_commission_m2 = UserCommission::where('pid', $checked->pid)->where('status', '3')->where('amount_type', 'm2')->first();
                            if (empty($check_commission_m2)) {
                                $subroutineEight = $this->SubroutineEight($checked);
                            } else {
                                $subroutineEight['closer_commission'] = 0;
                                $subroutineEight['setter_commission'] = 0;
                            }

                            // Has M2 already been paid?
                            if (! empty($check_commission_m2)) { // && empty($check_override)
                                // Log::info('I m here 1 '.$checked->pid);
                                // Does total paid match total from Subroutine #8?
                                $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                                $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                                if (round($totalPaid) != round($pullTotalCommission)) {
                                    // Run Subroutine #12 (Sale Adjustments)
                                    $subroutineTwelve = $this->SubroutineTwelve($checked);
                                }
                            } else {
                                // Log::info('I m here else 1 '.$checked->pid);

                                if (isset($setter1_id) && $setter1_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {
                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($setter2_id) && $setter2_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {
                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($closer1_id) && $closer1_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($closer2_id) && $closer2_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                            }
                        } else {
                            // No Further Action Required
                        }
                    } else {
                        // Run Subroutine 1
                        $subRoutine = $this->subroutineOne($checked);
                        if ($subRoutine == false) {
                            continue;
                        }
                        if ($m2_date != null) {

                            // Run Subroutine #8 (Total Commission)
                            // $check_override = UserOverrides::where('pid',$checked->pid)->first();
                            $check_commission_m2 = UserCommission::where('pid', $checked->pid)->where('status', '3')->where('amount_type', 'm2')->first();
                            if (empty($check_commission_m2)) {
                                $subroutineEight = $this->SubroutineEight($checked);
                            } else {
                                $subroutineEight['closer_commission'] = 0;
                                $subroutineEight['setter_commission'] = 0;
                            }

                            // Has M2 already been paid?
                            if (! empty($check_commission_m2)) { // && empty($check_override)
                                // Log::info('I m here 2'.$checked->pid);
                                // Does total paid match total from Subroutine #8?
                                $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                                $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                                if (round($totalPaid) != round($pullTotalCommission)) {
                                    // Run Subroutine #12 (Sale Adjustments)
                                    $subroutineTwelve = $this->SubroutineTwelve($checked);
                                }

                            } else {
                                // Log::info('I m here else 2' . $checked->pid);

                                if (isset($setter1_id) && $setter1_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {
                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($setter2_id) && $setter2_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {
                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($closer1_id) && $closer1_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($closer2_id) && $closer2_id != null) {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                        $subroutineNine = $this->subroutineNine($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                            }

                        } else {

                            // Run Subroutine #3 (M1 Payment)
                            $commission_m1 = UserCommission::where('pid', $checked->pid)->where('status', '3')->where('amount_type', 'm1')->first();
                            if (empty($commission_m1)) {
                                $subroutineThree = $this->subroutineThree($checked);
                            }

                            // No Further Action Required

                        }

                    }

                } else {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    if (isset($UpdateData) && $UpdateData != '') {
                        $UpdateData->mark_account_status_id = 2;
                        $UpdateData->save();
                    }
                }

                $legacyApiRawDataHistory = LegacyApiRawDataHistory::where('pid', $checked->pid)->where('date_cancelled', '!=', null)->orderBy('id', 'desc')->first();
                if ($legacyApiRawDataHistory && empty($dateCancelled)) {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->update(['mark_account_status_id' => 3]);
                    // $clawbackSettlement = ClawbackSettlement::where('pid',$checked->pid)->delete();
                }

            }

            $alertCenter = $this->closedPayrollData($checked->pid);

        }
    }

    public function subroutineThree($val)
    {
        $closerId = $val->salesMasterProcess->closer1_id;
        $closer2Id = $val->salesMasterProcess->closer2_id;
        $setterId = $val->salesMasterProcess->setter1_id;
        $setter2Id = $val->salesMasterProcess->setter2_id;
        $m1date = $val->m1_date;
        $customer_signoff = $val->customer_signoff;
        $kw = $val->kw;

        if ($closerId != null && $closer2Id != null) {

            $closer = User::where('id', $closerId)->first();
            $positionId = $closer->sub_position_id;
            $closerUpfront = PositionCommissionUpfronts::where('position_id', $positionId)->where('upfront_status', 1)->first();
            if ($closer->self_gen_accounts == 1 && $closer->self_gen_type == 2) {
                $upfrontAmount = $closer->self_gen_upfront_amount;
                $upfrontType = $closer->self_gen_upfront_type;
                $redline = $closer->self_gen_redline;
            } else {
                $upfrontAmount = $closer->upfront_pay_amount;
                $upfrontType = $closer->upfront_sale_type;
                $redline = $closer->redline;

                $upfrontHistory = UserUpfrontHistory::where('user_id', $closerId)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                if ($upfrontHistory) {
                    $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                    $upfrontType = $upfrontHistory->upfront_sale_type;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            $position2Id = $closer2->sub_position_id;
            $closer2Upfront = PositionCommissionUpfronts::where('position_id', $position2Id)->where('upfront_status', 1)->first();
            if ($closer2->self_gen_accounts == 1 && $closer2->self_gen_type == 2) {
                $upfrontAmount2 = $closer2->self_gen_upfront_amount;
                $upfrontType2 = $closer2->self_gen_upfront_type;
                $redline2 = $closer2->self_gen_redline;
            } else {
                $upfrontAmount2 = $closer2->upfront_pay_amount;
                $upfrontType2 = $closer2->upfront_sale_type;
                $redline2 = $closer2->redline;

                $upfront2History = UserUpfrontHistory::where('user_id', $closer2Id)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                if ($upfront2History) {
                    $upfrontAmount2 = $upfront2History->upfront_pay_amount;
                    $upfrontType2 = $upfront2History->upfront_sale_type;
                }
            }

            if (! empty($closerUpfront) && ! empty($upfrontAmount)) {
                if ($closer2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } else {
                        $amount = (($upfrontAmount * $kw) / 2);
                    }
                } else {
                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    } else {
                        $amount = ($upfrontAmount * $kw);
                    }
                }

                if (! empty($closerUpfront->upfront_limit)) {
                    if ($amount > $closerUpfront->upfront_limit) {
                        $amount = $closerUpfront->upfront_limit;
                    } else {
                        $amount = $amount;
                    }
                }

                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->closer1_m1 = $amount;
                $UpdateData->closer1_m1_paid_status = 4;
                $UpdateData->save();

                $payFrequency = $this->payFrequency($m1date, $closer->sub_position_id, $closerId);
                $userCommission = UserCommission::where('user_id', $closerId)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                $data = [
                    'user_id' => $closerId,
                    'position_id' => $closer->position_id,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                    'redline' => $redline,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'status' => 1,
                ];
                if (isset($userCommission) && ! empty($userCommission)) {
                    $update = UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    $create = UserCommission::create($data);
                }

                $this->updateCommission($closerId, $positionId, $amount, $m1date);

            }

            if (! empty($closer2Upfront) && ! empty($upfrontAmount2)) {
                if ($closerUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } else {
                        $amount2 = (($upfrontAmount2 * $kw) / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    } else {
                        $amount2 = ($upfrontAmount2 * $kw);
                    }
                }

                if (! empty($closer2Upfront->upfront_limit)) {
                    if ($amount2 > $closer2Upfront->upfront_limit) {
                        $amount2 = $closer2Upfront->upfront_limit;
                    } else {
                        $amount2 = $amount2;
                    }
                }

                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->closer2_m1 = $amount2;
                $UpdateData->closer2_m1_paid_status = 4;
                $UpdateData->save();

                $payFrequency = $this->payFrequency($m1date, $closer2->sub_position_id, $closer2Id);
                $userCommission = UserCommission::where('user_id', $closer2Id)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                $data = [
                    'user_id' => $closer2Id,
                    'position_id' => $closer2->position_id,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                    'redline' => $redline2,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'status' => 1,
                ];
                if (isset($userCommission) && ! empty($userCommission)) {
                    $update = UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    $create = UserCommission::create($data);
                }

                $this->updateCommission($closer2Id, $position2Id, $amount2, $m1date);
            }

        } elseif ($closerId) {

            $closer = User::where('id', $closerId)->first();
            if ($closerId == $setterId && ! empty($closer->self_gen_accounts)) {

                $positionId = $closer->sub_position_id;
                $closerUpfront = PositionCommissionUpfronts::where('position_id', $positionId)->where('upfront_status', 1)->first();

                $upfrontAmount = $closer->upfront_pay_amount;
                $upfrontType = $closer->upfront_sale_type;
                $redline = $closer->redline;

                $upfrontHistory = UserUpfrontHistory::where('user_id', $closerId)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                if ($upfrontHistory) {
                    $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                    $upfrontType = $upfrontHistory->upfront_sale_type;
                }

                if ($upfrontType == 'per sale') {
                    $amount1 = $upfrontAmount;
                } else {
                    $amount1 = ($upfrontAmount * $kw);
                }

                $selfupfrontAmount = $closer->self_gen_upfront_amount;
                $selfupfrontType = $closer->self_gen_upfront_type;
                $selfredline = $closer->self_gen_redline;
                if ($selfupfrontType == 'per sale') {
                    $amount2 = $selfupfrontAmount;
                } else {
                    $amount2 = ($selfupfrontAmount * $kw);
                }

                if ($amount1 > $amount2) {
                    $amount = $amount1;
                } else {
                    $amount = $amount2;
                }

                if (! empty($closerUpfront->upfront_limit)) {
                    if ($amount > $closerUpfront->upfront_limit) {
                        $amount = $closerUpfront->upfront_limit;
                    } else {
                        $amount = $amount;
                    }
                }

                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->closer1_m1 = $amount;
                $UpdateData->closer1_m1_paid_status = 4;
                $UpdateData->save();
                // echo $closer->sub_position_id

                $userCommission = UserCommission::where('user_id', $closerId)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                $payFrequency = $this->payFrequency($m1date, $closer->sub_position_id, $closerId);
                $data = [
                    'user_id' => $closerId,
                    'position_id' => $closer->position_id,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                    'redline' => $redline,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'status' => 1,
                ];
                if (isset($userCommission) && ! empty($userCommission)) {
                    $update = UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    $create = UserCommission::create($data);
                }

                $this->updateCommission($closerId, $positionId, $amount, $m1date);

            } else {

                $positionId = $closer->sub_position_id;
                $closerUpfront = PositionCommissionUpfronts::where('position_id', $positionId)->where('upfront_status', 1)->first();
                $upfrontHistory = UserUpfrontHistory::where('user_id', $closerId)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                if ($closer->self_gen_accounts == 1) {
                    if ($closer->position_id == 2) {
                        $upfrontAmount = $closer->upfront_pay_amount;
                        $upfrontType = $closer->upfront_sale_type;
                        $redline = $closer->redline;

                        if ($upfrontHistory) {
                            $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                            $upfrontType = $upfrontHistory->upfront_sale_type;
                        }
                    } else {
                        $upfrontAmount = $closer->self_gen_upfront_amount;
                        $upfrontType = $closer->self_gen_upfront_type;
                        $redline = $closer->self_gen_redline;
                    }

                } else {
                    $upfrontAmount = $closer->upfront_pay_amount;
                    $upfrontType = $closer->upfront_sale_type;
                    $redline = $closer->redline;

                    if ($upfrontHistory) {
                        $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                        $upfrontType = $upfrontHistory->upfront_sale_type;
                    }
                }

                if (! empty($closerUpfront) && ! empty($upfrontAmount)) {

                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    } else {
                        $amount = ($upfrontAmount * $kw);
                    }

                    if (! empty($closerUpfront->upfront_limit)) {
                        if ($amount > $closerUpfront->upfront_limit) {
                            $amount = $closerUpfront->upfront_limit;
                        } else {
                            $amount = $amount;
                        }
                    }
                    // echo $amount;die;
                    $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                    $UpdateData->closer1_m1 = $amount;
                    $UpdateData->closer1_m1_paid_status = 4;
                    $UpdateData->save();
                    // echo $closer->sub_position_id

                    $userCommission = UserCommission::where('user_id', $closerId)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                    $payFrequency = $this->payFrequency($m1date, $closer->sub_position_id, $closerId);
                    $data = [
                        'user_id' => $closerId,
                        'position_id' => $closer->position_id,
                        'pid' => $val->pid,
                        'amount_type' => 'm1',
                        'amount' => $amount,
                        'redline' => $redline,
                        'date' => $m1date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $customer_signoff,
                        'status' => 1,
                    ];
                    if (isset($userCommission) && ! empty($userCommission)) {
                        $update = UserCommission::where('id', $userCommission->id)->update($data);
                    } else {
                        $create = UserCommission::create($data);
                    }

                    $this->updateCommission($closerId, $positionId, $amount, $m1date);
                }
            }

        }

        if ($setterId != null && $setter2Id != null) {

            $setter = User::where('id', $setterId)->first();
            if ($setter) {
                $positionId = $setter->sub_position_id;
                $setterUpfront = PositionCommissionUpfronts::where('position_id', $positionId)->where('upfront_status', 1)->first();
                if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                    $upfrontAmount = $setter->self_gen_upfront_amount;
                    $upfrontType = $setter->self_gen_upfront_type;
                    $redline = $setter->self_gen_redline;
                } else {
                    $upfrontAmount = $setter->upfront_pay_amount;
                    $upfrontType = $setter->upfront_sale_type;
                    $redline = $setter->redline;

                    $upfrontHistory = UserUpfrontHistory::where('user_id', $setterId)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    if ($upfrontHistory) {
                        $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                        $upfrontType = $upfrontHistory->upfront_sale_type;
                    }

                }
            }

            $setter2 = User::where('id', $setter2Id)->first();
            if ($setter2) {
                $position2Id = $setter2->sub_position_id;
                $setter2Upfront = PositionCommissionUpfronts::where('position_id', $position2Id)->where('upfront_status', 1)->first();
                if ($setter2->self_gen_accounts == 1 && $setter2->self_gen_type == 3) {
                    $upfrontAmount2 = $setter2->self_gen_upfront_amount;
                    $upfrontType2 = $setter2->self_gen_upfront_type;
                    $redline2 = $setter2->self_gen_redline;
                } else {
                    $upfrontAmount2 = $setter2->upfront_pay_amount;
                    $upfrontType2 = $setter2->upfront_sale_type;
                    $redline2 = $setter2->redline;

                    $upfront2History = UserUpfrontHistory::where('user_id', $setter2Id)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    if ($upfront2History) {
                        $upfrontAmount2 = $upfront2History->upfront_pay_amount;
                        $upfrontType2 = $upfront2History->upfront_sale_type;
                    }
                }
            }

            if (! empty($setterUpfront) && ! empty($upfrontAmount)) {
                if ($setter2Upfront) {
                    if ($upfrontType == 'per sale') {
                        $amount = ($upfrontAmount / 2);
                    } else {
                        $amount = (($upfrontAmount * $kw) / 2);
                    }
                } else {

                    if ($upfrontType == 'per sale') {
                        $amount = $upfrontAmount;
                    } else {
                        $amount = ($upfrontAmount * $kw);
                    }

                }

                if (! empty($setterUpfront->upfront_limit)) {
                    if ($amount > $setterUpfront->upfront_limit) {
                        $amount = $setterUpfront->upfront_limit;
                    } else {
                        $amount = $amount;
                    }
                }

                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->setter1_m1 = $amount;
                $UpdateData->setter1_m1_paid_status = 4;
                $UpdateData->save();

                $payFrequency = $this->payFrequency($m1date, $setter->sub_position_id, $setterId);
                $userCommission = UserCommission::where('user_id', $setterId)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                $data = [
                    'user_id' => $setterId,
                    'position_id' => $setter->position_id,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount,
                    'redline' => $redline,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'status' => 1,
                ];
                if (isset($userCommission) && ! empty($userCommission)) {
                    $update = UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    $create = UserCommission::create($data);
                }

                $this->updateCommission($setterId, $positionId, $amount, $m1date);
            }

            if (! empty($setter2Upfront) && ! empty($upfrontAmount2)) {

                if ($setterUpfront) {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = ($upfrontAmount2 / 2);
                    } else {
                        $amount2 = (($upfrontAmount2 * $kw) / 2);
                    }
                } else {
                    if ($upfrontType2 == 'per sale') {
                        $amount2 = $upfrontAmount2;
                    } else {
                        $amount2 = ($upfrontAmount2 * $kw);
                    }
                }

                if (! empty($setter2Upfront->upfront_limit)) {
                    if ($amount2 > $setter2Upfront->upfront_limit) {
                        $amount2 = $setter2Upfront->upfront_limit;
                    } else {
                        $amount2 = $amount2;
                    }
                }

                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->setter2_m1 = $amount2;
                $UpdateData->setter2_m1_paid_status = 4;
                $UpdateData->save();

                $payFrequency = $this->payFrequency($m1date, $setter2->sub_position_id, $setter2Id);
                $userCommission = UserCommission::where('user_id', $setter2Id)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                $data = [
                    'user_id' => $setter2Id,
                    'position_id' => $setter2->position_id,
                    'pid' => $val->pid,
                    'amount_type' => 'm1',
                    'amount' => $amount2,
                    'redline' => $redline2,
                    'date' => $m1date,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'customer_signoff' => $customer_signoff,
                    'status' => 1,
                ];
                if (isset($userCommission) && ! empty($userCommission)) {
                    $update = UserCommission::where('id', $userCommission->id)->update($data);
                } else {
                    $create = UserCommission::create($data);
                }

                $this->updateCommission($setter2Id, $position2Id, $amount2, $m1date);
            }

        } elseif ($setterId) {

            $setter = User::where('id', $setterId)->first();
            if ($setter && $setterId != $closerId) {
                $positionId = $setter->sub_position_id;
                $setterUpfront = PositionCommissionUpfronts::where('position_id', $positionId)->where('upfront_status', 1)->first();
                if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                    $upfrontAmount = $setter->self_gen_upfront_amount;
                    $upfrontType = $setter->self_gen_upfront_type;
                    $redline = $setter->self_gen_redline;
                } else {
                    $upfrontAmount = $setter->upfront_pay_amount;
                    $upfrontType = $setter->upfront_sale_type;
                    $redline = $setter->redline;

                    $upfrontHistory = UserUpfrontHistory::where('user_id', $setterId)->where('upfront_effective_date', '<=', $customer_signoff)->orderBy('upfront_effective_date', 'DESC')->first();
                    if ($upfrontHistory) {
                        $upfrontAmount = $upfrontHistory->upfront_pay_amount;
                        $upfrontType = $upfrontHistory->upfront_sale_type;
                    }
                }

                if ($upfrontType == 'per sale') {
                    $amount = $upfrontAmount;
                } else {
                    $amount = ($upfrontAmount * $kw);
                }

                if (! empty($setterUpfront) && ! empty($upfrontAmount)) {

                    if (! empty($setterUpfront->upfront_limit)) {
                        if ($amount > $setterUpfront->upfront_limit) {
                            $amount = $setterUpfront->upfront_limit;
                        } else {
                            $amount = $amount;
                        }
                    }

                    $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                    $UpdateData->setter1_m1 = $amount;
                    $UpdateData->setter1_m1_paid_status = 4;
                    $UpdateData->save();

                    $userCommission = UserCommission::where('user_id', $setterId)->where('pid', $val->pid)->where('amount_type', 'm1')->where('status', '<>', 3)->first();
                    $payFrequency = $this->payFrequency($m1date, $setter->sub_position_id, $setterId);
                    $data = [
                        'user_id' => $setterId,
                        'position_id' => $positionId,
                        'pid' => $val->pid,
                        'amount_type' => 'm1',
                        'amount' => $amount,
                        'redline' => $redline,
                        'date' => $m1date,
                        'pay_period_from' => $payFrequency->pay_period_from,
                        'pay_period_to' => $payFrequency->pay_period_to,
                        'customer_signoff' => $customer_signoff,
                        'status' => 1,
                    ];
                    if (isset($userCommission) && ! empty($userCommission)) {
                        $update = UserCommission::where('id', $userCommission->id)->update($data);
                    } else {
                        $create = UserCommission::create($data);
                    }

                    $this->updateCommission($setterId, $positionId, $amount, $m1date);

                }
            }
        }
    }

    public function subroutineFour($checked)
    {
        // echo"subroutineFour";die;
        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();

        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting
        $positionOverrideCloser = PositionOverride::where('position_id', 2)->first();

        if ($positionOverrideCloser->settlement_id == 1) {

            $closer1_id = $saleMasterProcess->closer1_id;
            $closer2_id = $saleMasterProcess->closer2_id;
            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $closer1ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer1_id)->where('status', 'pending')->sum('withhold_amount');

            }
            if ($closer2_id) {

                $closer2ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer2_id)->where('status', 'pending')->sum('withhold_amount');

            }
            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);

        } else {

            // $sattlement_type = "During M2";
            $closer1_id = $saleMasterProcess->closer1_id;
            $closer2_id = $saleMasterProcess->closer2_id;
            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $closer1ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer1_id)->where('status', 'pending')->sum('withhold_amount');

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer1_id', $closer1_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->closer1_m1 = 0;
                $saleMasterProcess->closer1_m2 = 0;
                $saleMasterProcess->save();

            }
            if ($closer2_id) {

                $closer2ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer2_id)->where('status', 'pending')->sum('withhold_amount');

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer2_id', $closer2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->closer2_m1 = 0;
                $saleMasterProcess->closer2_m2 = 0;
                $saleMasterProcess->save();

            }
            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);

        }
        // for setter
        $positionOverrideSettlementSetter = PositionOverride::where('position_id', 3)->first();
        if ($positionOverrideSettlementSetter->settlement_id == 1) {

            $setter1_id = $saleMasterProcess->setter1_id;
            $setter2_id = $saleMasterProcess->setter2_id;

            $setter1ReconciliationWithholding_amount = 0;
            $setter2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($setter1_id) {

                $setter1ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter1_id)->where('status', 'pending')->sum('withhold_amount');

            }
            if ($setter2_id) {

                $setter2ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter2_id)->where('status', 'pending')->sum('withhold_amount');

            }
            $total_setters_ReconciliationWithholding_amount = ($setter1ReconciliationWithholding_amount + $setter2ReconciliationWithholding_amount);

        } else {
            // $sattlement_type = "During M2";
            $setter1_id = $saleMasterProcess->setter1_id;
            $setter2_id = $saleMasterProcess->setter2_id;

            $setter1ReconciliationWithholding_amount = 0;
            $setter2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($setter1_id) {

                $setter1ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter1_id)->where('status', 'pending')->sum('withhold_amount');

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'setter1_id', $setter1_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->setter1_m1 = 0;
                $saleMasterProcess->setter1_m2 = 0;
                $saleMasterProcess->save();

            }
            if ($setter2_id) {

                $setter2ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter2_id)->where('status', 'pending')->sum('withhold_amount');
                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'setter1_id', $setter2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->setter2_m1 = 0;
                $saleMasterProcess->setter2_m2 = 0;
                $saleMasterProcess->save();

            }

            $total_setters_ReconciliationWithholding_amount = ($setter1ReconciliationWithholding_amount + $setter2ReconciliationWithholding_amount);

        }

        $total_m1_m2_amount = $total_closers_ReconciliationWithholding_amount + $total_setters_ReconciliationWithholding_amount;

        $backendSetting = BackendSetting::first();
        if ($backendSetting) {

            $maximum_withheld = $backendSetting->maximum_withheld;
            if ($total_m1_m2_amount <= $maximum_withheld) {
                $total_deduct = $maximum_withheld - $total_m1_m2_amount;
            } else {
                $total_deduct = $maximum_withheld - $total_m1_m2_amount;
            }

            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
            // System adds total of clawback (deduction as a negative amount) to reconciliation
            $saleMasterProcess->mark_account_status_id = 1;
            $saleMasterProcess->save();

            $userReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->first();
            if ($userReconciliationWithholding) {
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $userReconciliationWithholding->status = 'Clawed Back';
                $userReconciliationWithholding->save();

            }

        }

        return 'Data';
    }

    public function subroutineFive($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        if ($closerId && $closer2Id) {
            $closerAry = [$closerId, $closer2Id];
        } else {
            $closerAry = [$closerId];
        }

        if ($setterId && $setter2Id) {
            $setterAry = [$setterId, $setter2Id];
        } else {
            $setterAry = [$setterId];
        }

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $closer1_m1 = $saleMasterProcess->closer1_m1;
        $closer2_m1 = $saleMasterProcess->closer2_m1;
        $closer1_m2 = $saleMasterProcess->closer1_m2;
        $closer2_m2 = $saleMasterProcess->closer2_m2;
        $closer1_commission = $saleMasterProcess->closer1_commission;
        $closer2_commission = $saleMasterProcess->closer2_commission;

        $setter1_m1 = $saleMasterProcess->setter1_m1;
        $setter2_m1 = $saleMasterProcess->setter2_m1;
        $setter1_m2 = $saleMasterProcess->setter1_m2;
        $setter2_m2 = $saleMasterProcess->setter2_m2;
        $setter1_commission = $saleMasterProcess->setter1_commission;
        $setter2_commission = $saleMasterProcess->setter2_commission;

        // $closer1Amount = ($closer1_m1 + $closer1_m2);
        // $closer2Amount = ($closer2_m1 + $closer2_m2);
        // $setter1Amount = ($setter1_m1 + $setter1_m2);
        // $setter2Amount = ($setter2_m1 + $setter2_m2);

        $closer1Withheld_amount = 0;
        $closer2Withheld_amount = 0;
        $setter1Withheld_amount = 0;
        $setter2Withheld_amount = 0;
        // $date = date('Y-m-d');
        $date_cancelled = isset($checked->date_cancelled) ? $checked->date_cancelled : $checked->return_sales_date;
        $date = isset($checked->date_cancelled) ? $checked->date_cancelled : $checked->return_sales_date;
        // $date_cancelled = $checked->date_cancelled;
        // $date = $checked->date_cancelled;
        $pid = $checked->pid;
        $companySetting = CompanySetting::where('type', 'reconciliation')->first();

        if ($closerId != null) {
            UserCommission::where(['user_id' => $closerId, 'pid' => $pid])->where('status', '<>', 3)->update(['amount' => 0]);

            $closer = User::where('id', $closerId)->first();
            $closer1Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
            if ($closer1Withheld) {
                if ($closer1Withheld->status == 'paid') {
                    $closer1Withheld_amount = $closer1Withheld->withhold_amount;
                } else {
                    $closer1Withheld->withhold_amount = 0;
                    $closer1Withheld->status = 'clawdback';
                    $closer1Withheld->save();
                }
            }
            // $this->overide_clawback($checked->pid,$date);
            $positionReconciliation = PositionReconciliations::where(['position_id' => $closer->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($date);
                $pay_period_from = $payFrequency->pay_period_from;
                $pay_period_to = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequency($date, $closer->sub_position_id, $closerId);
                $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            $closer1Amount = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'status' => 3])->sum('amount');
            $clawback = ($closer1Amount + $closer1Withheld_amount);
            if (! empty($clawback)) {

                ClawbackSettlement::create(
                    [
                        'user_id' => $closerId,
                        'position_id' => 2,
                        'pid' => $checked->pid,
                        'clawback_amount' => $clawback,
                        'clawback_type' => $clawbackType,
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                    ]
                );

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($closerId, 2, $clawback, $payFrequency, $pid);
                }

            }

        }

        if ($closer2Id != null) {
            UserCommission::where(['user_id' => $closer2Id, 'pid' => $pid])->where('status', '<>', 3)->update(['amount' => 0]);

            $closer = User::where('id', $closer2Id)->first();
            $closer2Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->first();
            if ($closer2Withheld) {
                if ($closer2Withheld->status == 'paid') {
                    $closer2Withheld_amount = $closer2Withheld->withhold_amount;
                } else {
                    $closer2Withheld->withhold_amount = 0;
                    $closer2Withheld->status = 'clawdback';
                    $closer2Withheld->save();
                }
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $closer->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($date);
                $pay_period_from = $payFrequency->pay_period_from;
                $pay_period_to = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequency($date, $closer->sub_position_id, $closer2Id);
                $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            $closer2Amount = UserCommission::where(['user_id' => $closerId, 'pid' => $pid, 'status' => 3])->sum('amount');
            $clawback = ($closer2Amount + $closer2Withheld_amount);
            if (! empty($clawback)) {

                ClawbackSettlement::create(
                    [
                        'user_id' => $closer2Id,
                        'position_id' => 2,
                        'pid' => $checked->pid,
                        'clawback_amount' => $clawback,
                        'clawback_type' => $clawbackType,
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                    ]
                );

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($closer2Id, 2, $clawback, $payFrequency, $pid);
                }

            }
        }

        if ($setterId != null) {
            UserCommission::where(['user_id' => $setterId, 'pid' => $pid])->where('status', '<>', 3)->update(['amount' => 0]);

            $setter = User::where('id', $setterId)->first();
            $setter1Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
            if ($setter1Withheld) {
                if ($setter1Withheld->status == 'paid') {
                    $setter1Withheld_amount = $setter1Withheld->withhold_amount;
                } else {
                    $setter1Withheld->withhold_amount = 0;
                    $setter1Withheld->status = 'clawdback';
                    $setter1Withheld->save();
                }
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $setter->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($date);
                $pay_period_from = $payFrequency->pay_period_from;
                $pay_period_to = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequency($date, $setter->sub_position_id, $setterId);
                $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            $setter1Amount = UserCommission::where(['user_id' => $setterId, 'pid' => $pid, 'status' => 3])->sum('amount');
            $clawback = ($setter1Amount + $setter1Withheld_amount);
            if (! empty($clawback)) {

                ClawbackSettlement::create(
                    [
                        'user_id' => $setterId,
                        'position_id' => 3,
                        'pid' => $checked->pid,
                        'clawback_amount' => $clawback,
                        'clawback_type' => $clawbackType,
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                    ]
                );

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($setterId, 3, $clawback, $payFrequency, $pid);
                }

            }

        }

        if ($setter2Id != null) {
            UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid])->where('status', '<>', 3)->update(['amount' => 0]);

            $setter = User::where('id', $setter2Id)->first();
            $setter2Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->first();
            if ($setter2Withheld) {
                if ($setter2Withheld->status == 'paid') {
                    $setter2Withheld_amount = $setter2Withheld->withhold_amount;
                } else {
                    $setter2Withheld->withhold_amount = 0;
                    $setter2Withheld->status = 'clawdback';
                    $setter2Withheld->save();
                }
            }

            $positionReconciliation = PositionReconciliations::where(['position_id' => $setter->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
            if ($companySetting->status == '1' && $positionReconciliation) {
                $clawbackType = 'reconciliation';
                $payFrequency = $this->reconciliationPeriod($date);
                $pay_period_from = $payFrequency->pay_period_from;
                $pay_period_to = $payFrequency->pay_period_to;
            } else {
                $clawbackType = 'next payroll';
                $payFrequency = $this->payFrequency($date, $setter->sub_position_id, $setter2Id);
                $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
            }

            $setter2Amount = UserCommission::where(['user_id' => $setter2Id, 'pid' => $pid, 'status' => 3])->sum('amount');
            $clawback = ($setter2Amount + $setter2Withheld_amount);
            if (! empty($clawback)) {

                ClawbackSettlement::create(
                    [
                        'user_id' => $setter2Id,
                        'position_id' => 3,
                        'pid' => $checked->pid,
                        'clawback_amount' => $clawback,
                        'clawback_type' => $clawbackType,
                        'pay_period_from' => $pay_period_from,
                        'pay_period_to' => $pay_period_to,
                    ]
                );

                if ($clawbackType == 'next payroll') {
                    $this->updateClawback($setter2Id, 3, $clawback, $payFrequency, $pid);
                }

            }

        }

        $this->overides_clawback($pid, $date);

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $saleMasterProcess->mark_account_status_id = 1;
        $saleMasterProcess->save();

    }

    public function subroutineSix($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;

        // $customerRedline = $checked->redline;
        $saleState = $checked->customer_state;
        $approvedDate = $checked->customer_signoff;

        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = $generalCode->redline_standard;
            }
        } else {
            // customer state Id..................................................
            $state = State::where('state_code', $saleState)->first();
            $saleStateId = isset($state->id) ? $state->id : 0;
            $location = Locations::where('state_id', $saleStateId)->first();
            $locationId = isset($location->id) ? $location->id : 0;
            $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            } else {
                $saleStandardRedline = isset($location->redline_standard) ? $location->redline_standard : 0;
            }

        }

        if ($approvedDate != null) {
            $data['closer1_redline'] = '0';
            $data['closer2_redline'] = '0';
            $data['setter1_redline'] = '0';
            $data['setter2_redline'] = '0';

            if ($setterId && $setter2Id) {
                // setter1
                $setter = User::where('id', $setterId)->first();
                if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $setter_redline = $setter->self_gen_redline;
                        $redline_amount_type = $setter->self_gen_redline_amount_type;
                    }

                } else {

                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $setter_redline = $setter->redline;
                        $redline_amount_type = $setter->redline_amount_type;
                    }

                }

                $setterOfficeId = $setter->office_id;

                if ($redline_amount_type == 'Fixed') {

                    $data['setter1_redline'] = $setter_redline;

                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $location_id = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $setterStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $setterStateRedline = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redline = $saleStandardRedline + ($setter_redline - $setterStateRedline);
                    $data['setter1_redline'] = $redline;
                }

                // setter2
                $setter2 = User::where('id', $setter2Id)->first();
                if ($setter2->self_gen_accounts == 1 && $setter2->self_gen_type == 3) {
                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter2_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $setter2_redline = $setter2->self_gen_redline;
                        $redline_amount_type = $setter2->self_gen_redline_amount_type;
                    }

                } else {

                    $user2Redlines = UserRedlines::where('user_id', $setter2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($user2Redlines) {
                        $setter2_redline = $user2Redlines->redline;
                        $redline_amount_type = $user2Redlines->redline_amount_type;
                    } else {
                        $setter2_redline = $setter2->redline;
                        $redline_amount_type = $setter2->redline_amount_type;
                    }

                }

                $setter2OfficeId = $setter2->office_id;

                if ($redline_amount_type == 'Fixed') {

                    $data['setter2_redline'] = $setter2_redline;

                } else {
                    $setterLocation = Locations::where('id', $setter2OfficeId)->first();
                    $location_id = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $setter2StateRedline = $locationRedlines->redline_standard;
                    } else {
                        $setter2StateRedline = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redline = $saleStandardRedline + ($setter2_redline - $setter2StateRedline);
                    $data['setter2_redline'] = $redline;
                }

            } elseif ($setterId) {

                $setter = User::where('id', $setterId)->first();
                if ($closerId == $setterId && $setter->self_gen_accounts == 1) {
                    $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $setter_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        if ($setter->self_gen_type == 3) {
                            $setter_redline = $setter->self_gen_redline;
                            $redline_amount_type = $setter->self_gen_redline_amount_type;
                        } else {
                            $setter_redline = $setter->redline;
                            $redline_amount_type = $setter->redline_amount_type;
                        }

                    }

                } else {

                    if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                        $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $setter_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $setter_redline = $setter->self_gen_redline;
                            $redline_amount_type = $setter->self_gen_redline_amount_type;
                        }

                    } else {

                        $userRedlines = UserRedlines::where('user_id', $setterId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $setter_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $setter_redline = $setter->redline;
                            $redline_amount_type = $setter->redline_amount_type;
                        }
                    }

                }

                $setterOfficeId = $setter->office_id;

                if ($redline_amount_type == 'Fixed') {

                    $data['setter1_redline'] = $setter_redline;

                } else {
                    $setterLocation = Locations::where('id', $setterOfficeId)->first();
                    $location_id = isset($setterLocation->id) ? $setterLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $setterStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $setterStateRedline = isset($setterLocation->redline_standard) ? $setterLocation->redline_standard : 0;
                    }

                    $redline = $saleStandardRedline + ($setter_redline - $setterStateRedline);
                    $data['setter1_redline'] = $redline;
                }

            }

            if ($closerId && $closer2Id) {
                // closer1
                $closer1 = User::where('id', $closerId)->first();
                if ($closer1->self_gen_accounts == 1 && $closer1->self_gen_type == 2) {
                    $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $closer1_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $closer1_redline = $closer1->self_gen_redline;
                        $redline_amount_type = $closer1->self_gen_redline_amount_type;
                    }

                } else {
                    $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $closer1_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        $closer1_redline = $closer1->redline;
                        $redline_amount_type = $closer1->redline_amount_type;
                    }
                }
                $closer1OfficeId = $closer1->office_id;

                if ($redline_amount_type == 'Fixed') {

                    $data['closer1_redline'] = $closer1_redline;

                } else {
                    $closerLocation = Locations::where('id', $closer1OfficeId)->first();
                    $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $closerStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                        // $closerStateRedline = $closerLocation->redline_standard;
                    }

                    // closer_redline
                    $redline = $saleStandardRedline + ($closer1_redline - $closerStateRedline);
                    $data['closer1_redline'] = $redline;
                }

                // closer2
                $closer2 = User::where('id', $closer2Id)->first();
                if ($closer2->self_gen_accounts == 1 && $closer2->self_gen_type == 2) {
                    $user2Redlines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($user2Redlines) {
                        $closer2_redline = $user2Redlines->redline;
                        $redline_amount_type = $user2Redlines->redline_amount_type;
                    } else {
                        $closer2_redline = $closer2->self_gen_redline;
                        $redline_amount_type = $closer2->self_gen_redline_amount_type;
                    }

                } else {

                    $user2Redlines = UserRedlines::where('user_id', $closer2Id)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                    if ($user2Redlines) {
                        $closer2_redline = $user2Redlines->redline;
                        $redline_amount_type = $user2Redlines->redline_amount_type;
                    } else {
                        $closer2_redline = $closer2->redline;
                        $redline_amount_type = $closer2->redline_amount_type;
                    }

                }

                $closer2OfficeId = $closer2->office_id;

                if ($redline_amount_type == 'Fixed') {

                    $data['closer2_redline'] = $closer2_redline;

                } else {
                    $closerLocation = Locations::where('id', $closer2OfficeId)->first();
                    $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $closerStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }

                    // closer_redline
                    $redline = $saleStandardRedline + ($closer2_redline - $closerStateRedline);
                    $data['closer2_redline'] = $redline;
                }

            } elseif ($closerId) {

                $closer = User::where('id', $closerId)->first();
                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {

                    $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                    if ($userRedlines) {
                        $closer_redline = $userRedlines->redline;
                        $redline_amount_type = $userRedlines->redline_amount_type;
                    } else {
                        if ($closer->self_gen_type == 2) {
                            $closer_redline = $closer->self_gen_redline;
                            $redline_amount_type = $closer->self_gen_redline_amount_type;
                        } else {
                            $closer_redline = $closer->redline;
                            $redline_amount_type = $closer->redline_amount_type;
                        }

                    }

                } else {

                    if ($closer->self_gen_accounts == 1 && $closer->self_gen_type == 2) {
                        $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 1)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $closer_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $closer_redline = $closer->self_gen_redline;
                            $redline_amount_type = $closer->self_gen_redline_amount_type;
                        }

                    } else {

                        $userRedlines = UserRedlines::where('user_id', $closerId)->where('start_date', '<=', $approvedDate)->where('self_gen_user', 0)->orderBy('start_date', 'DESC')->first();
                        if ($userRedlines) {
                            $closer_redline = $userRedlines->redline;
                            $redline_amount_type = $userRedlines->redline_amount_type;
                        } else {
                            $closer_redline = $closer->redline;
                            $redline_amount_type = $closer->redline_amount_type;
                        }

                    }

                }

                $closerOfficeId = $closer->office_id;

                if ($redline_amount_type == 'Fixed') {

                    $data['closer1_redline'] = $closer_redline;

                } else {
                    $closerLocation = Locations::where('id', $closerOfficeId)->first();
                    $location_id = isset($closerLocation->id) ? $closerLocation->id : 0;
                    $locationRedlines = LocationRedlineHistory::where('location_id', $location_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                    if ($locationRedlines) {
                        $closerStateRedline = $locationRedlines->redline_standard;
                    } else {
                        $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : 0;
                    }

                    // closer_redline
                    $redline = $saleStandardRedline + ($closer_redline - $closerStateRedline);
                    $data['closer1_redline'] = $redline;
                }

                if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                    $redline1 = $data['setter1_redline'];
                    $redline2 = $data['closer1_redline'];
                    if ($redline1 > $redline2) {
                        $data['closer1_redline'] = $redline2;
                    } else {
                        $data['closer1_redline'] = $redline1;
                    }
                }

            }

            // dd($data);
            return $data;
        }

    }

    public function subroutineEight($checked)
    {
        $companyProfile = CompanyProfile::where('id', 1)->first();

        if ($companyProfile->company_type == 'Solar') {
            $commission11 = $this->subroutineEightForSolar($checked);
        } elseif ($companyProfile->company_type == 'Pest') {

            $commission11 = $this->subroutineEightForFlex($checked);
        }

        return $commission11;
    }

    public function subroutineEightForSolar($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;
        $overrideSetting = CompanySetting::where('type', 'overrides')->first();
        // Get Pull user Redlines from subroutineSix
        $redline = $this->subroutineSix($checked);

        // Calculate setter & closer commission
        $setter_commission = 0;
        if ($setterId != null && $setter2Id != null) {

            $setter = User::where('id', $setterId)->first();
            $positionId = $setter->sub_position_id;
            if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                $commission_percentage = $setter->self_gen_commission;
            } else {
                $commission_percentage = $setter->commission;
            }

            $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;

            $setter2_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->setter1_m2_paid_status != 8) {
                $UpdateData->setter1_commission = $setter1_commission;
                $UpdateData->setter2_commission = $setter2_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();

                $this->updateCommission($setterId, $positionId, $setter1_commission, $m2date);
                $this->updateCommission($setter2Id, $positionId, $setter2_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $setterOverride = UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $checked->pid])->delete();
                    $setter2Override = UserOverrides::where(['sale_user_id' => $setter2Id, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($setterId, $checked->pid, $kw, $m2date, $redline['setter1_redline']);
                    $this->UserOverride($setter2Id, $checked->pid, $kw, $m2date, $redline['setter1_redline']);

                }

            }

            $setter_commission = ($setter1_commission + $setter2_commission);
        } elseif ($setterId) {

            $setter = User::where('id', $setterId)->first();
            $positionId = $setter->sub_position_id;
            if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                $commission_percentage = $setter->self_gen_commission;
            } else {
                $commission_percentage = $setter->commission; // percenge
            }

            $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->setter1_m2_paid_status != 8) {
                $UpdateData->setter1_commission = $setter_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();
                $this->updateCommission($setterId, $positionId, $setter_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $setterOverride = UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($setterId, $checked->pid, $kw, $m2date, $redline['setter1_redline']);
                }
            }

        }

        $closer_commission = 0;
        if ($closerId != null && $closer2Id != null) {
            $closer = User::where('id', $closerId)->first();
            $positionId = $closer->sub_position_id;

            $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) - ($setter_commission / 2)) * 0.5;
            $closer2_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) - ($setter_commission / 2)) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->closer1_m2_paid_status == 0) {
                $UpdateData->closer1_commission = $closer1_commission;
                $UpdateData->closer2_commission = $closer2_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();

                $this->updateCommission($closerId, $positionId, $closer1_commission, $m2date);
                $this->updateCommission($closer2Id, $positionId, $closer2_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $closerOverride = UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $checked->pid])->delete();
                    $closer2Override = UserOverrides::where(['sale_user_id' => $closer2Id, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($closerId, $checked->pid, $kw, $m2date, $redline['closer1_redline']);
                    $this->UserOverride($closer2Id, $checked->pid, $kw, $m2date, $redline['closer1_redline']);

                }
            }

            $closer_commission = ($closer1_commission + $closer2_commission);

        } elseif ($closerId) {
            $closer = User::where('id', $closerId)->first();
            $positionId = $closer->sub_position_id;

            $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000) - $setter_commission;
            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->closer1_m2_paid_status != 8) {
                $UpdateData->closer1_commission = $closer_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();
                $this->updateCommission($closerId, $positionId, $closer_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $closerOverride = UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($closerId, $checked->pid, $kw, $m2date, $redline['closer1_redline']);
                }
            }

        }

        // $saleProcess = SaleMasterProcess::where('pid',$checked->pid)->first();
        // $closer_commission = ($saleProcess->closer1_commission + $saleProcess->closer2_commission);

        $commissiondata['closer_commission'] = $closer_commission;
        $commissiondata['setter_commission'] = $setter_commission;

        // dd($commissiondata);
        return $commissiondata;

    }

    public function subroutineEightForFlex($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;
        $approvedDate = $checked->customer_signoff;

        $overrideSetting = CompanySetting::where('type', 'overrides')->first();
        // Get Pull user Redlines from subroutineSix
        $redline = $this->subroutineSix($checked);

        // Calculate setter & closer commission
        $setter_commission = 0;
        if ($setterId != null && $setter2Id != null) {

            $setter = User::where('id', $setterId)->first();
            if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                $commission_percentage = $setter->self_gen_commission;
            } else {
                $commission_percentage = $setter->commission;
                $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                }
            }

            $setter2 = User::where('id', $setter2Id)->first();
            if ($setter2->self_gen_accounts == 1 && $setter2->self_gen_type == 3) {
                $commission_percentage2 = $setter2->self_gen_commission;
            } else {
                $commission_percentage2 = $setter2->commission;
                $commission2History = UserCommissionHistory::where('user_id', $setter2Id)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                }
            }

            $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;

            $setter2_commission = (($netEpc - $redline['setter2_redline']) * $kw * 1000 * $commission_percentage2 / 100) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->setter1_m2_paid_status != 8) {
                $UpdateData->setter1_commission = $setter1_commission;
                $UpdateData->setter2_commission = $setter2_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();

                $this->updateCommission($setterId, 3, $setter1_commission, $m2date);
                $this->updateCommission($setter2Id, 3, $setter2_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $setterOverride = UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $checked->pid])->delete();
                    $setter2Override = UserOverrides::where(['sale_user_id' => $setter2Id, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($setterId, $checked->pid, $kw, $m2date, $redline['setter1_redline']);
                    $this->UserOverride($setter2Id, $checked->pid, $kw, $m2date, $redline['setter2_redline']);

                }

            }

            $setter_commission = ($setter1_commission + $setter2_commission);
        } elseif ($setterId) {
            if ($closerId != $setterId) {
                $setter = User::where('id', $setterId)->first();
                if ($setter->self_gen_accounts == 1 && $setter->self_gen_type == 3) {
                    $commission_percentage = $setter->self_gen_commission;
                } else {
                    $commission_percentage = $setter->commission; // percenge
                    $commissionHistory = UserCommissionHistory::where('user_id', $setterId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                    }
                }

                $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);

                $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                if ($UpdateData->setter1_m2_paid_status != 8) {
                    $UpdateData->setter1_commission = $setter_commission;
                    $UpdateData->mark_account_status_id = 3;
                    $UpdateData->save();
                    $this->updateCommission($setterId, 3, $setter_commission, $m2date);

                    if ($overrideSetting->status == '1') {
                        $setterOverride = UserOverrides::where(['sale_user_id' => $setterId, 'pid' => $checked->pid])->delete();
                        $this->UserOverride($setterId, $checked->pid, $kw, $m2date, $redline['setter1_redline']);
                    }
                }
            }

        }

        $closer_commission = 0;
        if ($closerId != null && $closer2Id != null) {

            $closer = User::where('id', $closerId)->first();
            if ($closer->self_gen_accounts == 1 && $closer->self_gen_type == 2) {
                $commission_percentage = $closer->self_gen_commission;
            } else {
                $commission_percentage = $closer->commission; // percenge
                $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionHistory) {
                    $commission_percentage = $commissionHistory->commission;
                }
            }

            $closer2 = User::where('id', $closer2Id)->first();
            if ($closer2->self_gen_accounts == 1 && $closer2->self_gen_type == 2) {
                $commission_percentage2 = $closer2->self_gen_commission;
            } else {
                $commission_percentage2 = $closer2->commission; // percenge
                $commission2History = UserCommissionHistory::where('user_id', $closer2Id)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commission2History) {
                    $commission_percentage2 = $commission2History->commission;
                }
            }

            $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) * ($commission_percentage / 100)) * 0.5;
            $closer2_commission = ((($netEpc - $redline['closer2_redline']) * $kw * 1000) * ($commission_percentage2 / 100)) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->closer1_m2_paid_status == 0) {
                $UpdateData->closer1_commission = $closer1_commission;
                $UpdateData->closer2_commission = $closer2_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();

                $this->updateCommission($closerId, 2, $closer1_commission, $m2date);
                $this->updateCommission($closer2Id, 2, $closer2_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $closerOverride = UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $checked->pid])->delete();
                    $closer2Override = UserOverrides::where(['sale_user_id' => $closer2Id, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($closerId, $checked->pid, $kw, $m2date, $redline['closer1_redline']);
                    $this->UserOverride($closer2Id, $checked->pid, $kw, $m2date, $redline['closer2_redline']);

                }
            }

            $closer_commission = ($closer1_commission + $closer2_commission);

        } elseif ($closerId) {

            $closer = User::where('id', $closerId)->first();
            if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                $percentage1 = $closer->commission;
                $percentage2 = $closer->self_gen_commission;
                $commission_percentage = 100;

                // if ($percentage1 > $percentage2 ) {
                //     $commission_percentage = $percentage1;
                // }else{
                //     $commission_percentage = $percentage2;
                // }

                // $redline1 = $redline['closer1_redline'];
                // $redline2 = $redline['setter1_redline'];
                // if ($redline1 > $redline2 ) {
                //     $redline['closer1_redline'] = $redline1;
                // }else{
                //     $redline['closer1_redline'] = $redline2;
                // }

            } else {
                if ($closer->self_gen_accounts == 1 && $closer->self_gen_type == 2) {
                    $commission_percentage = $closer->self_gen_commission;
                } else {
                    $commission_percentage = $closer->commission; // percenge
                    $commissionHistory = UserCommissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                    if ($commissionHistory) {
                        $commission_percentage = $commissionHistory->commission;
                    }
                }
            }

            $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000 * $commission_percentage / 100);

            if ($closerId == $setterId && $closer->self_gen_accounts == 1) {
                $commissionSelfgen = UserSelfGenCommmissionHistory::where('user_id', $closerId)->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->first();
                if ($commissionSelfgen) {
                    if ($commissionSelfgen->commission > 0) {
                        $selfgen_percentage = $commissionSelfgen->commission;
                        $closer_commission = ($closer_commission * $selfgen_percentage / 100);
                    }
                }
            }

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->closer1_m2_paid_status != 8) {
                $UpdateData->closer1_commission = $closer_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();
                $this->updateCommission($closerId, 2, $closer_commission, $m2date);

                if ($overrideSetting->status == '1') {
                    $closerOverride = UserOverrides::where(['sale_user_id' => $closerId, 'pid' => $checked->pid])->delete();
                    $this->UserOverride($closerId, $checked->pid, $kw, $m2date, $redline['closer1_redline']);
                }
            }

        }

        if (! empty($closerId)) {
            $this->StackUserOverride($closerId, $checked->pid, $kw, $m2date);
        }
        if (! empty($closer2Id)) {
            $this->StackUserOverride($closer2Id, $checked->pid, $kw, $m2date);
        }

        $commissiondata['closer_commission'] = $closer_commission;
        $commissiondata['setter_commission'] = $setter_commission;

        return $commissiondata;

    }

    public function subroutineNine($checked)
    {
        // $totalCommission = $this->subroutineEight($checked);

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $customer_signoff = $checked->customer_signoff;
        $m2date = $checked->m2_date;

        $payFrequencySetter = $this->payFrequency($m2date, 3, $setterId);
        $payFrequencyCloser = $this->payFrequency($m2date, 2, $closerId);

        $companySetting = CompanySetting::where('type', 'reconciliation')->first();
        if ($companySetting->status == '1') {
            $totalWithholding = $this->subroutineTen($checked);
        }
        $redline = $this->subroutineSix($checked);
        $saleData = SaleMasterProcess::where('pid', $checked->pid)->first();
        if ($setterId != null && $closerId != $setterId) {
            $setter1ReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->sum('withhold_amount');

            $setter1DueM2 = ($saleData->setter1_commission - $saleData->setter1_m1 - $setter1ReconciliationWithholding);

            if ($setter1DueM2 > 0) {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->setter1_m2 = $setter1DueM2;
                $data->setter1_m2_paid_status = 5;
                $data->save();

            } else {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->setter1_m2 = $setter1DueM2;
                $data->setter1_m2_paid_status = 5;
                $data->save();

            }

            $userCommission = UserCommission::where('user_id', $setterId)->where('pid', $checked->pid)->where('amount_type', 'm2')->where('status', '<>', 3)->first();
            $setter = User::where('id', $setterId)->first();
            $payFrequencySetter = $this->payFrequency($m2date, $setter->sub_position_id, $setterId);
            $data = [
                'user_id' => $setterId,
                'position_id' => $setter->position_id,
                'pid' => $checked->pid,
                'amount_type' => 'm2',
                'amount' => $setter1DueM2,
                'redline' => $redline['setter1_redline'],
                'redline_type' => ($setter->redline_amount_type == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                'date' => $m2date,
                'pay_period_from' => $payFrequencySetter->pay_period_from,
                'pay_period_to' => $payFrequencySetter->pay_period_to,
                'customer_signoff' => $customer_signoff,
                'status' => 1,
            ];
            if (isset($userCommission) && ! empty($userCommission)) {
                $update = UserCommission::where('id', $userCommission->id)->update($data);
            } else {
                $create = UserCommission::create($data);
            }

        }
        if ($setter2Id != null) {
            $setter2ReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->sum('withhold_amount');

            $setter2DueM2 = ($saleData->setter2_commission - $saleData->setter2_m1 - $setter2ReconciliationWithholding);

            if ($setter2DueM2 > 0) {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->setter2_m2 = $setter2DueM2;
                $data->setter2_m2_paid_status = 5;
                $data->save();

            } else {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->setter2_m2 = $setter2DueM2;
                $data->setter2_m2_paid_status = 5;
                $data->save();

            }

            $userCommission = UserCommission::where('user_id', $setter2Id)->where('pid', $checked->pid)->where('amount_type', 'm2')->where('status', '<>', 3)->first();
            $setter = User::where('id', $setter2Id)->first();
            $data = [
                'user_id' => $setter2Id,
                'position_id' => $setter->position_id,
                'pid' => $checked->pid,
                'amount_type' => 'm2',
                'amount' => $setter2DueM2,
                'redline' => $redline['setter2_redline'],
                'redline_type' => ($setter->redline_amount_type == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                'date' => $m2date,
                'pay_period_from' => $payFrequencySetter->pay_period_from,
                'pay_period_to' => $payFrequencySetter->pay_period_to,
                'customer_signoff' => $customer_signoff,
                'status' => 1,
            ];
            if (isset($userCommission) && ! empty($userCommission)) {
                $update = UserCommission::where('id', $userCommission->id)->update($data);
            } else {
                $create = UserCommission::create($data);
            }

        }

        if ($closerId != null) {
            $closer1ReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->sum('withhold_amount');

            $closer1DueM2 = ($saleData->closer1_commission - $saleData->closer1_m1 - $closer1ReconciliationWithholding);

            if ($closer1DueM2 > 0) {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->closer1_m2 = $closer1DueM2;
                $data->closer1_m2_paid_status = 5;
                $data->save();

            } else {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->closer1_m2 = $closer1DueM2;
                $data->closer1_m2_paid_status = 5;
                $data->save();

            }

            $userCommission = UserCommission::where('user_id', $closerId)->where('pid', $checked->pid)->where('amount_type', 'm2')->where('status', '<>', 3)->first();
            $closer = User::where('id', $closerId)->first();
            $payFrequencyCloser = $this->payFrequency($m2date, $closer->sub_position_id, $closerId);
            $data = [
                'user_id' => $closerId,
                'position_id' => $closer->position_id,
                'pid' => $checked->pid,
                'amount_type' => 'm2',
                'amount' => $closer1DueM2,
                'redline' => $redline['closer1_redline'],
                'redline_type' => ($closer->redline_amount_type == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                'date' => $m2date,
                'pay_period_from' => $payFrequencyCloser->pay_period_from,
                'pay_period_to' => $payFrequencyCloser->pay_period_to,
                'customer_signoff' => $customer_signoff,
                'status' => 1,
            ];
            if (isset($userCommission) && ! empty($userCommission)) {
                $update = UserCommission::where('id', $userCommission->id)->update($data);
            } else {
                $create = UserCommission::create($data);
            }
        }
        if ($closer2Id != null) {
            $closer2ReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->sum('withhold_amount');

            $closer2DueM2 = ($saleData->closer2_commission - $saleData->closer2_m1 - $closer2ReconciliationWithholding);

            if ($closer2DueM2 > 0) {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->closer2_m2 = $closer2DueM2;
                $data->closer2_m2_paid_status = 5;
                $data->save();

            } else {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->closer2_m2 = $closer2DueM2;
                $data->closer2_m2_paid_status = 5;
                $data->save();

            }

            $userCommission = UserCommission::where('user_id', $closer2Id)->where('pid', $checked->pid)->where('amount_type', 'm2')->where('status', '<>', 3)->first();
            $closer = User::where('id', $closer2Id)->first();
            $payFrequencyCloser = $this->payFrequency($m2date, $closer->sub_position_id, $closer2Id);
            $data = [
                'user_id' => $closer2Id,
                'position_id' => $closer->position_id,
                'pid' => $checked->pid,
                'amount_type' => 'm2',
                'amount' => $closer2DueM2,
                'redline' => $redline['closer2_redline'],
                'redline_type' => ($closer->redline_amount_type == 'Shift Based on Location') ? 'Shift Based on Location' : 'Fixed',
                'date' => $m2date,
                'pay_period_from' => $payFrequencyCloser->pay_period_from,
                'pay_period_to' => $payFrequencyCloser->pay_period_to,
                'customer_signoff' => $customer_signoff,
                'status' => 1,
            ];
            if (isset($userCommission) && ! empty($userCommission)) {
                $update = UserCommission::where('id', $userCommission->id)->update($data);
            } else {
                $create = UserCommission::create($data);
            }

        }

    }

    public function subroutineTen($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $m2date = $checked->m2_date;
        $kw = $checked->kw;
        $approvedDate = $checked->customer_signoff;

        $companySetting = CompanySetting::where('type', 'reconciliation')->first();
        if ($companySetting->status == '1') {
            $payFrequency = $this->reconciliationPeriod($m2date);
            if ($setterId != null && $closerId != $setterId) {
                $user = User::where('id', $setterId)->first();
                $setterWithheldForMax = PositionReconciliations::where('position_id', $user->sub_position_id)->first();
                $setterWithHeldType = $setterWithheldForMax->commission_type;
                $setterWithHeldAmount = $setterWithheldForMax->commission_withheld;
                $setterMaxWithHeldAmount = $setterWithheldForMax->maximum_withheld;

                $userWithheldHistory = UserWithheldHistory::where('user_id', $setterId)->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                if ($userWithheldHistory) {
                    if ($userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                }

                if ($setterWithheldForMax && $setterWithheldForMax->status == 1) {
                    $setterReconciliationWithholdAmount = UserReconciliationWithholding::where('setter_id', $setterId)->sum('withhold_amount');
                    if (! empty($setterMaxWithHeldAmount)) {
                        if ($setterReconciliationWithholdAmount >= $setterMaxWithHeldAmount) {
                            // No withholding calculations required.  System proceeds to following steps
                        } else {

                            // $subroutineEight  =  $this->subroutineEight($checked);
                            // $closerCommission =  $subroutineEight['closer_commission'];
                            // $setterCommission =  $subroutineEight['setter_commission'];

                            if ($setterWithHeldType == 'per kw') {
                                $commissionSettingAmount = $setterWithHeldAmount * $kw;
                            } else {
                                $commissionSettingAmount = $setterWithHeldAmount;
                            }

                            $setterWithheldCheck = ($setterReconciliationWithholdAmount + $commissionSettingAmount);
                            if ($setterWithheldCheck > $setterMaxWithHeldAmount) {
                                $commissionSettingAmount = ($setterMaxWithHeldAmount - $setterReconciliationWithholdAmount);
                            }

                            $setterWithheld = $commissionSettingAmount;

                            // Total is added to reconciliation withholdings and system proceeds to following steps
                            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
                            if (isset($reconData) && $reconData != '') {
                                $reconData->withhold_amount = $setterWithheld;
                                $reconData->save();
                            } else {
                                $data = [
                                    'pid' => $checked->pid,
                                    'setter_id' => $setterId,
                                    'withhold_amount' => $setterWithheld,
                                ];
                                $backendSettings = UserReconciliationWithholding::create($data);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $setterId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
                                    $payReconciliation->save();
                                } else {
                                    UserReconciliationCommission::create(
                                        [
                                            'user_id' => $setterId,
                                            'amount' => $setterWithheld,
                                            'period_from' => $payFrequency->pay_period_from,
                                            'period_to' => $payFrequency->pay_period_to,
                                            'status' => 'pending',
                                        ]
                                    );
                                }

                            }

                        }
                    } else {

                        if ($setterWithHeldType == 'per kw') {
                            $commissionSettingAmount = $setterWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $setterWithHeldAmount;
                        }

                        $setterWithheld = $commissionSettingAmount;
                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $setterWithheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'setter_id' => $setterId,
                                'withhold_amount' => $setterWithheld,
                            ];
                            $backendSettings = UserReconciliationWithholding::create($data);

                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $setterId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $setterWithheld);
                                $payReconciliation->save();
                            } else {
                                UserReconciliationCommission::create(
                                    [
                                        'user_id' => $setterId,
                                        'amount' => $setterWithheld,
                                        'period_from' => $payFrequency->pay_period_from,
                                        'period_to' => $payFrequency->pay_period_to,
                                        'status' => 'pending',
                                    ]
                                );
                            }

                        }
                    }

                }
            }

            if ($setter2Id != null) {
                $user = User::where('id', $setter2Id)->first();
                $setterWithheldForMax = PositionReconciliations::where('position_id', $user->sub_position_id)->first();
                $setterWithHeldType = $setterWithheldForMax->commission_type;
                $setterWithHeldAmount = $setterWithheldForMax->commission_withheld;
                $setterMaxWithHeldAmount = $setterWithheldForMax->maximum_withheld;

                $userWithheldHistory = UserWithheldHistory::where('user_id', $setter2Id)->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                if ($userWithheldHistory) {
                    if ($userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                }

                if ($setterWithheldForMax && $setterWithheldForMax->status == 1) {

                    $setter2ReconciliationWithholdAmount = UserReconciliationWithholding::where('setter_id', $setter2Id)->sum('withhold_amount');
                    if (! empty($setterMaxWithHeldAmount)) {
                        if ($setter2ReconciliationWithholdAmount < $setterMaxWithHeldAmount) {
                            if ($setterWithHeldType == 'per kw') {
                                $commissionSettingAmount = $setterWithHeldAmount * $kw;
                            } else {
                                $commissionSettingAmount = $setterWithHeldAmount;
                            }

                            $setter2WithheldCheck = ($setter2ReconciliationWithholdAmount + $commissionSettingAmount);
                            if ($setter2WithheldCheck > $setterMaxWithHeldAmount) {
                                $commissionSettingAmount = ($setterMaxWithHeldAmount - $setter2ReconciliationWithholdAmount);
                            }

                            $setter2Withheld = $commissionSettingAmount;

                            // Total is added to reconciliation withholdings and system proceeds to following steps

                            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->first();
                            if (isset($reconData) && $reconData != '') {
                                $reconData->withhold_amount = $setter2Withheld;
                                $reconData->save();
                            } else {
                                $data = [
                                    'pid' => $checked->pid,
                                    'setter_id' => $setter2Id,
                                    'withhold_amount' => $setter2Withheld,
                                ];
                                $backendSettings = UserReconciliationWithholding::create($data);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $setter2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $setter2Withheld);
                                    $payReconciliation->save();
                                } else {
                                    UserReconciliationCommission::create(
                                        [
                                            'user_id' => $setter2Id,
                                            'amount' => $setter2Withheld,
                                            'period_from' => $payFrequency->pay_period_from,
                                            'period_to' => $payFrequency->pay_period_to,
                                            'status' => 'pending',
                                        ]
                                    );
                                }

                            }

                        }

                    } else {

                        if ($setterWithHeldType == 'per kw') {
                            $commissionSettingAmount = $setterWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $setterWithHeldAmount;
                        }
                        $setter2Withheld = $commissionSettingAmount;

                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $setter2Withheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'setter_id' => $setter2Id,
                                'withhold_amount' => $setter2Withheld,
                            ];
                            $backendSettings = UserReconciliationWithholding::create($data);

                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $setter2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $setter2Withheld);
                                $payReconciliation->save();
                            } else {
                                UserReconciliationCommission::create(
                                    [
                                        'user_id' => $setter2Id,
                                        'amount' => $setter2Withheld,
                                        'period_from' => $payFrequency->pay_period_from,
                                        'period_to' => $payFrequency->pay_period_to,
                                        'status' => 'pending',
                                    ]
                                );
                            }

                        }

                    }

                }
            }

            if ($closerId != null) {

                $user = User::where('id', $closerId)->first();
                $closerWithheldForMax = PositionReconciliations::where('position_id', $user->sub_position_id)->first();
                $closerWithHeldType = $closerWithheldForMax->commission_type;
                $closerWithHeldAmount = $closerWithheldForMax->commission_withheld;
                $closerMaxWithHeldAmount = $closerWithheldForMax->maximum_withheld;

                $userWithheldHistory = UserWithheldHistory::where('user_id', $closerId)->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                if ($userWithheldHistory) {
                    if ($userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                }

                if ($closerWithheldForMax && $closerWithheldForMax->status == 1) {

                    $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closerId)->sum('withhold_amount');
                    if (! empty($closerMaxWithHeldAmount)) {

                        if ($closerReconciliationWithholdAmount < $closerMaxWithHeldAmount) {
                            if ($closerWithHeldType == 'per kw') {
                                $commissionSettingAmount = $closerWithHeldAmount * $kw;
                            } else {
                                $commissionSettingAmount = $closerWithHeldAmount;
                            }
                            // $commissionSettingAmount = $commissionWithHeldAmountPerWatt*$kw;
                            $closerWithheldCheck = ($closerReconciliationWithholdAmount + $commissionSettingAmount);
                            if ($closerWithheldCheck > $closerMaxWithHeldAmount) {
                                $commissionSettingAmount = ($closerMaxWithHeldAmount - $closerReconciliationWithholdAmount);
                            }

                            $closerWithheld = $commissionSettingAmount;

                            // Total is added to reconciliation withholdings and system proceeds to following steps
                            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
                            if (isset($reconData) && $reconData != '') {
                                $reconData->withhold_amount = $closerWithheld;
                                $reconData->save();
                            } else {
                                $data = [
                                    'pid' => $checked->pid,
                                    'closer_id' => $closerId,
                                    'withhold_amount' => $closerWithheld,
                                ];
                                $backendSettings = UserReconciliationWithholding::create($data);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $closerId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
                                    $payReconciliation->save();
                                } else {
                                    UserReconciliationCommission::create(
                                        [
                                            'user_id' => $closerId,
                                            'amount' => $closerWithheld,
                                            'period_from' => $payFrequency->pay_period_from,
                                            'period_to' => $payFrequency->pay_period_to,
                                            'status' => 'pending',
                                        ]
                                    );
                                }

                            }

                        }

                    } else {

                        if ($closerWithHeldType == 'per kw') {
                            $commissionSettingAmount = $closerWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $closerWithHeldAmount;
                        }
                        $closerWithheld = $commissionSettingAmount;

                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $closerWithheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'closer_id' => $closerId,
                                'withhold_amount' => $closerWithheld,
                            ];
                            $backendSettings = UserReconciliationWithholding::create($data);

                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $closerId, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $closerWithheld);
                                $payReconciliation->save();
                            } else {
                                UserReconciliationCommission::create(
                                    [
                                        'user_id' => $closerId,
                                        'amount' => $closerWithheld,
                                        'period_from' => $payFrequency->pay_period_from,
                                        'period_to' => $payFrequency->pay_period_to,
                                        'status' => 'pending',
                                    ]
                                );
                            }

                        }

                    }

                }

            }

            if ($closer2Id != null) {
                $user = User::where('id', $closer2Id)->first();
                $closerWithheldForMax = PositionReconciliations::where('position_id', $user->sub_position_id)->first();
                $closerWithHeldType = $closerWithheldForMax->commission_type;
                $closerWithHeldAmount = $closerWithheldForMax->commission_withheld;
                $closerMaxWithHeldAmount = $closerWithheldForMax->maximum_withheld;

                $userWithheldHistory = UserWithheldHistory::where('user_id', $closer2Id)->where('withheld_effective_date', '<=', $approvedDate)->orderBy('withheld_effective_date', 'DESC')->first();
                if ($userWithheldHistory) {
                    if ($userWithheldHistory->withheld_amount > 0) {
                        $setterWithHeldType = $userWithheldHistory->withheld_type;
                        $setterWithHeldAmount = $userWithheldHistory->withheld_amount;
                    }
                }

                if ($closerWithheldForMax && $closerWithheldForMax->status == 1) {

                    $closer2ReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closer2Id)->sum('withhold_amount');
                    if (! empty($closerMaxWithHeldAmount)) {
                        if ($closer2ReconciliationWithholdAmount < $closerMaxWithHeldAmount) {
                            if ($closerWithHeldType == 'per kw') {
                                $commissionSettingAmount = $closerWithHeldAmount * $kw;
                            } else {
                                $commissionSettingAmount = $closerWithHeldAmount;
                            }
                            // $commissionSettingAmount = $commissionWithHeldAmountPerWatt*$kw;
                            $closer2WithheldCheck = ($closer2ReconciliationWithholdAmount + $commissionSettingAmount);
                            if ($closer2WithheldCheck > $closerMaxWithHeldAmount) {
                                $commissionSettingAmount = ($closerMaxWithHeldAmount - $closer2ReconciliationWithholdAmount);
                            }

                            $closer2Withheld = $commissionSettingAmount;

                            // Total is added to reconciliation withholdings and system proceeds to following steps
                            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->first();
                            if (isset($reconData) && $reconData != '') {
                                $reconData->withhold_amount = $closer2Withheld;
                                $reconData->save();
                            } else {
                                $data = [
                                    'pid' => $checked->pid,
                                    'closer_id' => $closer2Id,
                                    'withhold_amount' => $closer2Withheld,
                                ];
                                $backendSettings = UserReconciliationWithholding::create($data);

                                $payReconciliation = UserReconciliationCommission::where(['user_id' => $closer2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                                if ($payReconciliation) {
                                    $payReconciliation->amount = ($payReconciliation->amount + $closer2Withheld);
                                    $payReconciliation->save();
                                } else {
                                    UserReconciliationCommission::create(
                                        [
                                            'user_id' => $closer2Id,
                                            'amount' => $closer2Withheld,
                                            'period_from' => $payFrequency->pay_period_from,
                                            'period_to' => $payFrequency->pay_period_to,
                                            'status' => 'pending',
                                        ]
                                    );
                                }

                            }

                        }

                    } else {

                        if ($closerWithHeldType == 'per kw') {
                            $commissionSettingAmount = $closerWithHeldAmount * $kw;
                        } else {
                            $commissionSettingAmount = $closerWithHeldAmount;
                        }
                        $closer2Withheld = $commissionSettingAmount;

                        $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->first();
                        if (isset($reconData) && $reconData != '') {
                            $reconData->withhold_amount = $closer2Withheld;
                            $reconData->save();
                        } else {
                            $data = [
                                'pid' => $checked->pid,
                                'closer_id' => $closer2Id,
                                'withhold_amount' => $closer2Withheld,
                            ];
                            $backendSettings = UserReconciliationWithholding::create($data);

                            $payReconciliation = UserReconciliationCommission::where(['user_id' => $closer2Id, 'period_from' => $payFrequency->pay_period_from, 'period_to' => $payFrequency->pay_period_to, 'status' => 'pending'])->first();
                            if ($payReconciliation) {
                                $payReconciliation->amount = ($payReconciliation->amount + $closer2Withheld);
                                $payReconciliation->save();
                            } else {
                                UserReconciliationCommission::create(
                                    [
                                        'user_id' => $closer2Id,
                                        'amount' => $closer2Withheld,
                                        'period_from' => $payFrequency->pay_period_from,
                                        'period_to' => $payFrequency->pay_period_to,
                                        'status' => 'pending',
                                    ]
                                );
                            }

                        }

                    }

                }
            }

        }

    }

    public function SubroutineTwelve($checked)
    {
        $subroutineEight = $this->subroutineEight($checked);
        $closerCommission = $subroutineEight['closer_commission'];
        $setterCommission = $subroutineEight['setter_commission'];

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        // Calculate difference between 2 previous steps
        $dataSale = SaleMasterProcess::where('pid', $checked->pid)->first();

        if ($closerId != null && $closer2Id != null) {
            if ($closerId != null) {
                $closer1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $closerId])->first();
                $closer1Commission = ($closerCommission / 2);
                $closerValue = ($closer1Commission - $dataSale->closer1_commission);
                // Value is sent to current payroll as DEDUCTION (And annotated as adjustment to this sal)
                $data1 = [
                    'pid' => $checked->pid,
                    'user_id' => $closerId,
                    'position_id' => 2,
                    'amount' => $closerValue,
                    'status' => ($closerValue >= 0) ? 'Positive' : 'Negative',
                ];
                if ($closer1Deduction) {
                    $deduction = ($closerValue - $closer1Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $closer1Deduction->id)->update($data1);
                } else {
                    $deduction = $closerValue;
                    $backendSettings = DeductionAlert::create($data1);
                }
                // $this->updateDeduction($closerId,2,$deduction);

            }

            if ($closer2Id != null) {
                $closer2Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $closer2Id])->first();
                $closer2Commission = ($closerCommission / 2);
                $closer2Value = ($closer2Commission - $dataSale->closer2_commission);
                $data2 = [
                    'pid' => $checked->pid,
                    'user_id' => $closer2Id,
                    'position_id' => 2,
                    'amount' => $closer2Value,
                    'status' => ($closer2Value >= 0) ? 'Positive' : 'Negative',
                ];
                if ($closer2Deduction) {
                    $deduction = ($closer2Value - $closer2Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $closer2Deduction->id)->update($data2);
                } else {
                    $deduction = $closer2Value;
                    $backendSettings = DeductionAlert::create($data2);
                }
                // $this->updateDeduction($closer2Id,2,$deduction);

            }

        } elseif ($closerId) {
            $closer1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $closerId])->first();
            $closer1Commission = $closerCommission;
            $closerValue = ($closer1Commission - $dataSale->closer1_commission);
            // Value is sent to current payroll as DEDUCTION (And annotated as adjustment to this sal)
            $data1 = [
                'pid' => $checked->pid,
                'user_id' => $closerId,
                'position_id' => 2,
                'amount' => $closerValue,
                'status' => ($closerValue >= 0) ? 'Positive' : 'Negative',
            ];
            if ($closer1Deduction) {
                $deduction = ($closerValue - $closer1Deduction->amount);
                $updateDeduction = DeductionAlert::where('id', $closer1Deduction->id)->update($data1);
            } else {
                $deduction = $closerValue;
                $backendSettings = DeductionAlert::create($data1);
            }
            // $this->updateDeduction($closerId,2,$deduction);

        }

        if ($setterId != null && $setter2Id != null) {
            if ($setterId != null) {
                $setter1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $setterId])->first();
                $setter1Commission = ($setterCommission / 2);
                $setterValue = ($setter1Commission - $dataSale->setter1_commission);
                $data3 = [
                    'pid' => $checked->pid,
                    'user_id' => $setterId,
                    'position_id' => 3,
                    'amount' => $setterValue,
                    'status' => ($setterValue >= 0) ? 'Positive' : 'Negative',
                ];
                if ($setter1Deduction) {
                    $deduction = ($setterValue - $setter1Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $setter1Deduction->id)->update($data3);
                } else {
                    $deduction = $setterValue;
                    $backendSettings = DeductionAlert::create($data3);
                }
                // $this->updateDeduction($setterId,3,$deduction);

            }

            if ($setter2Id != null) {
                $setter2Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $setter2Id])->first();
                $setter2Commission = ($setterCommission / 2);
                $setter2Value = ($setter2Commission - $dataSale->setter2_commission);
                $data4 = [
                    'pid' => $checked->pid,
                    'user_id' => $setter2Id,
                    'position_id' => 3,
                    'amount' => $setter2Value,
                    'status' => ($setter2Value >= 0) ? 'Positive' : 'Negative',
                ];
                if ($setter2Deduction) {
                    $deduction = ($setter2Value - $setter2Deduction->amount);
                    $updateDeduction = DeductionAlert::where('id', $setter2Deduction->id)->update($data4);
                } else {
                    $deduction = $setter2Value;
                    $backendSettings = DeductionAlert::create($data4);
                }
                // $this->updateDeduction($setter2Id,3,$deduction);
            }

        } elseif ($setterId) {
            $setter1Deduction = DeductionAlert::where(['pid' => $checked->pid, 'user_id' => $setterId])->first();
            $setter1Commission = $setterCommission;
            $setterValue = ($setter1Commission - $dataSale->setter1_commission);
            $data3 = [
                'pid' => $checked->pid,
                'user_id' => $setterId,
                'position_id' => 3,
                'amount' => $setterValue,
                'status' => ($setterValue >= 0) ? 'Positive' : 'Negative',
            ];
            if ($setter1Deduction) {
                $deduction = ($setterValue - $setter1Deduction->amount);
                $updateDeduction = DeductionAlert::where('id', $setter1Deduction->id)->update($data3);
            } else {
                $deduction = $setterValue;
                $backendSettings = DeductionAlert::create($data3);
            }
            // $this->updateDeduction($setterId,3,$deduction);

        }

    }

    public function overide_clawback($pid, $date)
    {
        $data = UserOverrides::with('userInfo')->where('pid', $pid)->get();

        if (count($data) > 0) {
            $data->transform(function ($data) use ($date, $pid) {

                $positionReconciliation = PositionReconciliations::where(['position_id' => $data->userInfo->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
                if ($positionReconciliation) {
                    $clawbackType = 'reconciliation';
                    $payFrequency = $this->reconciliationPeriod($date);
                    $pay_period_from = $payFrequency->pay_period_from;
                    $pay_period_to = $payFrequency->pay_period_to;
                } else {
                    $clawbackType = 'next payroll';
                    $payFrequency = $this->payFrequency($date, $data->userInfo->sub_position_id, $data->user_id);
                    $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                    $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
                }

                if ($data->status == '3') {
                    $clawbackSettlement = ClawbackSettlement::where(['user_id' => $data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

                    if (empty($clawbackSettlement)) {
                        ClawbackSettlement::create(
                            [
                                'user_id' => $data->user_id,
                                'position_id' => 2,
                                'pid' => $pid,
                                'clawback_amount' => $data->amount,
                                'clawback_type' => $clawbackType,
                                'pay_period_from' => $pay_period_from,
                                'pay_period_to' => $pay_period_to,
                            ]
                        );
                    } else {
                        $clawbackSettlement->clawback_amount += $data->amount;
                        $clawbackSettlement->save();
                    }
                }
            });
        }
    }

    public function overides_clawback($pid,$date)
    {
        $data = UserOverrides::with('userdata')->where('pid',$pid)->get();

        if (count($data) > 0) {

            $data->transform(function ($data) use ($date,$pid) {
                $companySetting = CompanySetting::where('type', 'reconciliation')->first();
                $positionReconciliation = PositionReconciliations::where(['position_id' => $data->userdata->sub_position_id, 'status' => 1, 'clawback_settlement' => 'Reconciliation'])->first();
                if ($companySetting->status == '1' && $positionReconciliation) {
                    $clawbackType = 'reconciliation';
                    $payFrequency = $this->reconciliationPeriod($date);
                    $pay_period_from = $payFrequency->pay_period_from;
                    $pay_period_to = $payFrequency->pay_period_to;
                } else {
                    $clawbackType = 'next payroll';
                    $payFrequency = $this->payFrequency($date, $data->userdata->sub_position_id, $data->user_id);
                    $pay_period_from = isset($payFrequency->next_pay_period_from) ? $payFrequency->next_pay_period_from : null;
                    $pay_period_to = isset($payFrequency->next_pay_period_to) ? $payFrequency->next_pay_period_to : null;
                }

                if ($data->status == '3') {
                    $clawbackSettlement = ClawbackSettlement::where(['pid' => $pid, 'user_id' => $data->user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to, 'type' => 'overrides'])->first();

                    if (empty($clawbackSettlement)) {
                        ClawbackSettlement::create(
                            [
                                'user_id' => $data->user_id,
                                'position_id' => $data->userdata->sub_position_id,
                                'sale_user_id' => $data->sale_user_id,
                                'pid' => $pid,
                                'clawback_amount' => $data->amount,
                                'clawback_type' => $clawbackType,
                                'type' => 'overrides',
                                'pay_period_from' => $pay_period_from,
                                'pay_period_to' => $pay_period_to,
                            ]
                        );
                    } else {
                        $clawbackSettlement->clawback_amount += $data->amount;
                        $clawbackSettlement->save();
                    }

                    if ($clawbackType == 'next payroll') {
                        $this->updateClawback($data->user_id,$data->userdata->sub_position_id,$data->amount,$payFrequency,$pid);
                    }

                } else {

                    UserOverrides::where(['pid' => $pid, 'user_id' => $data->user_id])->update(['amount' => 0]);
                }

            });
        }
    }
}
