<?php

namespace App\Core\Traits;

use App\Models\CompanyProfile;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRawDataHistory;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrides;

trait EditSaleTrait
{
    use PayFrequencyTrait, PayRollCommissionTrait;
    use ReconciliationPeriodTrait;

    public function updateSalesData($userID, $position_id, $pid)
    {
        $commission = UserCommission::where(['user_id' => $userID, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('amount_type', ['m1', 'm2'])->first();
        if ($commission) {
            UserCommission::where(['user_id' => $userID, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('amount_type', ['m1', 'm2'])->delete();
            UserOverrides::where(['sale_user_id' => $userID, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->delete();
        }
        $reconciliation = UserCommission::where(['user_id' => $userID, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
        if ($reconciliation) {
            $reconPaid = ReconCommissionHistory::where(['user_id' => $userID, 'pid' => $pid, 'type' => 'reconciliation', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
            if (! $reconPaid) {
                $reconciliation->delete();
            }
        }
        $reconciliations = UserOverrides::where(['sale_user_id' => $userID, 'pid' => $pid, 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get();
        foreach ($reconciliations as $recon) {
            $reconPaid = ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $recon->user_id, 'overrider' => $recon->sale_user_id, 'type' => $recon->type, 'during' => $recon->during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
            if (! $reconPaid) {
                $recon->delete();
            }
        }
    }

    public function m1dateSalesData($pid)
    {
        $m1Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
        $m1Recon = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first();
        if (! $m1Comm && ! $m1Recon) {
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                $saleMasters->closer1_m1 = 0;
                $saleMasters->closer2_m1 = 0;
                $saleMasters->setter1_m1 = 0;
                $saleMasters->setter2_m1 = 0;
                $saleMasters->closer1_m1_paid_status = null;
                $saleMasters->closer2_m1_paid_status = null;
                $saleMasters->setter1_m1_paid_status = null;
                $saleMasters->setter2_m1_paid_status = null;
                $saleMasters->save();
            }
            UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'is_displayed' => '1'])->delete();
            ReconCommissionHistory::where(['pid' => $pid, 'type' => 'm1', 'is_displayed' => '1', 'is_ineligible' => '0'])->delete();
        }
    }

    public function m2dateSalesData($pid, $m2date)
    {
        $m2Comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => '3', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first();
        $m2Recon = ReconCommissionHistory::where(['pid' => $pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->whereIn('type', ['m2', 'reconciliation'])->first();
        if (! $m2Comm && ! $m2Recon) {
            $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
            if ($saleMasters) {
                $saleMasters->closer1_m2 = 0;
                $saleMasters->closer2_m2 = 0;
                $saleMasters->setter1_m2 = 0;
                $saleMasters->setter2_m2 = 0;
                $saleMasters->closer1_m2_paid_status = null;
                $saleMasters->closer2_m2_paid_status = null;
                $saleMasters->setter1_m2_paid_status = null;
                $saleMasters->setter2_m2_paid_status = null;
                $saleMasters->closer1_commission = 0;
                $saleMasters->closer2_commission = 0;
                $saleMasters->setter1_commission = 0;
                $saleMasters->setter2_commission = 0;
                $saleMasters->mark_account_status_id = null;
                $saleMasters->save();

                UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'reconciliation'])->delete();
                ReconCommissionHistory::where(['pid' => $pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->whereIn('type', ['m2', 'reconciliation'])->delete();
            }
        }
    }

    public function m1datePayrollData($pid, $m1_date_new)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;

        $commissions = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        foreach ($commissions as $commission) {
            $subPositionId = $commission->position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $commission->user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $payFrequency = $this->payFrequencyNew($m1_date_new, $subPositionId, $commission->user_id);
            if (isset($payFrequency->pay_period_from)) {
                $commission->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'payroll_id' => 0, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to]);
                $this->updateCommissionNew($commission->user_id, $subPositionId, 0, $payFrequency);
            }
        }
    }

    public function m2datePayrollData($pid, $m2_date_new)
    {
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;

        $commissions = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        foreach ($commissions as $commission) {
            $subPositionId = $commission->position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $commission->user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $payFrequency = $this->payFrequencyNew($m2_date_new, $subPositionId, $commission->user_id);
            if (isset($payFrequency->pay_period_from)) {
                $commission->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'payroll_id' => 0, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to]);
                $this->updateCommissionNew($commission->user_id, $subPositionId, 0, $payFrequency);
            }
        }

        $overrides = UserOverrides::with('userdata')->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->get();
        foreach ($overrides as $override) {
            $subPositionId = $override->userdata->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where('user_id', $override->user_id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $payFrequency = $this->payFrequencyNew($m2_date_new, $subPositionId, $override->user_id);
            if (isset($payFrequency->pay_period_from)) {
                $override->update(['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_move_to_recon' => 0, 'payroll_id' => 0, 'pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to]);
                $this->updateOverrideNew($override->user_id, $subPositionId, 0, $payFrequency);
            }
        }
    }

    public function missingSalesData($param)
    {
        $pid = $param->pid;
        $m1date = $param->m1_date;
        $m2date = $param->m2_date;
        $closers = $param->rep_id;
        $setters = $param->setter_id;
        $closer = User::whereIn('id', $param->rep_id)->get();
        $setter = User::whereIn('id', $param->setter_id)->get();

        if (! empty($param->m1_date) && ! empty($param->m2_date)) {
            $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id, $closer[0]->id);

        } elseif (! empty($m2date)) {
            $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id, $closer[0]->id);

        } elseif (! empty($m1date)) {
            $payFrequency = $this->payFrequency($m1date, $closer[0]->sub_position_id, $closer[0]->id);
        }
        // return $payFrequency->closed_status;
        if (isset($payFrequency) && $payFrequency->closed_status == 1) {

            $apiData = [
                'legacy_data_id' => null,
                'pid' => $param->pid,
                'weekly_sheet_id' => null,
                'homeowner_id' => isset($param->homeowner_id) ? $param->homeowner_id : null,
                'proposal_id' => isset($param->proposal_id) ? $param->proposal_id : null,
                'customer_name' => isset($param->customer_name) ? $param->customer_name : null,
                'customer_address' => isset($param->customer_address) ? $param->customer_address : null,
                'customer_address_2' => isset($param->customer_address2) ? $param->customer_address2 : null,
                'customer_city' => isset($param->customer_city) ? $param->customer_city : null,
                'customer_state' => isset($param->customer_state) ? $param->customer_state : null,
                'customer_zip' => isset($param->customer_zip) ? $param->customer_zip : null,
                'customer_email' => isset($param->customer_email) ? $param->customer_email : null,
                'customer_phone' => isset($param->customer_phone) ? $param->customer_phone : null,
                'setter_id' => isset($setter[0]->id) ? $setter[0]->id : null,
                // 'setter_id'  => isset($setters[0])? $setters[0] : null,
                'sales_setter_email' => isset($setter[0]->email) ? $setter[0]->email : null,
                'employee_id' => null,
                'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
                'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
                'install_partner' => isset($param->installer) ? $param->installer : null,
                'install_partner_id' => null,
                'customer_signoff' => isset($param->approved_date) ? $param->approved_date : null,
                'm1_date' => isset($param->m1_date) ? $param->m1_date : null,
                'scheduled_install' => null,
                'install_complete_date' => null,
                'm2_date' => isset($param->m2_date) ? $param->m2_date : null,
                'date_cancelled' => isset($param->date_cancelled) ? $param->date_cancelled : null,
                'return_sales_date' => null,
                'gross_account_value' => isset($param->gross_account_value) ? $param->gross_account_value : null,
                'cash_amount' => isset($param->cash_amount) ? $param->cash_amount : null,
                'loan_amount' => isset($param->loan_amount) ? $param->loan_amount : null,
                'kw' => isset($param->kw) ? $param->kw : null,
                'dealer_fee_percentage' => isset($param->dealer_fee_percentage) ? $param->dealer_fee_percentage : null,
                'adders' => isset($param->show) ? $param->show : null,
                'cancel_fee' => isset($param->cancel_fee) ? $param->cancel_fee : null,
                'adders_description' => isset($param->adders_description) ? $param->adders_description : null,
                'funding_source' => null,
                'financing_rate' => null,
                'financing_term' => null,
                'product' => isset($param->product) ? $param->product : null,
                'epc' => isset($param->epc) ? $param->epc : null,
                'net_epc' => isset($param->net_epc) ? $param->net_epc : null,
                'status' => 'Unresolved',
                'type' => 'Payroll',
            ];

            $getData = LegacyApiNullData::where('pid', $param->pid)->where('type', 'Payroll')->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
            if ($getData) {
                $inserted = LegacyApiNullData::where('id', $getData->id)->update($apiData);
            } else {
                $inserted = LegacyApiNullData::create($apiData);
            }

            return true;
        } else {
            return false;
        }

    }

    public function executedSalesData($param)
    {
        $pid = $param->pid;
        $m1date = $param->m1_date;
        $m2date = $param->m2_date;
        $date_cancelled = $param->date_cancelled;
        $closers = $param->rep_id;
        $setters = $param->setter_id;
        $closer = User::whereIn('id', $param->rep_id)->get();
        $setter = User::whereIn('id', $param->setter_id)->get();
        $statusType = 'Payroll';

        $saleMasterData = SalesMaster::where('pid', $pid)->first();
        if (! empty($param->m1_date) && ! empty($param->m2_date)) {

            if (empty($saleMasterData->m2_date) && ! empty($m2date)) {
                $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id, $closer[0]->id);

            } elseif (! empty($saleMasterData->m2_date) && ! empty($m2date) && $saleMasterData->m2_date != $m2date) {
                $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id, $closer[0]->id);

            }
            // else if (!empty($m2date)) {
            //     $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id);

            // }

        } elseif (! empty($m2date)) {

            if (empty($saleMasterData->m2_date) && ! empty($m2date)) {
                $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id, $closer[0]->id);

            } elseif (! empty($saleMasterData->m2_date) && ! empty($m2date) && $saleMasterData->m2_date != $m2date) {
                $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id, $closer[0]->id);

            }
            // else if (!empty($m2date)) {
            //     $payFrequency = $this->payFrequency($m2date, $closer[0]->sub_position_id);

            // }

        } elseif (! empty($m1date)) {
            if (empty($saleMasterData->m1_date) && ! empty($m1date)) {
                $payFrequency = $this->payFrequency($m1date, $closer[0]->sub_position_id, $closer[0]->id);

            } elseif (! empty($saleMasterData->m1_date) && ! empty($m1date) && $saleMasterData->m1_date != $m1date) {
                $payFrequency = $this->payFrequency($m1date, $closer[0]->sub_position_id, $closer[0]->id);
            }
            // else if (!empty($m1date)) {
            //     $payFrequency = $this->payFrequency($m1date, $closer[0]->sub_position_id);
            // }

        }

        // if (!empty($date_cancelled)) {
        //     $payFrequency = $this->payFrequency($date_cancelled, $closer[0]->sub_position_id);
        //     if (isset($payFrequency) && $payFrequency->closed_status==1) {
        //         $statusType = 'Clawback';
        //     }
        // }

        // return $payFrequency->closed_status;
        if (isset($payFrequency) && $payFrequency->closed_status == 1) {

            $apiData = [
                'legacy_data_id' => null,
                'pid' => $param->pid,
                'weekly_sheet_id' => null,
                'homeowner_id' => isset($param->homeowner_id) ? $param->homeowner_id : null,
                'proposal_id' => isset($param->proposal_id) ? $param->proposal_id : null,
                'customer_name' => isset($param->customer_name) ? $param->customer_name : null,
                'customer_address' => isset($param->customer_address) ? $param->customer_address : null,
                'customer_address_2' => isset($param->customer_address2) ? $param->customer_address2 : null,
                'customer_city' => isset($param->customer_city) ? $param->customer_city : null,
                'customer_state' => isset($param->customer_state) ? $param->customer_state : null,
                'customer_zip' => isset($param->customer_zip) ? $param->customer_zip : null,
                'customer_email' => isset($param->customer_email) ? $param->customer_email : null,
                'customer_phone' => isset($param->customer_phone) ? $param->customer_phone : null,
                'setter_id' => isset($setter[0]->id) ? $setter[0]->id : null,
                // 'setter_id'  => isset($setters[0])? $setters[0] : null,
                'sales_setter_email' => isset($setter[0]->email) ? $setter[0]->email : null,
                'employee_id' => null,
                'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
                'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
                'install_partner' => isset($param->installer) ? $param->installer : null,
                'install_partner_id' => null,
                'customer_signoff' => isset($param->approved_date) ? $param->approved_date : null,
                'm1_date' => isset($param->m1_date) ? $param->m1_date : null,
                'scheduled_install' => null,
                'install_complete_date' => null,
                'm2_date' => isset($param->m2_date) ? $param->m2_date : null,
                'date_cancelled' => isset($param->date_cancelled) ? $param->date_cancelled : null,
                'return_sales_date' => null,
                'gross_account_value' => isset($param->gross_account_value) ? $param->gross_account_value : null,
                'cash_amount' => isset($param->cash_amount) ? $param->cash_amount : null,
                'loan_amount' => isset($param->loan_amount) ? $param->loan_amount : null,
                'kw' => isset($param->kw) ? $param->kw : null,
                'dealer_fee_percentage' => isset($param->dealer_fee_percentage) ? $param->dealer_fee_percentage : null,
                'adders' => isset($param->show) ? $param->show : null,
                'cancel_fee' => isset($param->cancel_fee) ? $param->cancel_fee : null,
                'adders_description' => isset($param->adders_description) ? $param->adders_description : null,
                'funding_source' => null,
                'financing_rate' => null,
                'financing_term' => null,
                'product' => isset($param->product) ? $param->product : null,
                'epc' => isset($param->epc) ? $param->epc : null,
                'net_epc' => isset($param->net_epc) ? $param->net_epc : null,
                'status' => 'Unresolved',
                'type' => $statusType,
                'job_status' => isset($param->job_status) ? $param->job_status : null,
            ];

            $ommissionCheck = UserCommission::where(['pid' => $param->pid, 'status' => 1])->first();
            if ($ommissionCheck) {
                $getData = LegacyApiNullData::where('pid', $param->pid)->where('type', 'Payroll')->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
                if ($getData) {
                    $inserted = LegacyApiNullData::where('id', $getData->id)->update($apiData);
                } else {
                    $inserted = LegacyApiNullData::create($apiData);
                }
            }

            return true;
        } else {

            return false;
        }

    }

    public function closedPayrollData($pid)
    {
        // $pid = 'LIS24205';
        $commission = UserCommission::with('userdata')->where('pid', $pid)->where('status', 1)->first();
        if ($commission) {
            $payFrequency = $this->payFrequency($commission->date, $commission->userdata->sub_position_id, $commission->userdata->id);
            if (isset($payFrequency) && $payFrequency->closed_status == 1) {

                $salesMaster = SalesMaster::with('salesMasterProcess', 'userCommission', 'userDetail')->where('pid', $pid)->first();
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
                        'sales_setter_email' => isset($salesMaster->salesMasterProcess->setter1Detail) ? $salesMaster->salesMasterProcess->setter1Detail->email : null,
                        'employee_id' => null,
                        'sales_rep_name' => isset($salesMaster->salesMasterProcess->closer1Detail) ? $salesMaster->salesMasterProcess->closer1Detail->first_name : null,
                        'sales_rep_email' => isset($salesMaster->salesMasterProcess->closer1Detail) ? $salesMaster->salesMasterProcess->closer1Detail->email : null,
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
                        'data_source_type' => isset($salesMaster->data_source_type) ? $salesMaster->data_source_type : null,
                    ];
                    // return $apiData;
                    // $getData = LegacyApiNullData::where('pid',$pid)->where('type', 'Payroll')->first();
                    $getData = LegacyApiNullData::where('pid', $pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
                    if ($getData) {
                        $inserted = LegacyApiNullData::where('id', $getData->id)->update($apiData);
                    } else {
                        $inserted = LegacyApiNullData::create($apiData);
                    }
                }

            }

        }

    }

    public function closedPayrollCheck($pid)
    {
        $commission = LegacyApiNullData::where('pid', $pid)->where(['type' => 'Payroll', 'status' => 'Resolved', 'closedpayroll_type' => 'm2'])->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
        if ($commission) {
            return true;
        } else {
            return false;
        }

    }

    public function salesDataHistory($pid, $type = '')
    {
        $salesMaster = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();
        if ($salesMaster) {
            $apiData = [
                'pid' => $salesMaster->pid,
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
                'sales_rep_name' => isset($salesMaster->salesMasterProcess->closer1Detail) ? $salesMaster->salesMasterProcess->closer1Detail->first_name : null,
                'sales_rep_email' => isset($salesMaster->salesMasterProcess->closer1Detail) ? $salesMaster->salesMasterProcess->closer1Detail->email : null,
                'install_partner' => isset($salesMaster->install_partner) ? $salesMaster->install_partner : null,
                'customer_signoff' => isset($salesMaster->customer_signoff) ? $salesMaster->customer_signoff : null,
                'm1_date' => isset($salesMaster->m1_date) ? $salesMaster->m1_date : null,
                'm2_date' => isset($salesMaster->m2_date) ? $salesMaster->m2_date : null,
                'date_cancelled' => isset($salesMaster->date_cancelled) ? $salesMaster->date_cancelled : null,
                'gross_account_value' => isset($salesMaster->gross_account_value) ? $salesMaster->gross_account_value : null,
                'cash_amount' => isset($salesMaster->cash_amount) ? $salesMaster->cash_amount : null,
                'loan_amount' => isset($salesMaster->loan_amount) ? $salesMaster->loan_amount : null,
                'kw' => isset($salesMaster->kw) ? $salesMaster->kw : null,
                'dealer_fee_percentage' => isset($salesMaster->dealer_fee_percentage) ? $salesMaster->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($salesMaster->dealer_fee_amount) ? $salesMaster->dealer_fee_amount : null,
                'adders' => isset($salesMaster->adders) ? $salesMaster->adders : null,
                'cancel_fee' => isset($salesMaster->cancel_fee) ? $salesMaster->cancel_fee : null,
                'adders_description' => isset($salesMaster->adders_description) ? $salesMaster->adders_description : null,
                'redline' => isset($salesMaster->redline) ? $salesMaster->redline : null,
                'total_amount_for_acct' => isset($salesMaster->total_amount_for_acct) ? $salesMaster->total_amount_for_acct : null,
                'prev_amount_paid' => isset($salesMaster->prev_amount_paid) ? $salesMaster->prev_amount_paid : null,
                'last_date_pd' => isset($salesMaster->last_date_pd) ? $salesMaster->last_date_pd : null,
                'm1_amount' => isset($salesMaster->m1_amount) ? $salesMaster->m1_amount : null,
                'm2_amount' => isset($salesMaster->m2_amount) ? $salesMaster->m2_amount : null,
                'prev_deducted_amount' => isset($salesMaster->prev_deducted_amount) ? $salesMaster->prev_deducted_amount : null,
                'cancel_deduction' => isset($salesMaster->cancel_deduction) ? $salesMaster->cancel_deduction : null,
                'lead_cost_amount' => isset($salesMaster->lead_cost_amount) ? $salesMaster->lead_cost_amount : null,
                'adv_pay_back_amount' => isset($salesMaster->adv_pay_back_amount) ? $salesMaster->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($salesMaster->total_amount_in_period) ? $salesMaster->total_amount_in_period : null,
                'funding_source' => isset($salesMaster->funding_source) ? $salesMaster->funding_source : null,
                'financing_rate' => isset($salesMaster->financing_rate) ? $salesMaster->financing_rate : null,
                'financing_term' => isset($salesMaster->financing_term) ? $salesMaster->financing_term : null,
                'product' => isset($salesMaster->product) ? $salesMaster->product : null,
                'epc' => isset($salesMaster->epc) ? $salesMaster->epc : null,
                'net_epc' => isset($salesMaster->net_epc) ? $salesMaster->net_epc : null,

                'closer1_id' => isset($salesMaster->salesMasterProcess->closer1_id) ? $salesMaster->salesMasterProcess->closer1_id : null,
                'closer2_id' => isset($salesMaster->salesMasterProcess->closer2_id) ? $salesMaster->salesMasterProcess->closer2_id : null,
                'setter1_id' => isset($salesMaster->salesMasterProcess->setter1_id) ? $salesMaster->salesMasterProcess->setter1_id : null,
                'setter2_id' => isset($salesMaster->salesMasterProcess->setter2_id) ? $salesMaster->salesMasterProcess->setter2_id : null,

                'closer1_m1' => isset($salesMaster->salesMasterProcess->closer1_m1) ? $salesMaster->salesMasterProcess->closer1_m1 : 0,
                'closer2_m1' => isset($salesMaster->salesMasterProcess->closer2_m1) ? $salesMaster->salesMasterProcess->closer2_m1 : 0,
                'setter1_m1' => isset($salesMaster->salesMasterProcess->setter1_m1) ? $salesMaster->salesMasterProcess->setter1_m1 : 0,
                'setter2_m1' => isset($salesMaster->salesMasterProcess->setter2_m1) ? $salesMaster->salesMasterProcess->setter2_m1 : 0,

                'closer1_m2' => isset($salesMaster->salesMasterProcess->closer1_m2) ? $salesMaster->salesMasterProcess->closer1_m2 : 0,
                'closer2_m2' => isset($salesMaster->salesMasterProcess->closer2_m2) ? $salesMaster->salesMasterProcess->closer2_m2 : 0,
                'setter1_m2' => isset($salesMaster->salesMasterProcess->setter1_m2) ? $salesMaster->salesMasterProcess->setter1_m2 : 0,
                'setter2_m2' => isset($salesMaster->salesMasterProcess->setter2_m2) ? $salesMaster->salesMasterProcess->setter2_m2 : 0,

                'closer1_commission' => isset($salesMaster->salesMasterProcess->closer1_commission) ? $salesMaster->salesMasterProcess->closer1_commission : 0,
                'closer2_commission' => isset($salesMaster->salesMasterProcess->closer2_commission) ? $salesMaster->salesMasterProcess->closer2_commission : 0,
                'setter1_commission' => isset($salesMaster->salesMasterProcess->setter1_commission) ? $salesMaster->salesMasterProcess->setter1_commission : 0,
                'setter2_commission' => isset($salesMaster->salesMasterProcess->setter2_commission) ? $salesMaster->salesMasterProcess->setter2_commission : 0,

                'closer1_m1_paid_status' => isset($salesMaster->salesMasterProcess->closer1_m1_paid_status) ? $salesMaster->salesMasterProcess->closer1_m1_paid_status : null,
                'closer2_m1_paid_status' => isset($salesMaster->salesMasterProcess->closer2_m1_paid_status) ? $salesMaster->salesMasterProcess->closer2_m1_paid_status : null,
                'setter1_m1_paid_status' => isset($salesMaster->salesMasterProcess->setter1_m1_paid_status) ? $salesMaster->salesMasterProcess->setter1_m1_paid_status : null,
                'setter2_m1_paid_status' => isset($salesMaster->salesMasterProcess->setter2_m1_paid_status) ? $salesMaster->salesMasterProcess->setter2_m1_paid_status : null,
                'closer1_m2_paid_status' => isset($salesMaster->salesMasterProcess->closer1_m2_paid_status) ? $salesMaster->salesMasterProcess->closer1_m2_paid_status : null,
                'closer2_m2_paid_status' => isset($salesMaster->salesMasterProcess->closer2_m2_paid_status) ? $salesMaster->salesMasterProcess->closer2_m2_paid_status : null,
                'setter1_m2_paid_status' => isset($salesMaster->salesMasterProcess->setter1_m2_paid_status) ? $salesMaster->salesMasterProcess->setter1_m2_paid_status : null,
                'setter2_m2_paid_status' => isset($salesMaster->salesMasterProcess->setter2_m2_paid_status) ? $salesMaster->salesMasterProcess->setter2_m2_paid_status : null,

                'closer1_m1_paid_date' => isset($salesMaster->salesMasterProcess->closer1_m1_paid_date) ? $salesMaster->salesMasterProcess->closer1_m1_paid_date : null,
                'closer2_m1_paid_date' => isset($salesMaster->salesMasterProcess->closer2_m1_paid_date) ? $salesMaster->salesMasterProcess->closer2_m1_paid_date : null,
                'setter1_m1_paid_date' => isset($salesMaster->salesMasterProcess->setter1_m1_paid_date) ? $salesMaster->salesMasterProcess->setter1_m1_paid_date : null,
                'setter2_m1_paid_date' => isset($salesMaster->salesMasterProcess->setter2_m1_paid_date) ? $salesMaster->salesMasterProcess->setter2_m1_paid_date : null,
                'closer1_m2_paid_date' => isset($salesMaster->salesMasterProcess->closer1_m2_paid_date) ? $salesMaster->salesMasterProcess->closer1_m2_paid_date : null,
                'closer2_m2_paid_date' => isset($salesMaster->salesMasterProcess->closer2_m2_paid_date) ? $salesMaster->salesMasterProcess->closer2_m2_paid_date : null,
                'setter1_m2_paid_date' => isset($salesMaster->salesMasterProcess->setter1_m2_paid_date) ? $salesMaster->salesMasterProcess->setter1_m2_paid_date : null,
                'setter2_m2_paid_date' => isset($salesMaster->salesMasterProcess->setter2_m2_paid_date) ? $salesMaster->salesMasterProcess->setter2_m2_paid_date : null,

                'mark_account_status_id' => isset($salesMaster->salesMasterProcess->mark_account_status_id) ? $salesMaster->salesMasterProcess->mark_account_status_id : null,
                'pid_status' => isset($salesMaster->salesMasterProcess->pid_status) ? $salesMaster->salesMasterProcess->pid_status : null,
                'data_source_type' => $type ? $type : (isset($salesMaster->data_source_type) ? $salesMaster->data_source_type : null),
                'job_status' => isset($salesMaster->job_status) ? $salesMaster->job_status : null,
            ];

            $companyProfile = CompanyProfile::first();
            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                $apiData['data_source_type'] = $type ? $type : (isset($salesMaster->data_source_type) ? $salesMaster->data_source_type : null);
                $apiData['location_code'] = isset($salesMaster->location_code) ? $salesMaster->location_code : null;
                $apiData['length_of_agreement'] = isset($salesMaster->length_of_agreement) ? $salesMaster->length_of_agreement : null;
                $apiData['service_schedule'] = isset($salesMaster->service_schedule) ? $salesMaster->service_schedule : null;
                $apiData['initial_service_cost'] = isset($salesMaster->initial_service_cost) ? $salesMaster->initial_service_cost : null;
                $apiData['subscription_payment'] = isset($salesMaster->subscription_payment) ? $salesMaster->subscription_payment : null;
                $apiData['service_completed'] = isset($salesMaster->service_completed) ? $salesMaster->service_completed : null;
                $apiData['last_service_date'] = isset($salesMaster->last_service_date) ? $salesMaster->last_service_date : null;
                $apiData['bill_status'] = isset($salesMaster->bill_status) ? $salesMaster->bill_status : null;
                $apiData['auto_pay'] = isset($salesMaster->auto_pay) ? $salesMaster->auto_pay : null;
                $apiData['card_on_file'] = isset($salesMaster->card_on_file) ? $salesMaster->card_on_file : null;
            }

            LegacyApiRawDataHistory::create($apiData);
        }
    }

    public function closedPayrollDataHistory($pid, $startDate, $endDate)
    {
        // $pid = 'LIS24205';
        $salesMaster = SalesMaster::with('salesMasterProcess', 'userCommission', 'userDetail')->where('pid', $pid)->first();
        if ($salesMaster) {
            $saleMasterProcess = SaleMasterProcess::where('pid', $pid)->first();

            $apiData = [
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
                'employee_id' => null,
                'sales_rep_name' => isset($salesMaster->salesMasterProcess->closer1Detail) ? $salesMaster->salesMasterProcess->closer1Detail->first_name : null,
                'sales_rep_email' => isset($salesMaster->salesMasterProcess->closer1Detail) ? $salesMaster->salesMasterProcess->closer1Detail->email : null,
                'install_partner' => isset($salesMaster->install_partner) ? $salesMaster->install_partner : null,
                'install_partner_id' => null,
                'customer_signoff' => isset($salesMaster->customer_signoff) ? $salesMaster->customer_signoff : null,
                'm1_date' => isset($salesMaster->m1_date) ? $salesMaster->m1_date : null,
                'm2_date' => isset($salesMaster->m2_date) ? $salesMaster->m2_date : null,
                'scheduled_install' => null,
                'install_complete_date' => null,
                'date_cancelled' => isset($salesMaster->date_cancelled) ? $salesMaster->date_cancelled : null,
                'return_sales_date' => null,
                'gross_account_value' => isset($salesMaster->gross_account_value) ? $salesMaster->gross_account_value : null,
                'cash_amount' => isset($salesMaster->cash_amount) ? $salesMaster->cash_amount : null,
                'loan_amount' => isset($salesMaster->loan_amount) ? $salesMaster->loan_amount : null,
                'kw' => isset($salesMaster->kw) ? $salesMaster->kw : null,
                'dealer_fee_percentage' => isset($salesMaster->dealer_fee_percentage) ? $salesMaster->dealer_fee_percentage : null,
                'dealer_fee_amount' => isset($salesMaster->dealer_fee_amount) ? $salesMaster->dealer_fee_amount : null,
                'adders' => isset($salesMaster->adders) ? $salesMaster->adders : null,
                'cancel_fee' => isset($salesMaster->cancel_fee) ? $salesMaster->cancel_fee : null,
                'adders_description' => isset($salesMaster->adders_description) ? $salesMaster->adders_description : null,
                'redline' => isset($salesMaster->redline) ? $salesMaster->redline : null,
                'total_amount_for_acct' => isset($salesMaster->total_amount_for_acct) ? $salesMaster->total_amount_for_acct : null,
                'prev_amount_paid' => isset($salesMaster->prev_amount_paid) ? $salesMaster->prev_amount_paid : null,
                'last_date_pd' => isset($salesMaster->last_date_pd) ? $salesMaster->last_date_pd : null,
                'm1_amount' => isset($salesMaster->m1_amount) ? $salesMaster->m1_amount : null,
                'm2_amount' => isset($salesMaster->m2_amount) ? $salesMaster->m2_amount : null,
                'prev_deducted_amount' => isset($salesMaster->prev_deducted_amount) ? $salesMaster->prev_deducted_amount : null,
                'cancel_deduction' => isset($salesMaster->cancel_deduction) ? $salesMaster->cancel_deduction : null,
                'lead_cost_amount' => isset($salesMaster->lead_cost_amount) ? $salesMaster->lead_cost_amount : null,
                'adv_pay_back_amount' => isset($salesMaster->adv_pay_back_amount) ? $salesMaster->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($salesMaster->total_amount_in_period) ? $salesMaster->total_amount_in_period : null,
                'funding_source' => isset($salesMaster->funding_source) ? $salesMaster->funding_source : null,
                'financing_rate' => isset($salesMaster->financing_rate) ? $salesMaster->financing_rate : null,
                'financing_term' => isset($salesMaster->financing_term) ? $salesMaster->financing_term : null,
                'product' => isset($salesMaster->product) ? $salesMaster->product : null,
                'epc' => isset($salesMaster->epc) ? $salesMaster->epc : null,
                'net_epc' => isset($salesMaster->net_epc) ? $salesMaster->net_epc : null,

                'closer1_id' => isset($saleMasterProcess->closer1_id) ? $saleMasterProcess->closer1_id : null,
                'closer2_id' => isset($saleMasterProcess->closer2_id) ? $saleMasterProcess->closer2_id : null,
                'setter1_id' => isset($saleMasterProcess->setter1_id) ? $saleMasterProcess->setter1_id : null,
                'setter2_id' => isset($saleMasterProcess->setter2_id) ? $saleMasterProcess->setter2_id : null,

                'closer1_m1' => isset($saleMasterProcess->closer1_m1) ? $saleMasterProcess->closer1_m1 : 0,
                'closer2_m1' => isset($saleMasterProcess->closer2_m1) ? $saleMasterProcess->closer2_m1 : 0,
                'setter1_m1' => isset($saleMasterProcess->setter1_m1) ? $saleMasterProcess->setter1_m1 : 0,
                'setter2_m1' => isset($saleMasterProcess->setter2_m1) ? $saleMasterProcess->setter2_m1 : 0,

                'closer1_m2' => isset($saleMasterProcess->closer1_m2) ? $saleMasterProcess->closer1_m2 : 0,
                'closer2_m2' => isset($saleMasterProcess->closer2_m2) ? $saleMasterProcess->closer2_m2 : 0,
                'setter1_m2' => isset($saleMasterProcess->setter1_m2) ? $saleMasterProcess->setter1_m2 : 0,
                'setter2_m2' => isset($saleMasterProcess->setter2_m2) ? $saleMasterProcess->setter2_m2 : 0,

                'closer1_commission' => isset($saleMasterProcess->closer1_commission) ? $saleMasterProcess->closer1_commission : 0,
                'closer2_commission' => isset($saleMasterProcess->closer2_commission) ? $saleMasterProcess->closer2_commission : 0,
                'setter1_commission' => isset($saleMasterProcess->setter1_commission) ? $saleMasterProcess->setter1_commission : 0,
                'setter2_commission' => isset($saleMasterProcess->setter2_commission) ? $saleMasterProcess->setter2_commission : 0,

                'closer1_m1_paid_status' => isset($saleMasterProcess->closer1_m1_paid_status) ? $saleMasterProcess->closer1_m1_paid_status : null,
                'closer2_m1_paid_status' => isset($saleMasterProcess->closer2_m1_paid_status) ? $saleMasterProcess->closer2_m1_paid_status : null,
                'setter1_m1_paid_status' => isset($saleMasterProcess->setter1_m1_paid_status) ? $saleMasterProcess->setter1_m1_paid_status : null,
                'setter2_m1_paid_status' => isset($saleMasterProcess->setter2_m1_paid_status) ? $saleMasterProcess->setter2_m1_paid_status : null,
                'closer1_m2_paid_status' => isset($saleMasterProcess->closer1_m2_paid_status) ? $saleMasterProcess->closer1_m2_paid_status : null,
                'closer2_m2_paid_status' => isset($saleMasterProcess->closer2_m2_paid_status) ? $saleMasterProcess->closer2_m2_paid_status : null,
                'setter1_m2_paid_status' => isset($saleMasterProcess->setter1_m2_paid_status) ? $saleMasterProcess->setter1_m2_paid_status : null,
                'setter2_m2_paid_status' => isset($saleMasterProcess->setter2_m2_paid_status) ? $saleMasterProcess->setter2_m2_paid_status : null,

                'closer1_m1_paid_date' => isset($saleMasterProcess->closer1_m1_paid_date) ? $saleMasterProcess->closer1_m1_paid_date : null,
                'closer2_m1_paid_date' => isset($saleMasterProcess->closer2_m1_paid_date) ? $saleMasterProcess->closer2_m1_paid_date : null,
                'setter1_m1_paid_date' => isset($saleMasterProcess->setter1_m1_paid_date) ? $saleMasterProcess->setter1_m1_paid_date : null,
                'setter2_m1_paid_date' => isset($saleMasterProcess->setter2_m1_paid_date) ? $saleMasterProcess->setter2_m1_paid_date : null,
                'closer1_m2_paid_date' => isset($saleMasterProcess->closer1_m2_paid_date) ? $saleMasterProcess->closer1_m2_paid_date : null,
                'closer2_m2_paid_date' => isset($saleMasterProcess->closer2_m2_paid_date) ? $saleMasterProcess->closer2_m2_paid_date : null,
                'setter1_m2_paid_date' => isset($saleMasterProcess->setter1_m2_paid_date) ? $saleMasterProcess->setter1_m2_paid_date : null,
                'setter2_m2_paid_date' => isset($saleMasterProcess->setter2_m2_paid_date) ? $saleMasterProcess->setter2_m2_paid_date : null,

                'mark_account_status_id' => isset($saleMasterProcess->mark_account_status_id) ? $saleMasterProcess->mark_account_status_id : null,
                'pid_status' => isset($saleMasterProcess->pid_status) ? $saleMasterProcess->pid_status : null,
                'data_source_type' => isset($salesMaster->data_source_type) ? $salesMaster->data_source_type : null,
                'import_to_sales' => 1,
                'import_status_reason' => null, // Clear error reason on successful processing
                'import_status_description' => null, // Clear error description on successful processing
                'pay_period_from' => isset($startDate) ? $startDate : null,
                'pay_period_to' => isset($endDate) ? $endDate : null,

            ];
            // return $apiData;
            $inserted = LegacyApiRawDataHistory::create($apiData);
        }

    }
}
