<?php

namespace App\Core\Traits;

use App\Models\BackendSetting;
use App\Models\CloserIdentifyAlert;
use App\Models\DeductionAlert;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\Locations;
use App\Models\PayRollProcessing;
use App\Models\PositionOverride;
use App\Models\SaleMasterProcess;
use App\Models\SetterIdentifyAlert;
use App\Models\State;
use App\Models\User;
use App\Models\UserReconciliationWithholding;
use App\Models\UsersAdditionalEmail;

trait CloserSubroutineListTrait
{
    public function subroutineOne($checked)
    {
        // echo"subroutineOne";die;
        $rep_email = $checked->sales_rep_email;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $epc = $checked->epc;
        $netEpc = $checked->net_epc;
        $customerState = $checked->customer_state;
        // customer state Id..................................................
        $state = State::where('name', $customerState)->first();
        $customerStateId = isset($state->id) ? $state->id : 0;
        // Identify Closer ( Compare Rep_email with the Work Email( Sequifi )
        $closer = User::where('email', $rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $rep_email)->first();
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        if ($closer) {
            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            $UpdateData->closer1_id = $closer->id;
            $UpdateData->save();

            // //Calculated Closer Commission...............................................
            // $redline = $closer->redline;
            // $closerCommission = $closer->commission;
            // $closerRedlineAmount = $closer->redline_amount;
            // $closerStateId = $closer->state_id;

            // Identify Setter
            if ($setterId != null) {

                $setterIdCheck = User::where('id', $setterId)->first();
                if (isset($setterIdCheck) && $setterIdCheck != '') {
                    //  Setter Data is updated
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    $UpdateData->setter1_id = $setterId;
                    $UpdateData->save();

                    // Calculated Setter Commission...............................................
                    /*$setter = User::where('id', $setterId)->first();
                    $redline = $setter->redline;
                    $commission = $setter->commission;
                    $redlineAmount = $setter->redline_amount;
                    $setterStateId = $setter->state_id;
                    // Get Location Redline .............................................
                    $locationRedline = Locations::where('state_id', $setterStateId)->first();
                    $saleRedlineMin = $locationRedline->redline_min;
                    $saleRedlineStandard = $locationRedline->redline_standard;
                    $saleRedlineMax = $locationRedline->redline_max;
                    $kw = $checked->kw;
                    if ($setterStateId != $customerStateId) {
                        $commission = ($netEpc - $saleRedlineStandard) * $kw * 1000 * $commission;
                        $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                        $UpdateData->setter_m1 = $commission;
                        $UpdateData->save();
                    } else {
                        $commission = ($netEpc - $redline) * $kw * 1000 * $commission;
                        $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                        $UpdateData->setter_m1 = $commission;
                        $UpdateData->save();
                    }*/

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
            // echo"no";die;
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

    public function subroutineTwo($val, $lid)
    {
        // echo"subroutineTwo";die;
        $data['ct'] = null;

        if ($val->prospect_id != null && $val->customer_name != null && $val->kw != null && $val->customer_state != null && $val->rep_name != null && $val->rep_email != null) {
            $data['legacy_data_id'] = isset($val->id) ? $val->id : null;
            $data['weekly_sheet_id'] = isset($lid) ? $lid : null;
            $data['pid'] = isset($val->prospect_id) ? $val->prospect_id : null;
            $data['homeowner_id'] = isset($val->homeowner_id) ? $val->homeowner_id : null;
            $data['proposal_id'] = isset($val->proposal_id) ? $val->proposal_id : null;
            $data['customer_name'] = isset($val->customer_name) ? $val->customer_name : null;
            $data['customer_address'] = isset($val->customer_address) ? $val->customer_address : null;
            $data['customer_address_2'] = isset($val->customer_address_2) ? $val->customer_address_2 : null;
            $data['customer_city'] = isset($val->customer_city) ? $val->customer_city : null;
            $data['customer_state'] = isset($val->customer_state) ? $val->customer_state : null;
            $data['customer_zip'] = isset($val->customer_zip) ? $val->customer_zip : null;
            $data['customer_email'] = isset($val->customer_email) ? $val->customer_email : null;
            $data['customer_phone'] = isset($val->customer_phone) ? $val->customer_phone : null;
            $data['setter_id'] = isset($val->setter_id) ? $val->setter_id : null;
            $data['employee_id'] = isset($val->employee_id) ? $val->employee_id : null;
            $data['sales_rep_name'] = isset($val->rep_name) ? $val->rep_name : null;
            $data['sales_rep_email'] = isset($val->rep_email) ? $val->rep_email : null;
            $data['install_partner'] = isset($val->install_partner) ? $val->install_partner : null;
            $data['install_partner_id'] = isset($val->install_partner_id) ? $val->install_partner_id : null;
            $data['customer_signoff'] = isset($val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
            $data['m1_date'] = isset($val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
            $data['scheduled_install'] = isset($val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
            $data['m2_date'] = isset($val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
            $data['date_cancelled'] = isset($val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
            $data['return_sales_date'] = isset($val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
            $data['gross_account_value'] = isset($val->gross_account_value) ? $val->gross_account_value : null;
            $data['cash_amount'] = isset($val->cash_amount) ? $val->cash_amount : null;
            $data['loan_amount'] = isset($val->loan_amount) ? $val->loan_amount : null;
            $data['kw'] = isset($val->kw) ? $val->kw : null;
            $data['dealer_fee_percentage'] = isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
            $data['adders'] = isset($val->adders) ? $val->adders : null;
            $data['adders_description'] = isset($val->adders_description) ? $val->adders_description : null;
            $data['funding_source'] = isset($val->funding_source) ? $val->funding_source : null;
            $data['financing_rate'] = isset($val->financing_rate) ? $val->financing_rate : 0.00;
            $data['financing_term'] = isset($val->financing_term) ? $val->financing_term : null;
            $data['product'] = isset($val->product) ? $val->product : null;
            $inserted = LegacyApiRowData::create($data);
        } else {

            // Insert null data in table for alert admin...............................................
            $checkNull = LegacyApiNullData::where('legacy_data_id', $val->id)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
            if (! isset($checkNull) && $checkNull == '') {
                $data['legacy_data_id'] = isset($val->id) ? $val->id : null;
                $data['weekly_sheet_id'] = isset($lid) ? $lid : null;
                $data['pid'] = isset($val->prospect_id) ? $val->prospect_id : null;
                $data['homeowner_id'] = isset($val->homeowner_id) ? $val->homeowner_id : null;
                $data['proposal_id'] = isset($val->proposal_id) ? $val->proposal_id : null;
                $data['customer_name'] = isset($val->customer_name) ? $val->customer_name : null;
                $data['customer_address'] = isset($val->customer_address) ? $val->customer_address : null;
                $data['customer_address_2'] = isset($val->customer_address_2) ? $val->customer_address_2 : null;
                $data['customer_city'] = isset($val->customer_city) ? $val->customer_city : null;
                $data['customer_state'] = isset($val->customer_state) ? $val->customer_state : null;
                $data['customer_zip'] = isset($val->customer_zip) ? $val->customer_zip : null;
                $data['customer_email'] = isset($val->customer_email) ? $val->customer_email : null;
                $data['customer_phone'] = isset($val->customer_phone) ? $val->customer_phone : null;
                $data['setter_id'] = isset($val->setter_id) ? $val->setter_id : null;
                $data['employee_id'] = isset($val->employee_id) ? $val->employee_id : null;
                $data['sales_rep_name'] = isset($val->rep_name) ? $val->rep_name : null;
                $data['sales_rep_email'] = isset($val->rep_email) ? $val->rep_email : null;
                $data['install_partner'] = isset($val->install_partner) ? $val->install_partner : null;
                $data['install_partner_id'] = isset($val->install_partner_id) ? $val->install_partner_id : null;
                $data['customer_signoff'] = isset($val->customer_signoff) && $val->customer_signoff != null ? date('Y-m-d H:i:s', strtotime($val->customer_signoff)) : null;
                $data['m1_date'] = isset($val->m1) ? date('Y-m-d H:i:s', strtotime($val->m1)) : null;
                $data['scheduled_install'] = isset($val->scheduled_install) ? date('Y-m-d H:i:s', strtotime($val->scheduled_install)) : null;
                $data['m2_date'] = isset($val->m2) ? date('Y-m-d H:i:s', strtotime($val->m2)) : null;
                $data['date_cancelled'] = isset($val->date_cancelled) ? date('Y-m-d H:i:s', strtotime($val->date_cancelled)) : null;
                $data['return_sales_date'] = isset($val->return_sales_date) ? date('Y-m-d H:i:s', strtotime($val->return_sales_date)) : null;
                $data['gross_account_value'] = isset($val->gross_account_value) ? $val->gross_account_value : null;
                $data['cash_amount'] = isset($val->cash_amount) ? $val->cash_amount : null;
                $data['loan_amount'] = isset($val->loan_amount) ? $val->loan_amount : null;
                $data['kw'] = isset($val->kw) ? $val->kw : null;
                $data['dealer_fee_percentage'] = isset($val->dealer_fee_percentage) ? $val->dealer_fee_percentage : null;
                $data['adders'] = isset($val->adders) ? $val->adders : null;
                $data['adders_description'] = isset($val->adders_description) ? $val->adders_description : null;
                $data['funding_source'] = isset($val->funding_source) ? $val->funding_source : null;
                $data['financing_rate'] = isset($val->financing_rate) ? $val->financing_rate : 0.00;
                $data['financing_term'] = isset($val->financing_term) ? $val->financing_term : null;
                $data['product'] = isset($val->product) ? $val->product : null;
                $inserted = LegacyApiNullData::create($data);
            }

        }
    }

    public function subroutineThree($val)
    {
        // echo"subroutineThree";die;
        // Determine user roles in sale from Subroutine #1
        $this->subroutineOne($val);
        // return $val;
        // Pull Upfront from user based on closer/setter upfront in profile (Including tiers and rules)
        $rep_email = $val->sales_rep_email;
        // $setterId  = $val->setter_id;
        $closerId = $val->salesMasterProcess->closer1_id;
        $closer2Id = $val->salesMasterProcess->closer2_id;
        $closer = User::where('email', $rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        // echo $rep_email;die;
        // ...........................
        // $position = $closer->position_id;
        $kw = $val->kw;
        $upfrontAmount = isset($closer->upfront_pay_amount) ? $closer->upfront_pay_amount : 0;
        $upfrontType = isset($closer->upfront_sale_type) ? $closer->upfront_sale_type : 0;
        if ($upfrontType == 'per sale') {
            $amount = $upfrontAmount;
        } else {
            $amount = ($upfrontAmount * $kw);
        }
        // return $position;
        // if ($position == 2) {

        $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
        $UpdateData->closer1_m1 = $amount;
        $UpdateData->closer1_id = $UpdateData->closer1_id;
        $UpdateData->closer1_m1_paid_status = 4;
        $UpdateData->save();

        // }
        //  if ($position == 2) {

        //     $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
        //     $UpdateData->closer1_m1 = $amount;
        //     $UpdateData->closer1_id = $closer->id;
        //     $UpdateData->closer1_m1_paid_status = 4;
        //     $UpdateData->save();

        // }

        if ($closer1Id != null && $closer2Id != null) {

            $closer = User::where('id', $setterId)->first();
            if ($closer) {
                $upfrontAmount = $closer->upfront_pay_amount;
                $upfrontType = $closer->upfront_sale_type;

                if ($upfrontType == 'per sale') {
                    $amount = ($upfrontAmount / 2);
                } else {
                    $amount = (($upfrontAmount * $kw) / 2);
                }
                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->closer1_m1 = $amount;
                $UpdateData->closer2_m1 = $amount;
                $UpdateData->closer1_m1_paid_status = 4;
                $UpdateData->save();
            }
        } elseif ($closer) {

            $closer = User::where('id', $closerId)->first();
            if ($closer) {
                $upfrontAmount = $closer->upfront_pay_amount;
                $upfrontType = $closer->upfront_sale_type;

                if ($upfrontType == 'per sale') {
                    $amount = $upfrontAmount;
                } else {
                    $amount = ($upfrontAmount * $kw);
                }
                // echo $amount;die;
                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->closer1_m1 = $amount;
                $UpdateData->closer1_id = $setterId;
                $UpdateData->closer1_m1_paid_status = 4;
                $UpdateData->save();
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
        $positionOverrideSettlementCloser = PositionOverride::where('position_id', 4)->first();
        if ($positionOverrideSettlementcloser->settlement_id == 1) {

            $closer1_id = $saleMasterProcess->closer1_id;
            $closer2_id = $saleMasterProcess->closer2_id;

            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $closer1ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter1_id)->where('status', 'pending')->sum('withhold_amount');

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
                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer1_id', $closer2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $saleMasterProcess->closer2_m1 = 0;
                $saleMasterProcess->closer2_m2 = 0;
                $saleMasterProcess->save();

            }

            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);

        }

        $total_m1_m2_amount = $total_closers_ReconciliationWithholding_amount + $total_closers_ReconciliationWithholding_amount;

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
        // echo"subroutineFive";die;
        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $closer1_id = $saleMasterProcess->closer1_id;
        $closer2_id = $saleMasterProcess->closer2_id;
        $setter1_id = $saleMasterProcess->setter1_id;
        $setter2_id = $saleMasterProcess->setter2_id;

        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting
        \DB::enableQueryLog();
        $positionOverrideCloser = PositionOverride::where('position_id', 2)->first();
        // dd(\DB::getQueryLog()); // Show results of log
        // dd($positionOverrideCloser);

        if ($positionOverrideCloser->settlement_id == 1) {
            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $closer1ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer1_id)->where('status', 'paid')->sum('withhold_amount');

            }
            if ($closer2_id) {

                $closer2ReconciliationWithholding_amount = UserReconciliationWithholding::where('closer_id', $closer2_id)->where('status', 'paid')->sum('withhold_amount');

            }
            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);

        } else {

            // $sattlement_type = "During M2";
            $closer1ReconciliationWithholding_amount = 0;
            $closer2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($closer1_id) {

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer1_id', $closer1_id])->first();

                $closer1_m1 = $saleMasterProcess->closer1_m1;
                $closer1_m2 = $saleMasterProcess->closer1_m2;
                $closer1ReconciliationWithholding_amount = ($closer1_m1 + $closer1_m2);

            }
            if ($closer2_id) {

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'closer2_id', $closer2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $closer2_m1 = $saleMasterProcess->closer2_m1;
                $closer2_m2 = $saleMasterProcess->closer2_m2;
                $closer2ReconciliationWithholding_amount = ($closer2_m1 + $closer2_m2);
            }
            $total_closers_ReconciliationWithholding_amount = ($closer1ReconciliationWithholding_amount + $closer2ReconciliationWithholding_amount);

        }

        // for setter
        $positionOverrideSettlementSetter = PositionOverride::where('position_id', 3)->first();
        if ($positionOverrideSettlementSetter->settlement_id == 1) {

            $setter1ReconciliationWithholding_amount = 0;
            $setter2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($setter1_id) {

                $setter1ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter1_id)->where('status', 'paid')->sum('withhold_amount');

            }
            if ($setter2_id) {

                $setter2ReconciliationWithholding_amount = UserReconciliationWithholding::where('setter_id', $setter2_id)->where('status', 'paid')->sum('withhold_amount');

            }
            $total_setters_ReconciliationWithholding_amount = ($setter1ReconciliationWithholding_amount + $setter2ReconciliationWithholding_amount);

        } else {
            // $sattlement_type = "During M2";
            $setter1ReconciliationWithholding_amount = 0;
            $setter2ReconciliationWithholding_amount = 0;

            // Calculate closer amount
            if ($setter1_id) {

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'setter1_id', $setter1_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $setter1_m1 = $saleMasterProcess->setter1_m1;
                $setter1_m2 = $saleMasterProcess->setter1_m2;
                $setter1ReconciliationWithholding_amount = ($setter1_m1 + $setter1_m2);
            }
            if ($setter2_id) {

                $saleMasterProcess = SaleMasterProcess::where(['pid' => $checked->pid, 'setter1_id', $setter2_id])->first();
                // System adds total of clawback (deduction as a negative amount) to reconciliation
                $setter2_m1 = $saleMasterProcess->setter2_m1;
                $setter2_m2 = $saleMasterProcess->setter2_m2;
                $setter2ReconciliationWithholding_amount = ($setter2_m1 + $setter2_m2);

            }

            $total_setters_ReconciliationWithholding_amount = ($setter1ReconciliationWithholding_amount + $setter2ReconciliationWithholding_amount);

        }

        $total_m1_m2_amount = ($total_closers_ReconciliationWithholding_amount + $total_setters_ReconciliationWithholding_amount);

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

        return $total_m1_m2_amount;

    }

    public function subroutineSix($checked)
    {
        // Run Subroutine 1 ..............................................
        $rep_email = $checked->sales_rep_email;
        // $setterId = $checked->setter_id;
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;

        $customerRedline = $checked->redline;
        $customerState = $checked->customer_state;
        $kw = $checked->kw;
        $epc = $checked->epc;

        $approvedDate = $checked->customer_signoff;
        $m2_this_week = isset($checked->m2_amount) ? $checked->m2_amount : '';
        $netEpc = $checked->net_epc;

        // Identify Closer ( Compare Rep_email with the Work Email (Sequifi)
        $closer = User::where('email', $rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        // customer state Id..................................................
        $state = State::where('state_code', $customerState)->first();
        $customerStateId = isset($state->id) ? $state->id : 0;

        // Get Pull user Redlines from profile info
        // dd($approvedDate);die;

        if ($approvedDate != null) {
            // echo $approvedDate;die;
            if (isset($closer) && $closer != '') {
                // echo"dasads";die;
                $closer_redline = $closer->redline;
                $commissions = $closer->commission;
                $closer_redline_amount = $closer->redline_amount;
                $closer_redline_type = $closer->redline_type;
                $closerStateId = $closer->state_id;
                // Check applicable redline Check approval Date of sale and apply applicable redline based on user redline history
                $customer_state = $checked->customer_state;
                $location = Locations::where('id', $customerStateId)->first();
                $customerRedline = isset($location->redline_standard) ? $location->redline_standard : null;
                if ($closerStateId == $customerStateId) {

                    return $closer_redline;

                } else {

                    $closerLocation = Locations::where('id', $closerStateId)->first();
                    $closerStateRedline = isset($closerLocation->redline_standard) ? $closerLocation->redline_standard : null;
                    // closer_redline
                    $difference_redline = ($customerRedline - $closerStateRedline);
                    $redline = $closer_redline + $difference_redline;

                    return $redline;
                }
            }
        }

    }

    public function subroutineEight($checked)
    {
        $rep_email = $checked->sales_rep_email;
        $closer2_id = $checked->salesMasterProcess->closer2_id;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;

        $customerRedline = $this->subroutineSix($checked);
        // Get Pull user Redlines from profile info

        $closer = User::where('email', $rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        $closer_redline = isset($closer->redline) ? $closer->redline : null;
        $closer_redline_amount = isset($closer->redline_amount) ? $closer->redline_amount : null;
        $closer_redline_type = isset($closer->redline_type) ? $closer->redline_type : null;
        $closerStateId = $closer->state_id;
        $closer_commission = 0;
        if ($closer && $closer2_id != null) {

            // Check applicable redline Check approval Date of sale and apply applicable redline based on user redline history
            $locationRedline = Locations::where('state_id', $closerStateId)->first();

            $saleRedlineStandard = $locationRedline->redline_standard;

            $setterCommission = SaleMasterProcess::where('pid', $checked->pid)->first();
            $commission = $setterCommission->setter1_commission + $setterCommission->setter2_commission;

            $closer1_commission = ((($netEpc - $customerRedline) * $kw * 1000) - ($commission / 2)) * 0.5;
            $closer2_commission = ((($netEpc - $customerRedline) * $kw * 1000) - ($commission / 2)) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            $UpdateData->closer1_commission = $closer1_commission;
            $UpdateData->closer2_commission = $closer2_commission;
            $UpdateData->mark_account_status_id = 3;
            $UpdateData->save();
            $closer_commission = ($closer1_commission + $closer2_commission);
        } elseif ($closer) {

            // Check applicable redline Check approval Date of sale and apply applicable redline based on user redline history
            $locationRedline = Locations::where('state_id', $closerStateId)->first();

            $saleRedlineStandard = isset($locationRedline->redline_standard) ? $locationRedline->redline_standard : null;
            $setterCommission = SaleMasterProcess::where('pid', $checked->pid)->first();
            $commission = $setterCommission->setter1_commission + $setterCommission->setter2_commission;
            $closer_commission = (($netEpc - $customerRedline) * $kw * 1000) - $commission;
            // echo $closer_commission;die;
            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            $UpdateData->closer1_commission = $closer_commission;
            $UpdateData->mark_account_status_id = 3;
            $UpdateData->save();
        }
        $commissionData['closer_commission'] = $closer_commission;
        $commissionData['setter_commission'] = $commission;

        return $commissionData;
    }

    public function subroutineNine($checked)
    {
        // echo"subroutineNine";die;
        $totalCommission = $this->subroutineEight($checked);
        $closer = User::where('email', $checked->sales_rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        $setterId = $checked->salesMasterProcess->setter1_id;
        if ($closer) {

            // $upfront = PayRollProcessing::where('user_id', $closer->id)->get();
            // $totalUpfront =isset($upfront->commission)?$upfront->commission:'';

            $totalWithholding = $this->subroutineTen($checked);

            $saleData = SaleMasterProcess::where('pid', $checked->pid)->first();
            $closerCommission = $totalCommission['closer_commission'];

            $closerReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer->id)->sum('withhold_amount');

            $closerDueM2 = ($closerCommission - $saleData->closer1_m1 - $closerReconciliationWithholding);
            if ($closerDueM2 > 0) {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->closer1_m2 = $closerDueM2;
                $data->closer1_m2_paid_status = 5;
                $data->save();

            } else {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->closer1_m2 = $closerDueM2;
                $data->closer1_m2_paid_status = 5;
                $data->save();
            }

        }
        if (isset($setterId) && $setterId != '') {
            // $upfront = PayRollProcessing::where('user_id',$setterId)->get();
            // $totalUpfront = $upfront->commission;

            $totalWithholding = $this->subroutineTen($checked);
            $saleData = SaleMasterProcess::where('pid', $checked->pid)->first();

            $setterCommission = $totalCommission['setter_commission'];

            $setterReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->sum('withhold_amount');

            $setterDueM2 = ($setterCommission - $saleData->setter1_m1 - $setterReconciliationWithholding);

            if ($setterDueM2 > 0) {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->setter1_m2 = $setterDueM2;
                $data->setter1_m2_paid_status = 5;
                $data->save();

            } else {
                $data = SaleMasterProcess::where('pid', $checked->pid)->first();
                $data->setter1_m2 = $setterDueM2;
                $data->setter1_m2_paid_status = 5;
                $data->save();

            }
        }
    }

    public function subroutineTen($checked)
    {
        // echo"subroutineTen";die;
        // return $checked;
        $closer = User::where('email', $checked->sales_rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;

        $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closer->id)->sum('withhold_amount');

        $backendSettings = BackendSetting::with('Backend')->first();
        $commissionWithHeldAmountPerWatt = $backendSettings->commission_withheld;
        $maximumWithHeldAmount = $backendSettings->maximum_withheld;

        if ($closerReconciliationWithholdAmount >= $maximumWithHeldAmount) {
            // No withholding calculations required.  System proceeds to following steps
        } else {

            $subroutineEight = $this->subroutineEight($checked);
            $closerCommission = $subroutineEight['closer_commission'];
            $setterCommission = $subroutineEight['setter_commission'];

            $commissionSettingAmount = $commissionWithHeldAmountPerWatt * $kw;
            $closerWithheldCheck = ($closerReconciliationWithholdAmount + $commissionSettingAmount);
            if ($closerWithheldCheck > $maximumWithHeldAmount) {
                $commissionSettingAmount = ($maximumWithHeldAmount - $closerReconciliationWithholdAmount);
            }
            // $closerWithheld = $closerReconciliationWithholdAmount-$commissionSettingAmount;
            $closerWithheld = $commissionSettingAmount;

            // Total is added to reconciliation withholdings and system proceeds to following steps

            $reconData = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer->id)->first();
            if (isset($reconData) && $reconData != '') {
                $reconData->withhold_amount = $closerWithheld;
                $reconData->save();
            } else {
                $data = [
                    'pid' => $checked->pid,
                    'closer_id' => $closer->id,
                    'withhold_amount' => $closerWithheld,
                ];
                $backendSettings = UserReconciliationWithholding::create($data);

            }
            // Calculate total to be withheld

        }
    }

    public function SubroutineTwelve($checked)
    {
        // echo"SubroutineTwelve";die;
        $subroutineEight = $this->subroutineEight($checked);
        $closerCommission = $subroutineEight['closer_commission'];
        $setterCommission = $subroutineEight['setter_commission'];

        $closer = User::where('email', $checked->sales_rep_email)->first();
        if (empty($closer)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->value('user_id');
            if (! empty($additional_user_id)) {
                $closer = User::where('id', $additional_user_id)->first();
            }
        }
        $closer2_id = $checked->salesMasterProcess->closer2_id;

        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2_id = $checked->salesMasterProcess->setter2_id;

        $closerReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer->id)->sum('withhold_amount');
        $setterReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->sum('withhold_amount');

        // Calculate difference between 2 previous steps
        $data = SaleMasterProcess::where('pid', $checked->pid)->first();

        if ($checked->salesMasterProcess->closer1_id !== null && $checked->salesMasterProcess->closer2_id !== null) {
            $totalCloserAmount = $data->closer1_m1 + $data->closer2_m1;
        } else {
            $totalCloserAmount = $data->closer1_m1;
        }

        if ($checked->salesMasterProcess->setter1_id !== null && $checked->salesMasterProcess->setter2_id !== null) {
            $totalSetterAmount = $data->setter1_m1 + $data->setter2_m1;
        } else {
            $totalSetterAmount = $data->setter1_m1;
        }

        $closerValue = $closerCommission - $totalCloserAmount - $closerReconciliationWithholding;

        $setterValue = $setterCommission - $totalSetterAmount - $setterReconciliationWithholding;
        if ($closerValue >= 0) {
            // Value is sent to current payroll as DEDUCTION (And annotated as adjustment to this sal)
            $data = [
                'pid' => $checked->pid,
                'closer_id' => $closer->id,
                'amount' => $closerValue,
                'status' => 'Positive',
            ];
            if ($closer2_id) {
                $data = [
                    'pid' => $checked->pid,
                    'closer_id' => $closer->id,
                    'amount' => $closerValue,
                    'status' => 'Positive',
                ];

            }
            $backendSettings = DeductionAlert::create($data);
        } else {
            $data = [
                'pid' => $checked->pid,
                'closer_id' => $closer->id,
                'amount' => $closerValue,
                'status' => 'Negative',
            ];
            if ($closer2_id) {
                $data = [
                    'pid' => $checked->pid,
                    'closer_id' => $closer2_id,
                    'amount' => $closerValue,
                    'status' => 'Negative',
                ];

            }
            $backendSettings = DeductionAlert::create($data);
        }

        if ($setterValue >= 0) {
            $data = [
                'setter_id' => $setterId,
                'pid' => $checked->pid,
                'amount' => $setterValue,
                'status' => 'Positive',
            ];
            if ($setter2_id) {
                $data = [
                    'pid' => $checked->pid,
                    'setter_id' => $setter2_id,
                    'amount' => $setterValue,
                    'status' => 'Positive',
                ];

            }
            $backendSettings = DeductionAlert::create($data);
        } else {

            $data = [
                'setter_id' => $setterId,
                'pid' => $checked->pid,
                'amount' => $setterValue,
                'status' => 'Negative',
            ];
            if ($setter2_id) {
                $data = [
                    'pid' => $checked->pid,
                    'setter_id' => $setter2_id,
                    'amount' => $setterValue,
                    'status' => 'Negative',
                ];

            }
            $backendSettings = DeductionAlert::create($data);
        }

    }
}
