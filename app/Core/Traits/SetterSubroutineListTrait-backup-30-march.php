<?php

namespace App\Core\Traits;

use App\Models\BackendSetting;
use App\Models\ClawbackSettlement;
use App\Models\DeductionAlert;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\Locations;
use App\Models\PositionOverride;
use App\Models\PositionReconciliations;
use App\Models\SaleMasterProcess;
use App\Models\State;
use App\Models\User;
use App\Models\UserReconciliationWithholding;

trait SetterSubroutineListTrait
{
    use PayRollCommissionTrait;

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
            $data['date_d'] = isset($val->date_d) ? date('Y-m-d H:i:s', strtotime($val->date_d)) : null;
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
            $checkNull = LegacyApiNullData::where('legacy_data_id', $val->id)->first();
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
                $data['date_d'] = isset($val->date_d) ? date('Y-m-d H:i:s', strtotime($val->date_d)) : null;
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
        $closerId = $val->salesMasterProcess->closer1_id;
        $closer2Id = $val->salesMasterProcess->closer2_id;
        $setterId = $val->salesMasterProcess->setter1_id;
        $setter2Id = $val->salesMasterProcess->setter2_id;
        $kw = $val->kw;

        if ($setterId != null && $setter2Id != null) {

            $setter = User::where('id', $setterId)->first();
            if ($setter) {
                $upfrontAmount = $setter->upfront_pay_amount;
                $upfrontType = $setter->upfront_sale_type;

                if ($upfrontType == 'per sale') {
                    $amount = ($upfrontAmount / 2);
                } else {
                    $amount = (($upfrontAmount * $kw) / 2);
                }
                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->setter1_m1 = $amount;
                $UpdateData->setter2_m1 = $amount;
                $UpdateData->setter1_m1_paid_status = 4;
                $UpdateData->setter2_m1_paid_status = 4;
                $UpdateData->save();

            }

        } elseif ($setterId) {

            $setter = User::where('id', $setterId)->first();
            if ($setter) {
                $upfrontAmount = $setter->upfront_pay_amount;
                $upfrontType = $setter->upfront_sale_type;

                if ($upfrontType == 'per sale') {
                    $amount = $upfrontAmount;
                } else {
                    $amount = ($upfrontAmount * $kw);
                }
                // echo $amount;die;
                $UpdateData = SaleMasterProcess::where('pid', $val->pid)->first();
                $UpdateData->setter1_m1 = $amount;
                $UpdateData->setter1_m1_paid_status = 4;
                $UpdateData->save();
            }
        }

        if ($closerId != null && $closer2Id != null) {

            $closer = User::where('id', $closerId)->first();
            if ($closer->upfront_pay_amount != null) {
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
                $UpdateData->closer2_m1_paid_status = 4;
                $UpdateData->save();

            }

        } elseif ($closerId) {

            $closer = User::where('id', $closerId)->first();
            if ($closer->upfront_pay_amount != null) {
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

    public function subroutineFive_old($checked)
    {

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $closer1_id = $saleMasterProcess->closer1_id;
        $closer2_id = $saleMasterProcess->closer2_id;
        $setter1_id = $saleMasterProcess->setter1_id;
        $setter2_id = $saleMasterProcess->setter2_id;

        // Payment Setting is Reconciliation OR M2 ?  >> Position Setting
        // \DB::enableQueryLog();
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

        $closer1Amount = ($closer1_m1 + $closer1_m2 + $closer1_commission);
        $closer2Amount = ($closer2_m1 + $closer2_m2 + $closer2_commission);
        $setter1Amount = ($setter1_m1 + $setter1_m2 + $setter1_commission);
        $setter2Amount = ($setter2_m1 + $setter2_m2 + $setter2_commission);

        $closer1Withheld_amount = 0;
        $closer2Withheld_amount = 0;
        $setter1Withheld_amount = 0;
        $setter2Withheld_amount = 0;
        if ($closerId != null) {
            $closer1Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->first();
            if ($closer1Withheld) {
                if ($closer1Withheld->status == 'paid') {
                    $closer1Withheld_amount = $closer1Withheld->withhold_amount;
                } else {
                    $closer1Withheld->withhold_amount = 0;
                    $closer1Withheld->status = 'clawdback';
                    $closer1Withheld->save();

                    $closer1Withheld_amount = 0;
                }
            }

        }

        if ($closer2Id != null) {
            $closer2Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->first();
            if ($closer2Withheld) {
                if ($closer2Withheld->status == 'paid') {
                    $closer2Withheld_amount = $closer2Withheld->withhold_amount;
                } else {
                    $closer2Withheld->withhold_amount = 0;
                    $closer2Withheld->status = 'clawdback';
                    $closer2Withheld->save();

                    $closer2Withheld_amount = 0;
                }
            }
        }

        if ($setterId != null) {
            $setter1Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->first();
            if ($setter1Withheld) {
                if ($setter1Withheld->status == 'paid') {
                    $setter1Withheld_amount = $setter1Withheld->withhold_amount;
                } else {
                    $setter1Withheld->withhold_amount = 0;
                    $setter1Withheld->status = 'clawdback';
                    $setter1Withheld->save();

                    $setter1Withheld_amount = 0;
                }
            }
        }

        if ($setter2Id != null) {
            $setter2Withheld = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->first();
            if ($setter2Withheld) {
                if ($setter2Withheld->status == 'paid') {
                    $setter2Withheld_amount = $setter2Withheld->withhold_amount;
                } else {
                    $setter2Withheld->withhold_amount = 0;
                    $setter2Withheld->status = 'clawdback';
                    $setter2Withheld->save();

                    $setter2Withheld_amount = 0;
                }
            }
        }

        $positionCloser = PositionReconciliations::where('position_id', 2)->first()->clawback_settlement;
        $positionSetter = PositionReconciliations::where('position_id', 3)->first()->clawback_settlement;

        if ($positionCloser == 'reconciliation') {
            if ($closerId != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $closerId,
                        'position_id' => 2,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($closer1Amount + $closer1Withheld_amount),
                        'clawback_type' => 'reconciliation',
                    ]
                );
            }
            if ($closer2Id != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $closer2Id,
                        'position_id' => 2,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($closer2Amount + $closer2Withheld_amount),
                        'clawback_type' => 'reconciliation',
                    ]
                );
            }

        } else {
            if ($closerId != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $closerId,
                        'position_id' => 2,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($closer1Amount + $closer1Withheld_amount),
                        'clawback_type' => 'next payroll',
                    ]
                );
            }
            if ($closer2Id != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $closer2Id,
                        'position_id' => 2,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($closer2Amount + $closer2Withheld_amount),
                        'clawback_type' => 'next payroll',
                    ]
                );
            }

        }

        if ($positionSetter == 'reconciliation') {
            if ($setterId != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $setterId,
                        'position_id' => 3,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($setter1Amount + $setter1Withheld_amount),
                        'clawback_type' => 'reconciliation',
                    ]
                );
            }
            if ($setter2Id != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $setter2Id,
                        'position_id' => 3,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($setter2Amount + $setter2Withheld_amount),
                        'clawback_type' => 'reconciliation',
                    ]
                );
            }

        } else {
            if ($setterId != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $setterId,
                        'position_id' => 3,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($setter1Amount + $setter1Withheld_amount),
                        'clawback_type' => 'next payroll',
                    ]
                );
            }
            if ($setter2Id != null) {
                ClawbackSettlement::create(
                    [
                        'user_id' => $setter2Id,
                        'position_id' => 3,
                        'pid' => $checked->pid,
                        'clawback_amount' => ($setter2Amount + $setter2Withheld_amount),
                        'clawback_type' => 'next payroll',
                    ]
                );
            }

        }

        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
        $saleMasterProcess->mark_account_status_id = 1;
        $saleMasterProcess->save();

        // dd($positionSetter);

    }

    public function subroutineSix($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        // $customerRedline = $checked->redline;
        $saleState = $checked->customer_state;
        $approvedDate = $checked->customer_signoff;

        // customer state Id..................................................
        $state = State::where('state_code', $saleState)->first();
        $saleStateId = isset($state->id) ? $state->id : 0;
        // Check applicable redline Check approval Date of sale and apply applicable redline based on user redline history
        $location = Locations::where('state_id', $saleStateId)->first();
        $saleStandardRedline = $location->redline_standard;

        if ($approvedDate != null) {
            $data['closer1_redline'] = '0';
            $data['closer2_redline'] = '0';
            $data['setter1_redline'] = '0';
            $data['setter2_redline'] = '0';
            if ($closerId && $closer2Id) {

                $closer1 = User::where('id', $closerId)->first();
                $closer1_redline = $closer1->redline;
                $closer1StateId = $closer1->state_id;

                if ($closer1->redline_amount_type == 'Fixed') {

                    $data['closer1_redline'] = $closer1_redline;

                } else {
                    if ($closer1StateId == $saleStateId) {

                        $data['closer1_redline'] = $closer1_redline;

                    } else {
                        $closerLocation = Locations::where('state_id', $closer1StateId)->first();
                        $closerStateRedline = $closerLocation->redline_standard;
                        // closer_redline
                        $difference_redline = ($saleStandardRedline - $closerStateRedline);
                        $redline = $closer1_redline + $difference_redline;
                        $data['closer1_redline'] = $redline;

                    }
                }

                $closer2 = User::where('id', $closer2Id)->first();
                $closer2_redline = $closer2->redline;
                $closer2StateId = $closer2->state_id;
                if ($closer2->redline_amount_type == 'Fixed') {

                    $data['closer2_redline'] = $closer2_redline;

                } else {
                    if ($closer2StateId == $saleStateId) {

                        $data['closer2_redline'] = $closer2_redline;

                    } else {
                        $closerLocation = Locations::where('state_id', $closer2StateId)->first();
                        $closerStateRedline = $closerLocation->redline_standard;
                        // closer_redline
                        $difference_redline = ($saleStandardRedline - $closerStateRedline);
                        $redline = $closer2_redline + $difference_redline;
                        $data['closer2_redline'] = $redline;
                    }
                }

            } elseif ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $closer_redline = $closer->redline;
                $closerStateId = $closer->state_id;

                if ($closer->redline_amount_type == 'Fixed') {

                    $data['closer1_redline'] = $closer_redline;

                } else {
                    if ($closerStateId == $saleStateId) {

                        $data['closer1_redline'] = $closer_redline;

                    } else {

                        $closerLocation = Locations::where('state_id', $closerStateId)->first();
                        $closerStateRedline = $closerLocation->redline_standard;
                        // closer_redline
                        $difference_redline = ($saleStandardRedline - $closerStateRedline);
                        $redline = $closer_redline + $difference_redline;
                        $data['closer1_redline'] = $redline;
                    }
                }

            }

            if ($setterId && $setter2Id) {

                $setter = User::where('id', $setterId)->first();
                $setter_redline = $setter->redline;
                $setterStateId = $setter->state_id;

                if ($setter->redline_amount_type == 'Fixed') {

                    $data['setter1_redline'] = $setter_redline;

                } else {
                    if ($setterStateId == $saleStateId) {

                        $data['setter1_redline'] = $setter_redline;

                    } else {

                        $setterLocation = Locations::where('state_id', $setterStateId)->first();
                        $setterStateRedline = $setterLocation->redline_standard;

                        $difference_redline = ($saleStandardRedline - $setterStateRedline);
                        $redline = $setter_redline + $difference_redline;

                        $data['setter1_redline'] = $redline;
                    }
                }

                $setter2 = User::where('id', $setter2Id)->first();
                $setter2_redline = $setter2->redline;
                $setter2StateId = $setter2->state_id;

                if ($setter2->redline_amount_type == 'Fixed') {

                    $data['setter2_redline'] = $setter2_redline;

                } else {
                    if ($setter2StateId == $saleStateId) {

                        $data['setter2_redline'] = $setter2_redline;

                    } else {

                        $setterLocation = Locations::where('state_id', $setter2StateId)->first();
                        $setter2StateRedline = $setterLocation->redline_standard;

                        $difference_redline = ($saleStandardRedline - $setter2StateRedline);
                        $redline = $setter2_redline + $difference_redline;

                        $data['setter2_redline'] = $redline;
                    }
                }

            }
            if ($setterId) {
                $setter = User::where('id', $setterId)->first();
                $setter_redline = $setter->redline;
                $setterStateId = $setter->state_id;

                if ($setter->redline_amount_type == 'Fixed') {

                    $data['setter1_redline'] = $setter_redline;

                } else {
                    if ($setterStateId == $saleStateId) {

                        $data['setter1_redline'] = $setter_redline;

                    } else {

                        $setterLocation = Locations::where('state_id', $setterStateId)->first();
                        $setterStateRedline = $setterLocation->redline_standard;

                        $difference_redline = ($saleStandardRedline - $setterStateRedline);
                        $redline = $setter_redline + $difference_redline;

                        $data['setter1_redline'] = $redline;
                    }
                }

            }

            // dd($data);
            return $data;
        }

    }

    public function subroutineEight($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $kw = $checked->kw;
        $netEpc = $checked->net_epc;

        // Get Pull user Redlines from subroutineSix
        $redline = $this->subroutineSix($checked);

        // Calculate setter & closer commission
        $setter_commission = 0;
        if ($setterId != null && $setter2Id != null) {

            $setter = User::where('id', $setterId)->first();
            $commission_percentage = $setter->commission;

            $setter1_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;

            $setter2_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->setter1_commission == 0) {
                $UpdateData->setter1_commission = $setter1_commission;
                $UpdateData->setter2_commission = $setter2_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();

                $this->updateCommission($setterId, 3, $setter1_commission);
                $this->updateCommission($setter2Id, 3, $setter2_commission);
            }

            $setter_commission = ($setter1_commission + $setter2_commission);
        } elseif ($setterId) {

            $setter = User::where('id', $setterId)->first();
            $commission_percentage = $setter->commission; // percenge

            $setter_commission = (($netEpc - $redline['setter1_redline']) * $kw * 1000 * $commission_percentage / 100);

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->setter1_commission == 0) {
                $UpdateData->setter1_commission = $setter_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();
                $this->updateCommission($setterId, 3, $setter_commission);
            }

        }

        $closer_commission = 0;
        if ($closerId != null && $closer2Id != null) {

            $closer1_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) - ($setter_commission / 2)) * 0.5;
            $closer2_commission = ((($netEpc - $redline['closer1_redline']) * $kw * 1000) - ($setter_commission / 2)) * 0.5;

            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->closer1_commission == 0) {
                $UpdateData->closer1_commission = $closer1_commission;
                $UpdateData->closer2_commission = $closer2_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();

                $this->updateCommission($closerId, 2, $closer1_commission);
                $this->updateCommission($closer2Id, 2, $closer2_commission);
            }

            $closer_commission = ($closer1_commission + $closer2_commission);

        } elseif ($closerId) {

            $closer_commission = (($netEpc - $redline['closer1_redline']) * $kw * 1000) - $setter_commission;
            $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
            if ($UpdateData->closer1_commission == 0) {
                $UpdateData->closer1_commission = $closer_commission;
                $UpdateData->mark_account_status_id = 3;
                $UpdateData->save();
                $this->updateCommission($closerId, 2, $closer_commission);
            }

        }

        // $saleProcess = SaleMasterProcess::where('pid',$checked->pid)->first();
        // $closer_commission = ($saleProcess->closer1_commission + $saleProcess->closer2_commission);

        $commissiondata['closer_commission'] = $closer_commission;
        $commissiondata['setter_commission'] = $setter_commission;

        // dd($commissiondata);
        return $commissiondata;

    }

    public function subroutineNine($checked)
    {
        // $totalCommission = $this->subroutineEight($checked);

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        $totalWithholding = $this->subroutineTen($checked);
        $saleData = SaleMasterProcess::where('pid', $checked->pid)->first();

        if ($setterId != null) {

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

        }

    }

    public function subroutineTen($checked)
    {
        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;
        $kw = $checked->kw;

        // $backendSettingForMax = BackendSetting::with('Backend')->first();
        // $commissionWithHeldAmountPerWatt = $backendSettingForMax->commission_withheld;
        // $maximumWithHeldAmount = $backendSettingForMax->maximum_withheld;

        $closerWithheldForMax = PositionReconciliations::where('position_id', 2)->first();
        $closerWithHeldType = $closerWithheldForMax->commission_type;
        $closerWithHeldAmount = $closerWithheldForMax->commission_withheld;
        $closerMaxWithHeldAmount = $closerWithheldForMax->maximum_withheld;

        $setterWithheldForMax = PositionReconciliations::where('position_id', 3)->first();
        $setterWithHeldType = $setterWithheldForMax->commission_type;
        $setterWithHeldAmount = $setterWithheldForMax->commission_withheld;
        $setterMaxWithHeldAmount = $setterWithheldForMax->maximum_withheld;

        if ($setterId != null) {
            $setterReconciliationWithholdAmount = UserReconciliationWithholding::where('setter_id', $setterId)->sum('withhold_amount');
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

                }

            }
        }

        if ($setter2Id != null) {
            $setter2ReconciliationWithholdAmount = UserReconciliationWithholding::where('setter_id', $setter2Id)->sum('withhold_amount');
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

                }

            }
        }

        if ($closerId != null) {
            $closerReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closerId)->sum('withhold_amount');

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

                }

            }
        }

        if ($closer2Id != null) {
            $closer2ReconciliationWithholdAmount = UserReconciliationWithholding::where('closer_id', $closer2Id)->sum('withhold_amount');

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

                }

            }
        }

    }

    public function SubroutineTwelve_old($checked)
    {
        // $subroutineEight = $this->subroutineEight($checked);
        // $closerCommission =  $subroutineEight['closer_commission'];
        // $setterCommission =  $subroutineEight['setter_commission'];

        $closerId = $checked->salesMasterProcess->closer1_id;
        $closer2Id = $checked->salesMasterProcess->closer2_id;
        $setterId = $checked->salesMasterProcess->setter1_id;
        $setter2Id = $checked->salesMasterProcess->setter2_id;

        // Calculate difference between 2 previous steps
        $dataSale = SaleMasterProcess::where('pid', $checked->pid)->first();
        if ($closerId != null) {
            $closerReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closerId)->sum('withhold_amount');
            $totalCloserAmount = ($dataSale->closer1_m1 + $dataSale->closer1_m2);
            $closerValue = $dataSale->closer1_commission - ($totalCloserAmount + $closerReconciliationWithholding);
            if ($closerValue >= 0) {
                // Value is sent to current payroll as DEDUCTION (And annotated as adjustment to this sal)
                $data1 = [
                    'pid' => $checked->pid,
                    'closer_id' => $closerId,
                    'amount' => $closerValue,
                    'status' => 'Positive',
                ];
                $backendSettings = DeductionAlert::create($data1);

            } else {
                $data1 = [
                    'pid' => $checked->pid,
                    'closer_id' => $closerId,
                    'amount' => $closerValue,
                    'status' => 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data1);
            }

        }

        if ($closer2Id != null) {
            $closer2ReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('closer_id', $closer2Id)->sum('withhold_amount');
            $totalCloser2Amount = ($dataSale->closer2_m1 + $dataSale->closer2_m2);
            $closer2Value = $dataSale->closer2_commission - ($totalCloser2Amount + $closer2ReconciliationWithholding);
            if ($closer2Value >= 0) {
                $data2 = [
                    'pid' => $checked->pid,
                    'closer_id' => $closer2Id,
                    'amount' => $closer2Value,
                    'status' => 'Positive',
                ];
                $backendSettings = DeductionAlert::create($data2);
            } else {
                $data2 = [
                    'pid' => $checked->pid,
                    'closer_id' => $closer2Id,
                    'amount' => $closer2Value,
                    'status' => 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data2);
            }

        }

        if ($setterId != null) {
            $setterReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setterId)->sum('withhold_amount');
            $totalSetterAmount = ($dataSale->setter1_m1 + $dataSale->setter1_m2);
            $setterValue = $dataSale->setter1_commission - ($totalSetterAmount + $setterReconciliationWithholding);
            if ($setterValue >= 0) {
                $data3 = [
                    'pid' => $checked->pid,
                    'setter_id' => $setterId,
                    'amount' => $setterValue,
                    'status' => 'Positive',
                ];
                $backendSettings = DeductionAlert::create($data3);
            } else {

                $data3 = [
                    'pid' => $checked->pid,
                    'setter_id' => $setterId,
                    'amount' => $setterValue,
                    'status' => 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data3);
            }

        }

        if ($setter2Id != null) {
            $setter2ReconciliationWithholding = UserReconciliationWithholding::where('pid', $checked->pid)->where('setter_id', $setter2Id)->sum('withhold_amount');
            $totalSetter2Amount = ($dataSale->setter2_m1 + $dataSale->setter2_m2);
            $setter2Value = $dataSale->setter2_commission - ($totalSetter2Amount + $setter2ReconciliationWithholding);
            if ($setter2Value >= 0) {
                $data4 = [
                    'pid' => $checked->pid,
                    'setter_id' => $setter2Id,
                    'amount' => $setter2Value,
                    'status' => 'Positive',
                ];
                $backendSettings = DeductionAlert::create($data4);
            } else {

                $data4 = [
                    'pid' => $checked->pid,
                    'setter_id' => $setter2Id,
                    'amount' => $setter2Value,
                    'status' => 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data4);
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
                $backendSettings = DeductionAlert::create($data1);

            }

            if ($closer2Id != null) {
                $closer2Commission = ($closerCommission / 2);
                $closer2Value = ($closer2Commission - $dataSale->closer2_commission);
                $data2 = [
                    'pid' => $checked->pid,
                    'user_id' => $closer2Id,
                    'position_id' => 2,
                    'amount' => $closer2Value,
                    'status' => ($closer2Value >= 0) ? 'Positive' : 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data2);

            }

        } elseif ($closerId) {

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
            $backendSettings = DeductionAlert::create($data1);
        }

        if ($setterId != null && $setter2Id != null) {
            if ($setterId != null) {
                $setter1Commission = ($setterCommission / 2);
                $setterValue = ($setter1Commission - $dataSale->setter1_commission);
                $data3 = [
                    'pid' => $checked->pid,
                    'user_id' => $setterId,
                    'position_id' => 3,
                    'amount' => $setterValue,
                    'status' => ($setterValue >= 0) ? 'Positive' : 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data3);
            }

            if ($setter2Id != null) {
                $setter2Commission = ($setterCommission / 2);
                $setter2Value = ($setter2Commission - $dataSale->setter2_commission);
                $data4 = [
                    'pid' => $checked->pid,
                    'user_id' => $setter2Id,
                    'position_id' => 3,
                    'amount' => $setter2Value,
                    'status' => ($setter2Value >= 0) ? 'Positive' : 'Negative',
                ];
                $backendSettings = DeductionAlert::create($data4);
            }

        } elseif ($setterId) {
            $setter1Commission = $setterCommission;
            $setterValue = ($setter1Commission - $dataSale->setter1_commission);
            $data3 = [
                'pid' => $checked->pid,
                'user_id' => $setterId,
                'position_id' => 3,
                'amount' => $setterValue,
                'status' => ($setterValue >= 0) ? 'Positive' : 'Negative',
            ];
            $backendSettings = DeductionAlert::create($data3);

        }

    }
}
