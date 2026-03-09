<?php

namespace App\Core\Traits;

use App\Models\AdditionalLocations;
use App\Models\Cities;
use App\Models\Crms;
use App\Models\Department;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\OnboardingEmployeeLocations;
use App\Models\OnboardingEmployees;
use App\Models\Positions;
use App\Models\ProductCode;
use App\Models\State;
use App\Models\User;
use Log;

trait HubspotTrait
{
    public function hubspotSubroutine($data)
    {

        $userData = User::where('employee_id', $data['closer_id']['value'])->first();

        // if(!empty($value->properties->full_name) && !empty($value->properties->full_address)  && !empty($value->properties->postal_code) && !empty($value->properties->contract_sign_date) && !empty($value->properties->system_size__w_) && !empty($value->properties->city) && !empty($value->properties->email)  && !empty($value->properties->state) && !empty($value->properties->dealer_fee_amount) && !empty($value->properties->phone) && !empty($value->properties->net_ppw_calc) && !empty($value->properties->dealer_fee_amount)){

        if ($userData) {
            $data1['pid'] = isset($data['hs_object_id']['value']) ? $data['hs_object_id']['value'] : null;
            $data1['aveyo_hs_id'] = isset($data['hs_object_id']['value']) ? $data['hs_object_id']['value'] : null;
            $data1['install_partner'] = isset($data['install_team']['value']) ? $data['install_team']['value'] : null;
            // $data1['aveyo_project']= isset($data['project']['value'])? $data['project']['value']:null;
            $data1['homeowner_id'] = isset($data['hubspot_owner_id']['value']) ? $data['hubspot_owner_id']['value'] : null;
            $data1['customer_name'] = isset($data['borrower_name']['value']) ? $data['borrower_name']['value'] : null;
            $data1['customer_address'] = isset($data['full_address']['value']) ? $data['full_address']['value'] : null;
            $data1['customer_address_2'] = isset($data['address']['value']) ? $data['address']['value'] : null;
            $data1['customer_city'] = isset($data['city']['value']) ? $data['city']['value'] : null;
            $data1['customer_state'] = isset($data['state']['value']) ? $data['state']['value'] : null;
            $data1['customer_zip'] = isset($data['postal_code']['value']) ? $data['postal_code']['value'] : null;
            $data1['customer_email'] = isset($data['email']['value']) ? $data['email']['value'] : null;
            $data1['customer_phone'] = isset($data['phone']['value']) ? $data['phone']['value'] : null;
            $data1['sales_rep_email'] = isset($data['setter']['value']) ? $data['setter']['value'] : null;
            $data1['m1_date'] = isset($data['m1_com_approved']['value']) ? $data['m1_com_approved']['value'] : null;
            $data1['m2_date'] = isset($data['m2_com_date']['value']) ? $data['m2_com_date']['value'] : null;
            $data1['date_cancelled'] = isset($data['cancelation_date']['value']) ? $data['cancelation_date']['value'] : null;
            $data1['kw'] = isset($data['system_size']['value']) ? $data['system_size']['value'] : null;
            $data1['dealer_fee_percentage'] = isset($data['dealer_fee____']['value']) ? $data['dealer_fee____']['value'] : null;
            $data1['dealer_fee_amount'] = isset($data['dealer_fee_amount']['value']) ? $data['dealer_fee_amount']['value'] : null;
            $data1['adders'] = isset($data['sow_total_adder_cost']['value']) ? $data['sow_total_adder_cost']['value'] : null;
            $data1['adders_description'] = isset($data['adders_description']['value']) ? $data['adders_description']['value'] : null;
            $data1['epc'] = isset($data['gross_ppw']['value']) ? $data['gross_ppw']['value'] : null;
            $data1['net_epc'] = isset($data['net_ppw_calc']['value']) ? $data['net_ppw_calc']['value'] : null;
            $data1['gross_account_value'] = isset($data['total_cost']['value']) ? $data['total_cost']['value'] : null;
            $data1['product'] = isset($data['project_type']['value']) ? $data['project_type']['value'] : null;
            $data1['setter_id'] = isset($data['setter_id']['value']) ? $data['setter_id']['value'] : null;
            $data1['closer_id'] = isset($data['closer_id']['value']) ? $data['closer_id']['value'] : null;
            $data1['closer_name'] = isset($data['closer']['value']) ? $data['closer']['value'] : null;
            $data1['setter_name'] = isset($data['setter']['value']) ? $data['setter']['value'] : null;
            $data1['contract_sign_date'] = isset($data['contract_sign_date']['value']) ? date('Y-m-d', strtotime($data['contract_sign_date']['value'])) : null;
            $data1['email_status'] = 0;

            $checkPid = LegacyApiRowData::where('pid', $data['hs_object_id']['value'])->first();

            if (! empty($checkPid)) {
                $inserted = LegacyApiRowData::where('id', $checkPid->id)->Update($data1);
                $response = hs_create_raw_data_history_api($data);
            } else {
                $inserted = LegacyApiRowData::create($data1);
                $response = hs_create_raw_data_history_api($data);
            }

        } else {

            // Insert null data in table for alert admin...............................................

            $data1['pid'] = isset($data['hs_object_id']['value']) ? $data['hs_object_id']['value'] : null;
            $data1['aveyo_hs_id'] = isset($data['hs_object_id']['value']) ? $data['hs_object_id']['value'] : null;
            $data1['install_partner'] = isset($data['install_team']['value']) ? $data['install_team']['value'] : null;
            $data1['aveyo_project'] = isset($data['project']['value']) ? $data['project']['value'] : null;
            $data1['homeowner_id'] = isset($data['hubspot_owner_id']['value']) ? $data['hubspot_owner_id']['value'] : null;
            $data1['customer_name'] = isset($data['borrower_name']['value']) ? $data['borrower_name']['value'] : null;
            $data1['customer_address'] = isset($data['full_address']['value']) ? $data['full_address']['value'] : null;
            $data1['customer_address_2'] = isset($data['address']['value']) ? $data['address']['value'] : null;
            $data1['customer_city'] = isset($data['city']['value']) ? $data['city']['value'] : null;
            $data1['customer_state'] = isset($data['state']['value']) ? $data['state']['value'] : null;
            $data1['customer_zip'] = isset($data['postal_code']['value']) ? $data['postal_code']['value'] : null;
            $data1['customer_email'] = isset($data['email']['value']) ? $data['email']['value'] : null;
            $data1['customer_phone'] = isset($data['phone']['value']) ? $data['phone']['value'] : null;
            $data1['sales_rep_email'] = isset($data['setter']['value']) ? $data['setter']['value'] : null;
            $data1['m1_date'] = isset($data['m1_com_approved']['value']) ? $data['m1_com_approved']['value'] : null;
            $data1['m2_date'] = isset($data['m2_com_date']['value']) ? $data['m2_com_date']['value'] : null;
            $data1['date_cancelled'] = isset($data['cancelation_date']['value']) ? $data['cancelation_date']['value'] : null;
            $data1['kw'] = isset($data['system_size']['value']) ? $data['system_size']['value'] : null;
            $data1['dealer_fee_percentage'] = isset($data['dealer_fee____']['value']) ? $data['dealer_fee____']['value'] : null;
            $data1['dealer_fee_amount'] = isset($data['dealer_fee_amount']['value']) ? $data['dealer_fee_amount']['value'] : null;
            $data1['adders'] = isset($data['sow_total_adder_cost']['value']) ? $data['sow_total_adder_cost']['value'] : null;
            $data1['adders_description'] = isset($data['adders_description']['value']) ? $data['adders_description']['value'] : null;
            $data1['epc'] = isset($data['gross_ppw']['value']) ? $data['gross_ppw']['value'] : null;
            $data1['net_epc'] = isset($data['net_ppw_calc']['value']) ? $data['net_ppw_calc']['value'] : null;
            $data1['gross_account_value'] = isset($data['total_cost']['value']) ? $data['total_cost']['value'] : null;
            $data1['product'] = isset($data['project_type']['value']) ? $data['project_type']['value'] : null;
            $data1['setter_id'] = isset($data['setter_id']['value']) ? $data['setter_id']['value'] : null;
            $data1['closer_id'] = isset($data['closer_id']['value']) ? $data['closer_id']['value'] : null;
            $data1['closer_name'] = isset($data['closer']['value']) ? $data['closer']['value'] : null;
            $data1['setter_name'] = isset($data['setter']['value']) ? $data['setter']['value'] : null;
            $data1['contract_sign_date'] = isset($data['contract_sign_date']['value']) ? date('Y-m-d', strtotime($data['contract_sign_date']['value'])) : null;
            $data1['email_status'] = 0;

            // $inserted = LegacyApiNullData::Create($data);
            $getData = LegacyApiNullData::where('pid', $data['hs_object_id']['value'])->first();
            // $userData = User::where('employee_id',$data['closer_id']['value'])->first();

            if (empty($getData)) {
                $inserted = LegacyApiNullData::Create($data1);
                $response = hs_create_raw_data_history_api($data);
            } else {
                $inserteds = LegacyApiNullData::where('id', $getData->id)->update($data1);
                $response = hs_create_raw_data_history_api($data);
            }

        }
    }

    // public function hubspotSaleDataCreateoffer($checkStatus,$token)
    // {
    //     $userdata = OnboardingEmployees::where('id',$checkStatus['id'])->first();
    //     $recruiter =  User::select('first_name','last_name')->where('id',$checkStatus['recruiter_id'])->first();
    //     $manager =  User::select('first_name','last_name')->where('id',$checkStatus['manager_id'])->first();
    //     $team =  ManagementTeam::select('team_name')->where('id',$checkStatus['team_id'])->first();
    //     $office =  Locations::select('office_name')->where('id',$checkStatus['office_id'])->first();
    //     $positions =  Positions::select('position_name')->where('id',$checkStatus['position_id'])->first();
    //     $department = Department::where('id',$checkStatus['department_id'])->first();
    //     $office =  Locations::select('office_name','work_site_id')->where('id',$checkStatus['office_id'])->first();
    //     $state = State::where('id',$checkStatus['state_id'])->first();

    //     $upfrontType ='';
    //     if($userdata->status_id == 1){
    //         $statusName = "Offer Letter Accepted";
    //     }

    //     if($checkStatus['position_id'] == 2){
    //         $payGroup = "Closer";

    //     }else
    //     if($checkStatus['self_gen_accounts'] == 1){
    //         $payGroup = "Setter&Closer";
    //     }else
    //     if($checkStatus['position_id'] == 3){
    //         $payGroup = "Setter";
    //     }

    //     if($checkStatus['upfront_sale_type'] == "per sale"){
    //         $upfrontType = "Per Sale";
    //     }elseif($checkStatus['upfront_sale_type'] == "per KW"){
    //         $upfrontType = "Per kw";
    //     }

    //     $Hubspotdata['properties'] = [
    //     "first_name"=> $checkStatus['first_name'],
    //     "last_name"=> $checkStatus['last_name'],
    //     "sales_name"=> $checkStatus['first_name'].' '.$checkStatus['last_name'],
    //     "email" => $checkStatus['email'],
    //     "phone" => $checkStatus['mobile_no'],
    //     "state" => isset($state['name'])?$state['name']:null,
    //     "city" => $checkStatus['city_id'],
    //     "position_id" => $checkStatus['position_id'],
    //     "position" => isset($positions['position_name']) ? $positions['position_name']:null,
    //     "manager" => isset($manager['first_name']) ? $manager['first_name'].' '.$manager['last_name']:null,
    //     "manager_id" => $checkStatus['manager_id'],
    //     "team_id" => $checkStatus['team_id'],
    //     "team" => isset($team['team_name'])?$team['team_name']:null,
    //     "sequifi_id"=>$userdata->employee_id,
    //     "commission" => $checkStatus['commission'],
    //     "redline" => $checkStatus['redline'],
    //     "setter_redline" => $checkStatus['self_gen_redline'],
    //     "pay_group" => isset($payGroup)?$payGroup:null,
    //     "office_id" => isset($office['work_site_id'])?$office['work_site_id']:null,
    //     "office" => isset($office['office_name'])?$office['office_name']:null,
    //     'department_id' => isset($checkStatus['department_id'])?$checkStatus['department_id']:null,
    //     'department' => isset($department['name'])?$department['name']:null,
    //     'recruiter_id' => isset($checkStatus['recruiter_id'])?$checkStatus['recruiter_id']:null,
    //     "recruiter"=> isset($recruiter['first_name'])?$recruiter['first_name'].' '.$recruiter['last_name']:null,
    //     "upfront_pay_amount"=>$checkStatus['upfront_pay_amount'],
    //     "upfront_type" => isset($upfrontType)?$upfrontType:null,
    //     "status"=>isset($statusName)?$statusName:null,

    //     ];
    //      $uid = $checkStatus->id;
    //      $aveyoid = $this->get_sales($token,$checkStatus->id,$checkStatus->mobile_no);

    //      if($aveyoid !=[]){
    //        $update_employees = $this->update_employees($Hubspotdata,$token,$uid,$aveyoid);
    //      }else{
    //        $create_employees = $this->create_employees($Hubspotdata, $token,$checkStatus->id);
    //      }
    // }

    public function hubspotOnboardemployee($checkStatus, $recruiter_id, $token)
    {

        $recruiter = User::select('first_name', 'last_name')->where('id', $checkStatus['recruiter_id'])->first();
        $manager = User::select('first_name', 'last_name')->where('id', $checkStatus['manager_id'])->first();
        $team = ManagementTeam::select('team_name')->where('id', $checkStatus['team_id'])->first();
        $office = Locations::select('office_name')->where('id', $checkStatus['office_id'])->first();
        $positions = Positions::select('position_name')->where('id', $checkStatus['position_id'])->first();
        $department = Department::where('id', $checkStatus['department_id'])->first();
        $office = Locations::select('office_name', 'work_site_id', 'general_code')->where('id', $checkStatus['office_id'])->first();
        $state = State::where('id', $checkStatus['state_id'])->first();
        $statusName = isset($checkStatus['status']) ? $checkStatus['status'] : 'inactive';

        $additionalOfficeId = OnboardingEmployeeLocations::where('user_id', $checkStatus->id)->pluck('office_id');
        $additionalOfficeName = Locations::whereNotNull('office_name')->whereIn('id', $additionalOfficeId)->pluck('office_name')->implode(',');
        $additionalWorkSiteId = Locations::whereNotNull('work_site_id')->whereIn('id', $additionalOfficeId)->pluck('work_site_id')->implode(',');

        $upfrontType = '';

        if ($checkStatus['position_id'] == 2) {
            $payGroup = 'Closer';
            $closer_redline = $checkStatus['redline'];
            $setter_redline = $checkStatus['self_gen_redline'];
        }
        if ($checkStatus['position_id'] == 3) {
            $payGroup = 'Setter';
            $closer_redline = $checkStatus['self_gen_redline'];
            $setter_redline = $checkStatus['redline'];
        }
        if ($checkStatus['self_gen_accounts'] == 1) {
            $payGroup = 'Setter&Closer';
        }

        if ($checkStatus['upfront_sale_type'] == 'per sale') {
            $upfrontType = 'Per Sale';
        } elseif ($checkStatus['upfront_sale_type'] == 'per KW') {
            $upfrontType = 'Per kw';
        }

        $Hubspotdata['properties'] = [
            'first_name' => $checkStatus['first_name'],
            'last_name' => $checkStatus['last_name'],
            'sales_name' => $checkStatus['first_name'].' '.$checkStatus['last_name'],
            'email' => $checkStatus['email'],
            'phone' => $checkStatus['mobile_no'],
            'state' => isset($state['name']) ? $state['name'] : null,
            'city' => $checkStatus['city_id'],
            'position_id' => $checkStatus['position_id'],
            'position' => isset($positions['position_name']) ? $positions['position_name'] : null,
            'manager' => isset($manager['first_name']) ? $manager['first_name'].' '.$manager['last_name'] : null,
            'manager_id' => $checkStatus['manager_id'],
            'team_id' => $checkStatus['team_id'],
            'team' => isset($team['team_name']) ? $team['team_name'] : null,
            'sequifi_id' => $checkStatus['employee_id'],
            'commission' => $checkStatus['commission'],
            'redline' => $closer_redline, //  in hubspot this is  closer redline
            'setter_redline' => $setter_redline,
            'pay_group' => isset($payGroup) ? $payGroup : null,
            'office_id' => isset($office['work_site_id']) ? $office['work_site_id'] : null,
            'office' => isset($office['office_name']) ? $office['office_name'] : null,
            'installer_on_file' => isset($office['general_code']) ? $office['general_code'] : null,
            'department_id' => isset($checkStatus['department_id']) ? $checkStatus['department_id'] : null,
            'department' => isset($department['name']) ? $department['name'] : null,
            'recruiter_id' => isset($checkStatus['recruiter_id']) ? $checkStatus['recruiter_id'] : $recruiter_id,
            'recruiter' => isset($recruiter['first_name']) ? $recruiter['first_name'].' '.$recruiter['last_name'] : null,
            'upfront_pay_amount' => $checkStatus['upfront_pay_amount'],
            'upfront_type' => isset($upfrontType) ? $upfrontType : null,
            'status' => isset($statusName) ? $statusName : 'Inactive',
            'last_update_date' => isset($checkStatus['updated_at']) ? date('Y-m-d', strtotime($checkStatus['updated_at'])) : null,
            'office_additional_id' => isset($additionalWorkSiteId) ? $additionalWorkSiteId : null,
            'office_additional' => isset($additionalOfficeName) ? $additionalOfficeName : null,

        ];

        $uid = $checkStatus['id'];
        $aveyoid = $this->get_sales($token, 0, $checkStatus['employee_id']);
        if ($aveyoid != []) {
            $update_employees = $this->update_employees($Hubspotdata, $token, $uid, $aveyoid, 'Onboarding_employee');
        } else {
            $create_employees = $this->create_employees($Hubspotdata, $token, $checkStatus['id'], 'Onboarding_employee');
        }
    }

    public function hubspotSaleDataCreate($data, $checkStatus, $uid, $token)
    {
        $userdata = User::where('id', $data->id)->first();
        $recruiter = User::select('first_name', 'last_name')->where('id', $checkStatus['recruiter_id'])->first();
        $manager = User::select('first_name', 'last_name')->where('id', $checkStatus['manager_id'])->first();
        $team = ManagementTeam::select('team_name')->where('id', $checkStatus['team_id'])->first();
        $office = Locations::select('office_name')->where('id', $checkStatus['office_id'])->first();
        $positions = Positions::select('position_name')->where('id', $checkStatus['position_id'])->first();
        $department = Department::where('id', $checkStatus['department_id'])->first();
        $office = Locations::select('office_name', 'work_site_id', 'general_code')->where('id', $checkStatus['office_id'])->first();
        $state = State::where('id', $checkStatus['state_id'])->first();

        $additionalOfficeId = OnboardingEmployeeLocations::where('user_id', $checkStatus->id)->pluck('office_id');
        $additionalOfficeName = Locations::whereNotNull('office_name')->whereIn('id', $additionalOfficeId)->pluck('office_name')->implode(',');
        $additionalWorkSiteId = Locations::whereNotNull('work_site_id')->whereIn('id', $additionalOfficeId)->pluck('work_site_id')->implode(',');

        $upfrontType = '';
        // if($userdata->status_id == 1){
        //     $statusName = "Active";
        // }elseif($userdata->status_id == 2){
        //     $statusName = "Inactive";
        // }

        if ($checkStatus['position_id'] == 2) {
            $payGroup = 'Closer';
            $closer_redline = $checkStatus['redline'];
            $setter_redline = $checkStatus['self_gen_redline'];
        }
        if ($checkStatus['position_id'] == 3) {
            $payGroup = 'Setter';
            $closer_redline = $checkStatus['self_gen_redline'];
            $setter_redline = $checkStatus['redline'];
        }
        if ($checkStatus['self_gen_accounts'] == 1) {
            $payGroup = 'Setter&Closer';
        }

        if ($checkStatus['upfront_sale_type'] == 'per sale') {
            $upfrontType = 'Per Sale';
        } elseif ($checkStatus['upfront_sale_type'] == 'per KW') {
            $upfrontType = 'Per kw';
        }

        $Hubspotdata['properties'] = [
            'first_name' => $checkStatus['first_name'],
            'last_name' => $checkStatus['last_name'],
            'sales_name' => $checkStatus['first_name'].' '.$checkStatus['last_name'],
            'email' => $checkStatus['email'],
            'phone' => $checkStatus['mobile_no'],
            'state' => isset($state['name']) ? $state['name'] : null,
            'city' => $checkStatus['city_id'],
            'position_id' => $checkStatus['position_id'],
            'position' => isset($positions['position_name']) ? $positions['position_name'] : null,
            'manager' => isset($manager['first_name']) ? $manager['first_name'].' '.$manager['last_name'] : null,
            'manager_id' => $checkStatus['manager_id'],
            'team_id' => $checkStatus['team_id'],
            'team' => isset($team['team_name']) ? $team['team_name'] : null,
            'sequifi_id' => $checkStatus['employee_id'],
            'commission' => $checkStatus['commission'],
            'redline' => $closer_redline, //  in hubspot this is  closer redline
            'setter_redline' => $setter_redline,
            'pay_group' => isset($payGroup) ? $payGroup : null,
            'office_id' => isset($office['work_site_id']) ? $office['work_site_id'] : null,
            'office' => isset($office['office_name']) ? $office['office_name'] : null,
            'installer_on_file' => isset($office['general_code']) ? $office['general_code'] : null,
            'department_id' => isset($checkStatus['department_id']) ? $checkStatus['department_id'] : null,
            'department' => isset($department['name']) ? $department['name'] : null,
            'recruiter_id' => isset($checkStatus['recruiter_id']) ? $checkStatus['recruiter_id'] : $uid,
            'recruiter' => isset($recruiter['first_name']) ? $recruiter['first_name'].' '.$recruiter['last_name'] : null,
            'upfront_pay_amount' => $checkStatus['upfront_pay_amount'],
            'upfront_type' => isset($upfrontType) ? $upfrontType : null,
            'status' => isset($checkStatus['status']) ? $checkStatus['status'] : null,
            'last_update_date' => isset($checkStatus['updated_at']) ? date('Y-m-d', strtotime($checkStatus['updated_at'])) : null,
            'office_additional_id' => isset($additionalWorkSiteId) ? $additionalWorkSiteId : null,
            'office_additional' => isset($additionalOfficeName) ? $additionalOfficeName : null,

        ];

        $uid = $data->id;
        // $aveyoid = $this->get_sales($token,$data->id,$userdata->employee_id);

        // Added By Gorakh
        $check_Aveyo_Id = $this->get_sales($token, $data->id, $checkStatus['employee_id']);

        if ($check_Aveyo_Id != []) {
            $update_employees = $this->update_employees($Hubspotdata, $token, $uid, $check_Aveyo_Id);
        } else {
            $create_employees = $this->create_employees($Hubspotdata, $token, $data->id);
        }
    }

    public function SyncHsSalesDataCreate($data, $token)
    {
        $data = json_decode($data, true);
        foreach ($data as $key => $val) {
            $userId = $val['id'];
            $additionalOfficeId = AdditionalLocations::where('user_id', $val['id'])->pluck('office_id');
            $additionalOfficeName = Locations::whereNotNull('office_name')->whereIn('id', $additionalOfficeId)->pluck('office_name')->implode(',');
            $additionalWorkSiteId = Locations::whereNotNull('work_site_id')->whereIn('id', $additionalOfficeId)->pluck('work_site_id')->implode(',');
            $additionalTeamName = ManagementTeam::whereNotNull('team_name')->where('team_lead_id', $val['id'])->pluck('team_name')->implode(',');
            $additionalTeamId = ManagementTeam::whereNotNull('id')->where('team_lead_id', $val['id'])->pluck('id')->implode(',');

            $statusName = ($val['status_id'] == 1) ? 'Active' : (($val['status_id'] == 2) ? 'Inactive' : null);
            $upfrontType = ($val['upfront_sale_type'] == 'per sale') ? 'Per Sale' : (($val['upfront_sale_type'] == 'per KW') ? 'Per kw' : null);
            $payGroup = $closer_redline = $setter_redline = '';
            if ($val['position_id'] == 2) {
                $payGroup = 'Closer';
                $closer_redline = $val['redline'];
                $setter_redline = $val['self_gen_redline'];
            } elseif ($val['position_id'] == 3) {
                $payGroup = 'Setter';
                $closer_redline = $val['self_gen_redline'];
                $setter_redline = $val['redline'];
            } elseif ($val['self_gen_accounts'] == 1) {
                $payGroup = 'Setter&Closer';
            }

            $Hubspotdata['properties'] = [
                'first_name' => $val['first_name'],
                'last_name' => $val['last_name'],
                'sales_name' => $val['first_name'].' '.$val['last_name'],
                'email' => $val['email'],
                'phone' => $val['mobile_no'],
                'state' => isset($val['state']['name']) ? $val['state']['name'] : null,
                'city' => $val['city_id'],
                'position_id' => $val['position_id'],
                'position' => isset($val['position_detail_team']['position_name']) ? $val['position_detail_team']['position_name'] : null,
                'manager' => isset($val['manager_detail']['first_name']) ? $val['manager_detail']['first_name'].' '.$val['manager_detail']['last_name'] : null,
                'manager_id' => $val['manager_id'],
                'team_id' => $val['team_id'],
                'team' => isset($val['teams_detail']['team_name']) ? $val['teams_detail']['team_name'] : null,
                'sequifi_id' => $val['employee_id'],
                'commission' => $val['commission'],
                'redline' => $closer_redline, //  in hubspot this is  closer redline
                'setter_redline' => $setter_redline,
                'pay_group' => isset($payGroup) ? $payGroup : null,
                'office_id' => isset($val['office']['work_site_id']) ? $val['office']['work_site_id'] : null,
                'office' => isset($val['office']['office_name']) ? $val['office']['office_name'] : null,
                'installer_on_file' => isset($val['office']['general_code']) ? $val['office']['general_code'] : null,
                'department_id' => isset($val['department_id']) ? $val['department_id'] : null,
                'department' => isset($val['department_detail']['name']) ? $val['department_detail']['name'] : null,
                'recruiter_id' => isset($val['recruiter_id']) ? $val['recruiter_id'] : null,
                'recruiter' => isset($val['recruiter']['first_name']) ? $val['recruiter']['first_name'].' '.$val['recruiter']['last_name'] : null,
                'upfront_pay_amount' => $val['upfront_pay_amount'],
                'upfront_type' => isset($upfrontType) ? $upfrontType : null,
                'status' => isset($statusName) ? $statusName : null,
                'dob' => isset($val['dob']) ? $val['dob'] : null,
                'birthday' => isset($val['dob']) ? $val['dob'] : null,
                'sex' => isset($val['sex']) ? $val['sex'] : null,
                'zip_code' => isset($val['zip_code']) ? $val['zip_code'] : null,
                'work_email' => isset($val['work_email']) ? $val['work_email'] : null,
                'last_update_date' => isset($val['updated_at']) ? date('Y-m-d', strtotime($val['updated_at'])) : null,

                'office_additional_id' => isset($additionalWorkSiteId) ? $additionalWorkSiteId : null,
                'office_additional' => isset($additionalOfficeName) ? $additionalOfficeName : null,
                'team_additional_id' => isset($additionalTeamId) ? $additionalTeamId : null,
                'team_additional' => isset($additionalTeamName) ? $additionalTeamName : null,
            ];

            $uid = $userId;
            $aveyoid = $this->get_sales($token, $userId, $val['employee_id']);
            $crms = Crms::find(2);
            $crms->touch();

            if ($aveyoid != []) {
                $update_employees = $this->update_employees($Hubspotdata, $token, $uid, $aveyoid);
            } else {
                $create_employees = $this->create_employees($Hubspotdata, $token, $uid);
            }
        }

    }

    // Code by Nikhil

    public function create_employees($Hubspotdata, $token, $user_id, $table = 'User')
    {
        // $url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $url = 'https://api.hubapi.com/crm/v3/objects/sales';
        $Hubspotdata = json_encode($Hubspotdata);

        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestData($url, $Hubspotdata, $headers, 'POST');

        $resp = json_decode($curl_response, true);

        if (count($resp) > 0) {
            if (isset($resp['properties']['hs_object_id'])) {
                $hs_object_id = $resp['properties']['hs_object_id'];
            } else {
                $hs_object_id = 0;
            }
            // $email = $resp['properties']['email'];

            if ($table == 'User') {
                // array_push($syncount, $hs_object_id);
                $updateuser = User::where('id', $user_id)->first();
                // $updateuser = OnboardingEmployees::where('id', $user_id)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                }
            } elseif ($table == 'Onboarding_employee') {
                $updateuser = OnboardingEmployees::where('id', $user_id)->first();
                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                }

            }
        }
    }

    public function curlRequestData($url, $Hubspotdata, $headers, $method = 'POST')
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.hubapi.com/crm/v3/objects/sales',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $Hubspotdata,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }

    // sales data get with uniq mobile number code start
    public function get_sales($token, $user_id, $sequifi_id)
    {
        $url = 'https://api.hubapi.com/crm/v3/objects/sales/search';
        // $Hubspotdata=json_encode($Hubspotdata);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $filters[] = [
            'propertyName' => 'sequifi_id',
            'operator' => 'EQ',
            'value' => $sequifi_id,
        ];

        $data['filterGroups'][] = ['filters' => $filters];

        $data = json_encode($data);
        $curl_response = $this->curlRequestSalesData($url, $headers, $data, 'POST');
        $resp = json_decode($curl_response, true);
        if ($resp['total'] == 0) {
            $newData = $resp['results'];
        } else {
            $newData = $resp['results'][0]['id'];
        }

        return $newData;
    }

    public function curlRequestSalesData($url, $headers, $data, $method = 'POST')
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,

        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }
    // sales data get with uniq mobile number code end

    // update hubspot sales data start

    public function update_employees($Hubspotdata, $token, $user_id, $aveyoid, $table = 'User')
    {
        // $url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $url = "https://api.hubapi.com/crm/v3/objects/sales/$aveyoid";
        $Hubspotdata = json_encode($Hubspotdata);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestDataUpdate($url, $Hubspotdata, $headers, 'PATCH');

        $resp = json_decode($curl_response, true);

        if (count($resp) > 0) {
            if (isset($resp['properties']['hs_object_id'])) {
                $hs_object_id = $resp['properties']['hs_object_id'];
            } else {
                $hs_object_id = 0;
            }
            // $email = $resp['properties']['email'];
            if ($table == 'User') {
                $updateuser = User::where('id', $user_id)->first();
                //  $updateuser = OnboardingEmployees::where('id', $user_id)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->update();
                }
            } elseif ($table == 'Onboarding_employee') {
                $updateuser = OnboardingEmployees::where('id', $user_id)->first();
                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->update();
                }
            }
        }
    }

    public function update_hubspot_data($Hubspotdata, $token, $aveyoid)
    {
        // $url = "https://api.hubapi.com/crm/v3/objects/contacts";
        $url = "https://api.hubapi.com/crm/v3/objects/sales/$aveyoid";
        $Hubspotdata = json_encode($Hubspotdata);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];
        $curl_response = $this->curlRequestDataUpdate($url, $Hubspotdata, $headers, 'PATCH');
        $resp = json_decode($curl_response, true);
    }

    public function curlRequestDataUpdate($url, $Hubspotdata, $headers, $method = 'PATCH')
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $Hubspotdata,
            CURLOPT_HTTPHEADER => $headers,

        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }
    // update hubspot sales data end

    // End code by Nikhil

    // function for hubspot current energy

    public function hubspotSubroutineForCurrentEnergy($data, $contactIds)
    {
        $salesRepData = null;
        $salesRepEmail = null;
        $salesRepname = null;
        $customerName = null;
        $customerAddress = null;
        $customerCity = null;
        $customerState = null;
        $customerZip = null;
        $customerEmail = null;
        $customerPhone = null;
        $salesRepId = null;
        if (! empty($contactIds)) {
            foreach ($contactIds as $contactId) {
                $getContactDetails = $this->getContactDetailsByIds($contactId);
                if (! empty($getContactDetails)) {
                    $contact_type = isset($getContactDetails['properties']['contact_type']) ? $getContactDetails['properties']['contact_type'] : null;
                    if ($contact_type == 'Sales Rep') {
                        $sales_rep_id = isset($getContactDetails['id']) ? $getContactDetails['id'] : null;
                        $salesRepData = User::where('aveyo_hs_id', $sales_rep_id)->first();
                        $salesRepId = isset($salesRepData->id) ? $salesRepData->id : null;
                        $salesRepEmail = isset($salesRepData->email) ? $salesRepData->email : null;
                        $salesRepname = isset($salesRepData->first_name) ? $salesRepData->first_name.' '.$salesRepData->last_name : null;
                    } elseif ($contact_type == 'Customer') {
                        $customer_fname = isset($getContactDetails['properties']['firstname']) ? $getContactDetails['properties']['firstname'] : null;
                        $customer_lname = isset($getContactDetails['properties']['lastname']) ? $getContactDetails['properties']['lastname'] : null;
                        $customerName = $customer_fname.' '.$customer_lname;
                        $customerAddress = isset($getContactDetails['properties']['address']) ? $getContactDetails['properties']['address'] : null;
                        $customerCity = isset($getContactDetails['properties']['city']) ? $getContactDetails['properties']['city'] : null;
                        $customerState = isset($getContactDetails['properties']['hs_state_code']) ? $getContactDetails['properties']['hs_state_code'] : null;
                        $customerZip = isset($getContactDetails['properties']['zip']) ? $getContactDetails['properties']['zip'] : null;
                        $customerEmail = isset($getContactDetails['properties']['email']) ? $getContactDetails['properties']['email'] : null;
                        $customerPhone = isset($getContactDetails['properties']['mobilephone']) ? $getContactDetails['properties']['mobilephone'] : null;
                    }
                    if (isset($salesRepEmail) && $salesRepname && $customerName) {
                        break;
                    }
                }
            }
        }

        $triggerDate[]['date'] = (isset($data->properties->payment_approved_date) && $data->properties->payment_approved_date != '') ? $data->properties->payment_approved_date : null;
        $productCode = isset($data->properties->financing_type) ? $data->properties->financing_type : null;
        $productCode = strtolower(str_replace(' ', '', $productCode));
        $product = ProductCode::withTrashed()->where('product_code', $productCode)->first();
        if (! $product) {
            $product = ProductCode::withTrashed()->where('product_code', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
        }
        $value['pid'] = isset($data->properties->hs_object_id) ? $data->properties->hs_object_id : null;
        $value['customer_name'] = isset($customerName) ? $customerName : null;
        $value['customer_address'] = $customerAddress;
        $value['customer_city'] = $customerCity;
        $value['customer_state'] = $customerState;
        $value['customer_zip'] = $customerZip;
        $value['customer_email'] = $customerEmail;
        $value['customer_phone'] = $customerPhone;
        $value['product_id'] = $product->product_id;
        $value['product_code'] = $product->product_code;
        $value['trigger_date'] = json_encode($triggerDate);
        $value['m1_date'] = (isset($data->properties->payment_approved_date) && $data->properties->payment_approved_date != '') ? $data->properties->payment_approved_date : null;
        $value['m2_date'] = (isset($data->properties->payment_approved_date) && $data->properties->payment_approved_date != '') ? $data->properties->payment_approved_date : null;
        $value['date_cancelled'] = (isset($data->properties->cancel_date) && $data->properties->cancel_date != '') ? $data->properties->cancel_date : null;
        $value['kw'] = isset($data->properties->system_size__kw_) ? $data->properties->system_size__kw_ : null;
        $value['adders'] = isset($data->properties->adders_total_amount) ? $data->properties->adders_total_amount : null;
        $value['epc'] = isset($data->properties->gross_epc) ? $data->properties->gross_epc : null;
        $value['net_epc'] = isset($data->properties->net_epc) ? $data->properties->net_epc : null;
        $value['gross_account_value'] = isset($data->properties->hs_tcv) ? $data->properties->hs_tcv : null;
        $value['sales_rep_name'] = $salesRepname;
        $value['sales_rep_email'] = $salesRepEmail;
        $value['closer1_id'] = $salesRepId;
        $value['setter1_id'] = $salesRepId;
        $value['customer_signoff'] = isset($data->properties->closedate) ? $data->properties->closedate : null;
        $value['data_source_type'] = 'hubspot_current_energy';
        hs_create_raw_data_history_api_new($value);
    }

    private function getContactDetailsByIds($contactId)
    {
        // echo"DADS";die;
        $contact_url = 'https://api.hubapi.com/crm/v3/objects/contacts/'.$contactId.'?properties=firstname%2Clastname%2Ccity%2Caddress%2Chs_state_code%2Czip%2Cemail%2Cmobilephone%2Ccontact_type';
        $tokens = 'pat-na1-a2c0f38b-4fea-47d7-9cfe-6b8d6132f202';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $contact_url, // your preferred url
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
        $contact_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get the HTTP status code
        // dd($contact_response);
        $err = curl_error($ch);
        if ($http_code === 200) {
            $res_contact = json_decode($contact_response, true);

            return $res_contact;
        } else {
            return null;
        }
    }

    // // Push Rep Data to Hubspot Current Energy
    public function pushRepDataToHubspotCurrentEnergy($data, $checkStatus, $uid, $token)
    {
        $userdata = User::where('id', $data->id)->first();
        $recruiter = User::select('first_name', 'last_name')->where('id', $checkStatus['recruiter_id'])->first();
        $manager = User::select('first_name', 'last_name')->where('id', $checkStatus['manager_id'])->first();
        $team = ManagementTeam::select('team_name')->where('id', $checkStatus['team_id'])->first();
        // $office =  Locations::select('office_name')->where('id',$checkStatus['office_id'])->first();
        $positions = Positions::select('position_name')->where('id', $checkStatus['position_id'])->first();
        $department = Department::where('id', $checkStatus['department_id'])->first();
        $office = Locations::select('office_name', 'work_site_id', 'general_code')->where('id', $checkStatus['office_id'])->first();
        $state = State::where('id', $checkStatus['state_id'])->first();
        $city = Cities::where('id', $checkStatus['city_id'])->first();

        $additionalOfficeId = OnboardingEmployeeLocations::where('user_id', $checkStatus->id)->pluck('office_id');
        $additionalOfficeName = Locations::whereNotNull('office_name')->whereIn('id', $additionalOfficeId)->pluck('office_name')->implode(',');
        $additionalWorkSiteId = Locations::whereNotNull('work_site_id')->whereIn('id', $additionalOfficeId)->pluck('work_site_id')->implode(',');

        $upfrontType = '';

        if ($checkStatus['position_id'] == 2) {
            $payGroup = 'Closer';
            $closer_redline = $checkStatus['redline'];
            $setter_redline = $checkStatus['self_gen_redline'];
        }
        if ($checkStatus['position_id'] == 3) {
            $payGroup = 'Setter';
            $closer_redline = $checkStatus['self_gen_redline'];
            $setter_redline = $checkStatus['redline'];
        }
        if ($checkStatus['self_gen_accounts'] == 1) {
            $payGroup = 'Setter&Closer';
        }

        if ($checkStatus['upfront_sale_type'] == 'per sale') {
            $upfrontType = 'Per Sale';
        } elseif ($checkStatus['upfront_sale_type'] == 'per KW') {
            $upfrontType = 'Per kw';
        }
        // $Hubspotdata['objectWriteTraceId'] = "string",
        $Hubspotdata['properties'] = [
            'firstname' => $checkStatus['first_name'] ?? null,
            'lastname' => $checkStatus['last_name'] ?? null,
            'company' => 'companyString',
            'website' => 'companyString.com',
            'phone' => $checkStatus['mobile_no'],
            'email' => $checkStatus['email'],
            'zip' => $checkStatus['zip_code'] ?? null,
            'state' => isset($state['name']) ? $state['name'] : null,
            'city' => isset($city['name']) ? $city['name'] : null,
            'address' => $checkStatus['home_address'] ?? null,
            'jobtitle' => 'string',
            'hs_lead_status' => 'NEW',
            'hs_language' => 'en',
            'closedate' => '2024-11-13',
            // "contact_type" => "",
            // "gender"  => "",
            // "hubspot_team_id"   => $checkStatus['team_id'],

            /*"position_id" => $checkStatus['position_id'],
            "position" => isset($positions['position_name']) ? $positions['position_name']:null,
            "manager" => isset($manager['first_name']) ? $manager['first_name'].' '.$manager['last_name']:null,
            "manager_id" => $checkStatus['manager_id'],
            "team_id" => $checkStatus['team_id'],
            "team" => isset($team['team_name'])?$team['team_name']:null,
            "sequifi_id"=>$checkStatus['employee_id'],
            "commission" => $checkStatus['commission'],
            "redline" => $closer_redline, //  in hubspot this is  closer redline
            "setter_redline" => $setter_redline,
            "pay_group" => isset($payGroup)?$payGroup:null,
            "office_id" => isset($office['work_site_id'])?$office['work_site_id']:null,
            "office" => isset($office['office_name'])?$office['office_name']:null,
            "installer_on_file" => isset($office['general_code'])?$office['general_code']:null,
            'department_id' => isset($checkStatus['department_id'])?$checkStatus['department_id']:null,
            'department' => isset($department['name'])?$department['name']:null,
            'recruiter_id' => isset($checkStatus['recruiter_id'])?$checkStatus['recruiter_id']:$uid,
            "recruiter"=> isset($recruiter['first_name'])?$recruiter['first_name'].' '.$recruiter['last_name']:null,
            "upfront_pay_amount"=>$checkStatus['upfront_pay_amount'],
            "upfront_type" => isset($upfrontType)?$upfrontType:null,
            "status"=>isset($checkStatus['status']) ? $checkStatus['status']:null,
            "last_update_date"=>isset($checkStatus['updated_at'])? date('Y-m-d',strtotime($checkStatus['updated_at'])):null,
            "office_additional_id" => isset($additionalWorkSiteId)?$additionalWorkSiteId:null,
            "office_additional" => isset($additionalOfficeName)?$additionalOfficeName:null,*/

        ];
        $Hubspotdata2['properties'] = [
            'firstname' => $checkStatus['first_name'] ?? null,
            'lastname' => $checkStatus['last_name'] ?? null,
            'company' => 'companyString',
            'website' => 'companyString.com',
            'phone' => $checkStatus['mobile_no'],
            'email' => $checkStatus['email'],
            'zip' => $checkStatus['zip_code'] ?? null,
            'state' => isset($state['name']) ? $state['name'] : null,
            'city' => isset($city['name']) ? $city['name'] : null,
            'address' => $checkStatus['home_address'] ?? null,
            // "jobtitle" => 'string',
            // "hs_lead_status" => 'NEW',
            // "hs_language" => 'en',
            // "closedate" => "2024-11-13",
            'sales_rep_id' => $checkStatus['employee_id'],
            'contact_type' => 'Sales Rep',
        ];

        $uid = $data->id;
        $check_contact = $this->getContactDetailsForHubspotCurrentEnergy($token, $data->email, $checkStatus['employee_id']);
        Log::info(['check_contactId===>' => $check_contact]);
        if (! empty($check_contact)) {
            $update_employees = $this->updateContactForHubspotCurrentEnergy($Hubspotdata2, $token, $uid, $check_contact);
            Log::info(['update_employees===>' => $update_employees]);
        } else {
            $create_employees = $this->createContactForHubspotCurrentEnergy($Hubspotdata2, $token, $data->id);
            Log::info(['create_employees===>' => $create_employees]);
        }
    }

    public function getContactDetailsForHubspotCurrentEnergy($tokens, $email, $employeeId)
    {
        $contact_url = 'https://api.hubapi.com/crm/v3/objects/contacts/'.$email.'?idProperty=email';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $contact_url, // your preferred url
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
        $contact_response = curl_exec($ch);
        $res_contact = json_decode($contact_response, true);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get the HTTP status code
        Log::info(['getContactDetailsForHubspotCurrentEnergy res_contact===>' => $res_contact, 'http_code' => $http_code]);
        // dd($response);
        $err = curl_error($ch);
        // if ($http_code === 200) {
        //     return $res_contact['id'];
        // } else {
        //     return null;
        // }
        if (isset($res_contact['properties']['hs_object_id'])) {
            $hs_object_id = $res_contact['properties']['hs_object_id'];
        } else {
            $hs_object_id = null;
        }

        return $hs_object_id;

    }

    public function updateContactForHubspotCurrentEnergy($Hubspotdata, $token, $user_id, $contact_id, $table = 'User')
    {
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts/'.$contact_id;
        $Hubspotdata = json_encode($Hubspotdata);
        Log::info(['updateContactForHubspotCurrentEnergy Hubspotdata===>' => $Hubspotdata]);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestDataForCurrentEnergy($url, $Hubspotdata, $headers, 'PATCH');

        $resp = json_decode($curl_response, true);
        Log::info(['updateContactForHubspotCurrentEnergy updateContact===>' => $resp]);
        if (isset($resp) && isset($resp['status']) && $resp['status'] == 'error') {
            Log::info(['updateContactForHubspotCurrentEnergy updateContact error===>' => $resp]);
        } else {
            Log::info(['updateContactForHubspotCurrentEnergy found===>' => true]);
            if (isset($resp['properties']['hs_object_id'])) {
                $hs_object_id = $resp['properties']['hs_object_id'];
            } else {
                $hs_object_id = 0;
            }
            // $email = $resp['properties']['email'];
            if ($table == 'User') {
                $updateuser = User::where('id', $user_id)->first();
                //  $updateuser = OnboardingEmployees::where('id', $user_id)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->update();
                    Log::info(['updateContactForHubspotCurrentEnergy UserUpdate===>' => true]);
                }
            } elseif ($table == 'Onboarding_employee') {
                $updateuser = OnboardingEmployees::where('id', $user_id)->first();
                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->update();
                    Log::info(['updateContactForHubspotCurrentEnergy OnboardingEmployeeUpdate===>' => true]);
                }
            }
        }
    }

    public function createContactForHubspotCurrentEnergy($Hubspotdata, $token, $user_id, $table = 'User')
    {
        $url = 'https://api.hubapi.com/crm/v3/objects/contacts';
        $Hubspotdata = json_encode($Hubspotdata);
        Log::info(['createContactForHubspotCurrentEnergy Hubspotdata===>' => $Hubspotdata]);
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'authorization: Bearer '.$token,
        ];

        $curl_response = $this->curlRequestDataForCurrentEnergy($url, $Hubspotdata, $headers, 'POST');

        $resp = json_decode($curl_response, true);
        Log::info(['createContactForHubspotCurrentEnergy createContact===>' => $resp]);
        if (isset($resp) && isset($resp['status']) && $resp['status'] == 'error') {
            Log::info(['updateContactForHubspotCurrentEnergy updateContact error===>' => $resp]);
        } else {
            Log::info(['createContactForHubspotCurrentEnergy success===>' => true]);
            if (isset($resp['properties']['hs_object_id'])) {
                $hs_object_id = $resp['properties']['hs_object_id'];
            } else {
                $hs_object_id = 0;
            }
            // $email = $resp['properties']['email'];

            if ($table == 'User') {
                // array_push($syncount, $hs_object_id);
                $updateuser = User::where('id', $user_id)->first();

                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                    Log::info(['createContactForHubspotCurrentEnergy UserUpdate===>' => true]);
                }
            } elseif ($table == 'Onboarding_employee') {
                $updateuser = OnboardingEmployees::where('id', $user_id)->first();
                if ($updateuser) {
                    $updateuser->aveyo_hs_id = $hs_object_id;
                    $updateuser->save();
                    Log::info(['createContactForHubspotCurrentEnergy OnboardingEmployeeUpdate===>' => true]);
                }

            }
        }
    }

    public function curlRequestDataForCurrentEnergy($url,$Hubspotdata,$headers,$method = 'POST')
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $Hubspotdata,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }
}
