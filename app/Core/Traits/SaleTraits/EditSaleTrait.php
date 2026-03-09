<?php

namespace App\Core\Traits\SaleTraits;

use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\ReconciliationPeriodTrait;
use App\Models\AdditionalLocations;
use App\Models\CompanyProfile;
use App\Models\CompanySetting;
use App\Models\ExternalSaleProductMaster;
use App\Models\ExternalSaleWorker;
use App\Models\LegacyApiRawDataHistory;
use App\Models\LocationRedlineHistory;
use App\Models\Locations;
use App\Models\OverrideStatus;
use App\Models\PositionCommission;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\ProductMilestoneHistories;
use App\Models\Products;
use App\Models\ProjectionUserCommission;
use App\Models\ProjectionUserOverrides;
use App\Models\ReconCommissionHistory;
use App\Models\ReconOverrideHistory;
use App\Models\SaleMasterProcess;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\SaleTiersDetail;
use App\Models\State;
use App\Models\User;
use App\Models\UserAdditionalOfficeOverrideHistory;
use App\Models\UserCommission;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionHistoryTiersRange;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserOverrides;
use App\Models\UserRedlines;
use App\Models\UserTransferHistory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait EditSaleTrait
{
    use PayFrequencyTrait, ReconciliationPeriodTrait;
    use ProjectionSyncTrait;

    public function updateSalesData($userID, $position_id, $pid)
    {
        $commission = UserCommission::where(['user_id' => $userID, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('amount_type', ['m1', 'm2'])->first();
        if ($commission) {
            $userCommissions = UserCommission::where(['user_id' => $userID, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->whereIn('amount_type', ['m1', 'm2'])->get();
            foreach ($userCommissions as $userCommission) {
                $userCommission->delete();
            }
            $userOverrides = UserOverrides::where(['sale_user_id' => $userID, 'pid' => $pid, 'status' => '1', 'is_displayed' => '1'])->get();
            foreach ($userOverrides as $userOverride) {
                $userOverride->delete();
            }
        }
        $reconciliation = UserCommission::where(['user_id' => $userID, 'pid' => $pid, 'amount_type' => 'reconciliation', 'is_displayed' => '1'])->first();
        if ($reconciliation) {
            $reconPaid = ReconCommissionHistory::where(['user_id' => $userID, 'pid' => $pid, 'type' => 'reconciliation', 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid_amount');
            if (! $reconPaid) {
                $reconciliation->delete();
            }
        }
        $reconciliations = UserOverrides::where(['sale_user_id' => $userID, 'pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get();
        foreach ($reconciliations as $recon) {
            $reconPaid = ReconOverrideHistory::where(['pid' => $pid, 'user_id' => $recon->user_id, 'overrider' => $recon->sale_user_id, 'type' => $recon->type, 'during' => $recon->during, 'is_displayed' => '1', 'is_ineligible' => '0'])->sum('paid');
            if (! $reconPaid) {
                $recon->delete();
            }
        }
    }

    public function removeUpFrontSaleData($pid, $type = null)
    {
        $userCommissions = UserCommission::where(['pid' => $pid, 'is_last' => '0', 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])
        ->when(!empty($type), function ($q) use ($type) {
            $q->where(['schema_type' => $type]);
        })->get();
        $userReconCommissions = UserCommission::where(['pid' => $pid, 'is_last' => '0', 'recon_status' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])
        ->when(!empty($type), function ($q) use ($type) {
            $q->where(['schema_type' => $type]);
        })->get();
        $userCommissions = $userCommissions->merge($userReconCommissions);
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }

        $overrideCheck = SaleProductMaster::where(['pid' => $pid, 'type' => $type, 'is_override' => '1'])->first();
        if ($overrideCheck || !$type) {
            $userOverrides = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->get();
            $userReconOverrides = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->get();
            $userOverrides = $userOverrides->merge($userReconOverrides);
            foreach ($userOverrides as $userOverride) {
                $userOverride->delete();
            }
        }
    }

    public function removeCommissionSaleData($pid)
    {
        $userCommissions = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        $userReconCommissions = UserCommission::where(['pid' => $pid, 'is_last' => '1', 'recon_status' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get();
        $userCommissions = $userCommissions->merge($userReconCommissions);
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }

        $overrideCheck = SaleProductMaster::where(['pid' => $pid, 'is_last_date' => '1', 'is_override' => '1'])->first();
        if ($overrideCheck) {
            $userOverrides = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->get();
            $userReconOverrides = UserOverrides::where(['pid' => $pid, 'overrides_settlement_type' => 'reconciliation', 'recon_status' => '1', 'is_displayed' => '1'])->get();
            $userOverrides = $userOverrides->merge($userReconOverrides);
            foreach ($userOverrides as $userOverride) {
                $userOverride->delete();
            }
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

                $userCommissions = UserCommission::where(['pid' => $pid, 'is_displayed' => '1'])->whereIn('amount_type', ['m2', 'reconciliation'])->get();
                $userReconCommissions = ReconCommissionHistory::where(['pid' => $pid, 'is_displayed' => '1', 'is_ineligible' => '0'])->whereIn('type', ['m2', 'reconciliation'])->get();
                $userCommissions = $userCommissions->merge($userReconCommissions);
                foreach ($userCommissions as $userCommission) {
                    $userCommission->delete();
                }
            }
        }
    }

    public function changeUpFrontPayrollData($pid, $data)
    {
        $date = $data['date'];
        $type = $data['type'];
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;

        $commissions = UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        foreach ($commissions as $commission) {
            $subPositionId = $commission->position_id;
            $organizationHistory = UserOrganizationHistory::where(['user_id' => $commission->user_id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $payFrequency = $this->payFrequencyNew($date, $subPositionId, $commission->user_id);
            if (isset($payFrequency->pay_period_from)) {
                $updateData = [
                    'is_mark_paid' => 0,
                    'is_next_payroll' => 0,
                    'is_move_to_recon' => 0,
                    'pay_period_from' => $payFrequency->pay_period_from,
                    'pay_period_to' => $payFrequency->pay_period_to,
                    'pay_frequency' => $payFrequency->pay_frequency,
                ];
                
                // Only set payroll_id to 0 if explicitly moving to reconciliation
                if ($commission->is_move_to_recon) {
                    $updateData['payroll_id'] = 0;
                }
                
                $commission->update($updateData);
                // subroutineCreatePayrollRecord($commission->user_id, $subPositionId, $payFrequency);
            }
        }

        $overrideCheck = SaleProductMaster::where(['pid' => $pid, 'type' => $type, 'is_override' => '1'])->first();
        if ($overrideCheck) {
            $overrides = UserOverrides::with(['userdata'])->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->get();
            foreach ($overrides as $override) {
                $subPositionId = $override->userdata->sub_position_id;
                $organizationHistory = UserOrganizationHistory::where(['user_id' => $override->user_id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($organizationHistory) {
                    $subPositionId = $organizationHistory->sub_position_id;
                }

                $payFrequency = $this->payFrequencyNew($date, $subPositionId, $override->user_id);
                if (isset($payFrequency->pay_period_from)) {
                    $override->is_mark_paid = 0;
                    $override->is_next_payroll = 0;
                    $override->is_move_to_recon = 0;
                    $override->pay_period_from = $payFrequency->pay_period_from;
                    $override->pay_period_to = $payFrequency->pay_period_to;
                    $override->pay_frequency = $payFrequency->pay_frequency;
                    $override->save();
                    // subroutineCreatePayrollRecord($override->user_id, $subPositionId, $payFrequency);
                }
            }
        }
    }

    public function changeCommissionPayrollData($pid, $data)
    {
        $date = $data['date'];
        $type = $data['type'];
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;

        $commissions = UserCommission::where(['pid' => $pid, 'schema_type' => $type, 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        foreach ($commissions as $commission) {
            $subPositionId = $commission->position_id;
            $organizationHistory = UserOrganizationHistory::where(['user_id' => $commission->user_id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $payFrequency = $this->payFrequencyNew($date, $subPositionId, $commission->user_id);
            if (isset($payFrequency->pay_period_from)) {
                if ($commission->is_move_to_recon) {
                    $commission->payroll_id = 0;
                }

                $commission->is_mark_paid = 0;
                $commission->is_next_payroll = 0;
                $commission->is_move_to_recon = 0;
                $commission->pay_period_from = $payFrequency->pay_period_from;
                $commission->pay_period_to = $payFrequency->pay_period_to;
                $commission->pay_frequency = $payFrequency->pay_frequency;
                $commission->save();
                // subroutineCreatePayrollRecord($commission->user_id, $subPositionId, $payFrequency, $pid);
            }
        }

        $overrides = UserOverrides::with(['userdata'])->where(['pid' => $pid, 'overrides_settlement_type' => 'during_m2', 'status' => '1', 'is_displayed' => '1'])->get();
        foreach ($overrides as $override) {
            $subPositionId = $override->userdata->sub_position_id;
            $organizationHistory = UserOrganizationHistory::where(['user_id' => $override->user_id])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($organizationHistory) {
                $subPositionId = $organizationHistory->sub_position_id;
            }

            $payFrequency = $this->payFrequencyNew($date, $subPositionId, $override->user_id);
            if (isset($payFrequency->pay_period_from)) {
                $override->is_mark_paid = 0;
                $override->is_next_payroll = 0;
                $override->is_move_to_recon = 0;
                $override->pay_period_from = $payFrequency->pay_period_from;
                $override->pay_period_to = $payFrequency->pay_period_to;
                $override->pay_frequency = $payFrequency->pay_frequency;
                $override->save();
                // subroutineCreatePayrollRecord($override->user_id, $subPositionId, $payFrequency, $pid);
            }
        }
    }

    public function salesDataHistory($pid, $type = '')
    {
        $salesMaster = SalesMaster::with(['salesMasterProcess', 'salesMasterProcess.closer1Detail'])->where('pid', $pid)->first();
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
                'product_id' => isset($salesMaster->product_id) ? $salesMaster->product_id : null,
                'product_code' => isset($salesMaster->product_code) ? $salesMaster->product_code : null,
                'sale_product_name' => isset($salesMaster->sale_product_name) ? $salesMaster->sale_product_name : null,
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

    public function createProductData($data)
    {
        $pid = $data['pid'];
        $productId = $data['product_id'];
        $closer1Id = $data['closer1_id'];
        $closer2Id = $data['closer2_id'];
        $setter1Id = $data['setter1_id'];
        $setter2Id = $data['setter2_id'];
        $effectiveDate = $data['effective_date'];

        \Log::info('[MILESTONE_DEBUG] createProductData - START', [
            'pid' => $pid,
            'productId' => $productId,
            'effectiveDate' => $effectiveDate,
            'data_milestone_dates' => $data['milestone_dates'] ?? 'not_set',
            'data_milestone_dates_count' => isset($data['milestone_dates']) ? count($data['milestone_dates']) : 0
        ]);

        $paid = false;
        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->first()) {
            $paid = true;
        }
        if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->whereIn('recon_status', ['2', '3'])->first()) {
            $paid = true;
        }

        if ($paid) {
            // CRITICAL: ORDER BY type to match the order used in recalculateSale()
            // This ensures $key indices align with milestone_dates array indices
            $saleProducts = SaleProductMaster::where('pid', $pid)->groupBy('type')->orderBy('type')->get();
            foreach ($saleProducts as $key => $saleProduct) {
                $date = @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null;
                SaleProductMaster::where(['pid' => $pid, 'type' => $saleProduct->type])->update(['milestone_date' => $date]);
                ExternalSaleProductMaster::where(['pid' => $pid, 'type' => $saleProduct->type])->update(['milestone_date' => $date]);
            }
        } else {
            $milestoneTriggers = [];
            if ($productId) {
                $milestone = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                
                \Log::info('[MILESTONE_DEBUG] createProductData - After milestone query', [
                    'pid' => $pid,
                    'product_id' => $productId,
                    'effective_date' => $effectiveDate,
                    'milestone_found' => $milestone ? 'yes' : 'no',
                    'milestone_id' => $milestone->id ?? null,
                    'milestone_schema_id' => $milestone->milestone_schema_id ?? null,
                    'has_milestone_relation' => isset($milestone->milestone) ? 'yes' : 'no',
                    'has_triggers' => isset($milestone->milestone->milestone_trigger) ? 'yes' : 'no',
                    'triggers_count' => isset($milestone->milestone->milestone_trigger) ? count($milestone->milestone->milestone_trigger) : 0
                ]);
                
                if (isset($milestone->milestone->milestone_trigger)) {
                    // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                    $milestoneTriggers = $milestone->milestone->milestone_trigger->values();
                } else {
                    $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $productId = $product->id;
                    $milestone = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $product->id)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (isset($milestone->milestone->milestone_trigger)) {
                        // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                        $milestoneTriggers = $milestone->milestone->milestone_trigger->values();
                    }
                }
            } else {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $productId = $product->id;
                $milestone = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $product->id)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (isset($milestone->milestone->milestone_trigger)) {
                    // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                    $milestoneTriggers = $milestone->milestone->milestone_trigger->values();
                }
            }

            SaleProductMaster::where('pid', $pid)->delete();
            ExternalSaleProductMaster::where('pid', $pid)->delete();
            $count = count($milestoneTriggers);
            
            \Log::info('[MILESTONE_DEBUG] createProductData - Before loop', [
                'pid' => $pid,
                'product_id' => $productId,
                'milestoneTriggers_count' => $count,
                'data_milestone_dates' => $data['milestone_dates'] ?? [],
            ]);
            
            foreach ($milestoneTriggers as $key => $milestoneTrigger) {
                // Reset flags for each iteration
                $isLast = 0;
                $isExempted = 0;
                $isOverRide = 0;

                if (($key + 1) == $count) {
                    $isLast = 1;
                }

                if ($milestone->clawback_exempt_on_ms_trigger_id && $milestone->clawback_exempt_on_ms_trigger_id == $milestoneTrigger->id) {
                    $isExempted = 1;
                }

                if ($milestone->override_on_ms_trigger_id && $milestone->override_on_ms_trigger_id == $milestoneTrigger->id) {
                    $isOverRide = 1;
                }

                $milestoneDate = @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null;
                
                \Log::info('[MILESTONE_DEBUG] createProductData - Creating SaleProductMaster', [
                    'pid' => $pid,
                    'loop_key' => $key,
                    'type' => 'm'.($key + 1),
                    'milestoneTrigger_id' => $milestoneTrigger->id,
                    'milestone_id' => $milestone->id,
                    'milestone_schema_id' => $milestoneTrigger->id,
                    'data_milestone_dates_key_exists' => isset($data['milestone_dates'][$key]),
                    'milestone_date_value' => $milestoneDate,
                    'is_projected' => $milestoneDate ? 0 : 1,
                ]);
                
                // if ($closer1Id) {
                SaleProductMaster::create([
                    'pid' => $pid,
                    'product_id' => $productId,
                    'milestone_id' => $milestone->id,
                    'milestone_schema_id' => $milestoneTrigger->id,
                    'milestone_date' => $milestoneDate,
                    'type' => 'm'.($key + 1),
                    'is_last_date' => $isLast,
                    'is_exempted' => $isExempted,
                    'is_override' => $isOverRide,
                    'is_projected' => $milestoneDate ? 0 : 1,
                    'closer1_id' => $closer1Id,
                ]);
                // }

                if ($closer2Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestone->id,
                        'milestone_schema_id' => $milestoneTrigger->id,
                        'milestone_date' => @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null,
                        'type' => 'm'.($key + 1),
                        'is_last_date' => $isLast,
                        'is_exempted' => $isExempted,
                        'is_override' => $isOverRide,
                        'is_projected' => @$data['milestone_dates'][$key]['date'] ? 0 : 1,
                        'closer2_id' => $closer2Id,
                    ]);
                }

                if ($setter1Id && $closer1Id != $setter1Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestone->id,
                        'milestone_schema_id' => $milestoneTrigger->id,
                        'milestone_date' => @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null,
                        'type' => 'm'.($key + 1),
                        'is_last_date' => $isLast,
                        'is_exempted' => $isExempted,
                        'is_override' => $isOverRide,
                        'is_projected' => @$data['milestone_dates'][$key]['date'] ? 0 : 1,
                        'setter1_id' => $setter1Id,
                    ]);
                }

                if ($setter2Id && $closer2Id != $setter2Id) {
                    SaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestone->id,
                        'milestone_schema_id' => $milestoneTrigger->id,
                        'milestone_date' => @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null,
                        'type' => 'm'.($key + 1),
                        'is_last_date' => $isLast,
                        'is_exempted' => $isExempted,
                        'is_override' => $isOverRide,
                        'is_projected' => @$data['milestone_dates'][$key]['date'] ? 0 : 1,
                        'setter2_id' => $setter2Id,
                    ]);
                }

                // Added code for external worker
                $externalWorkerList = ExternalSaleWorker::where('pid', $pid)->get();
                if (! empty($externalWorkerList)) {
                    foreach ($externalWorkerList as $worker) {
                        ExternalSaleProductMaster::create([
                            'pid' => $pid,
                            'product_id' => $productId,
                            'milestone_id' => $milestone->id,
                            'milestone_schema_id' => $milestoneTrigger->id,
                            'milestone_date' => @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null,
                            'type' => 'm'.($key + 1),
                            'is_last_date' => $isLast,
                            'is_exempted' => $isExempted,
                            'is_override' => $isOverRide,
                            'is_projected' => @$data['milestone_dates'][$key]['date'] ? 0 : 1,
                            'worker_id' => $worker->user_id,
                            'worker_type' => $worker->type, // Worker type (1 = self gen, 2= Closer, 3= Setter)

                        ]);
                    }
                }

            }
        }

        if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            $overrideLocked = [];
            $sale = SalesMaster::with(['salesMasterProcess', 'productInfo' => function ($q) {
                $q->withTrashed();
            }])->where(['pid' => $pid])->first();

            $schemas = SaleProductMaster::with('milestoneSchemaTrigger')
                ->where('pid', $pid)
                ->get()
                ->map(function ($item) {
                    $item->forExternal = false;

                    return $item;
                });

            $externalSchemas = ExternalSaleProductMaster::with('milestoneSchemaTrigger')
                ->where('pid', $pid)
                ->get()
                ->map(function ($item) {
                    $item->forExternal = true;

                    return $item;
                });

            $mergedSchemas = $schemas->merge($externalSchemas);

            foreach ($mergedSchemas as $schema) {
                $forExternal = $schema->forExternal;

                $info = $this->salesRepData($schema, $forExternal);

                if ($info) {
                    if ($schema->is_last_date == '1') {
                        $this->lockCommissions($sale, $info, $forExternal);
                    } else {
                        $this->lockMilestones($sale, $info, $schema, $forExternal);
                    }

                    if ($schema->is_override == 1 && ! isset($overrideLocked[$info['id']])) {
                        $overrideLocked[$info['id']] = 1;
                        $this->lockSaleOverrides($pid, $info, $forExternal);
                    }
                }
            }
        }
    }

    public function salesRepData($schema, $forExternal = 0)
    {
        if (! $schema) {
            return [];
        }

        if ($forExternal) {
            if ($schema['worker_type'] == 2) {
                return [
                    'type' => 'closer',
                    'id' => $schema['worker_id'],
                ];
            } elseif ($schema['worker_type'] == 3) {
                return [
                    'type' => 'setter',
                    'id' => $schema['worker_id'],
                ];
            } elseif ($schema['worker_type'] == 1) {
                return [
                    'type' => 'selfgen',
                    'id' => $schema['worker_id'],
                ];
            }
        } else {
            if ($schema['setter1_id']) {
                return [
                    'type' => 'setter',
                    'id' => $schema['setter1_id'],
                ];
            }

            if ($schema['setter2_id']) {
                return [
                    'type' => 'setter2',
                    'id' => $schema['setter2_id'],
                ];
            }

            if ($schema['closer1_id']) {
                return [
                    'type' => 'closer',
                    'id' => $schema['closer1_id'],
                ];
            }

            if ($schema['closer2_id']) {
                return [
                    'type' => 'closer2',
                    'id' => $schema['closer2_id'],
                ];
            }
        }
    }

    public function milestoneWithSchema($productId, $effectiveDate, $check = true)
    {
        if (! $effectiveDate) {
            return [];
        }

        if (! $productId) {
            $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
            $productId = $product->id;
        }

        return ProductMilestoneHistories::with('milestone.milestone_trigger')->when($check, function ($q) {
            $q->whereHas('milestone', function ($q) {
                $q->where('status', '1');
            });
        })->where('product_id', $productId)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
    }

    public function manageDataForDisplay($pid, $reCalculate = true)
    {
        if ($reCalculate) {
            Artisan::call('syncSalesProjectionData:sync', ['pid' => $pid]);
        }
        $sale = SalesMaster::where('pid', $pid)->first();
        $saleTriggers = SaleProductMaster::where('pid', $pid)->get();

        $totalCommission = 0;
        $projectedCommission = 0;
        $checkFinalPayment = SaleProductMaster::where(['pid' => $pid, 'is_last_date' => '1'])->whereNotNull('milestone_date')->first();
        foreach ($saleTriggers as $saleTrigger) {
            $info = $this->salesRepData($saleTrigger);

            $userId = null;
            if ($info) {
                if ($info['type'] == 'closer') {
                    $userId = $saleTrigger->closer1_id;
                } elseif ($info['type'] == 'closer2') {
                    $userId = $saleTrigger->closer2_id;
                } elseif ($info['type'] == 'setter') {
                    $userId = $saleTrigger->setter1_id;
                } elseif ($info['type'] == 'setter2') {
                    $userId = $saleTrigger->setter2_id;
                }
            }

            $commission = 0;
            if ($saleTrigger->milestone_date || $checkFinalPayment) {
                $commission = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'schema_type' => $saleTrigger->type, 'is_displayed' => '1'])->sum('amount') ?? 0;
                $saleTrigger->is_projected = 0;
                $totalCommission += $commission;
            } else {
                if ($sale && ! $sale->date_cancelled) {
                    $commission = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'type' => $saleTrigger->type])->sum('amount') ?? 0;
                    $projectedCommission = 1;
                    $saleTrigger->is_projected = 1;
                    $totalCommission += $commission;
                } else {
                    $saleTrigger->is_projected = 0;
                }
            }

            $saleTrigger->amount = $commission;
            $saleTrigger->save();
        }

        $totalOverride = 0;
        $projectedOverride = 0;
        if (SaleProductMaster::where(['pid' => $pid, 'is_override' => '1'])->whereNotNull('milestone_date')->first() || $checkFinalPayment) {
            $totalOverride = UserOverrides::where(['pid' => $pid, 'is_displayed' => '1'])->sum('amount') ?? 0;
        } else {
            if ($sale && ! $sale->date_cancelled) {
                $totalOverride = ProjectionUserOverrides::where(['pid' => $pid])->sum('total_override') ?? 0;
                $projectedOverride = 1;
            }
        }

        // Added code to get the external worker commission and add in total commission
        $extrenalSaleTriggers = ExternalSaleProductMaster::where('pid', $pid)->get();
        $externalCheckFinalPayment = ExternalSaleProductMaster::where(['pid' => $pid, 'is_last_date' => '1'])->whereNotNull('milestone_date')->first();
        if (! empty($extrenalSaleTriggers)) {
            foreach ($extrenalSaleTriggers as $saleTrigger) {
                $userId = $saleTrigger->worker_id;

                $commission = 0;
                if ($saleTrigger->milestone_date || $externalCheckFinalPayment) {
                    $commission = UserCommission::where(['pid' => $pid, 'user_id' => $userId, 'schema_type' => $saleTrigger->type, 'is_displayed' => '1'])->sum('amount') ?? 0;
                    $saleTrigger->is_projected = 0;
                    $totalCommission += $commission;
                } else {
                    if ($sale && ! $sale->date_cancelled) {
                        $commission = ProjectionUserCommission::where(['pid' => $pid, 'user_id' => $userId, 'type' => $saleTrigger->type])->sum('amount') ?? 0;
                        $projectedCommission = 1;
                        $saleTrigger->is_projected = 1;
                        $totalCommission += $commission;
                    } else {
                        $saleTrigger->is_projected = 0;
                    }
                }

                $saleTrigger->amount = $commission;
                $saleTrigger->save();
            }
        }

        // Update SalesMaster with calculated values
        SalesMaster::where('pid', $pid)->update([
            'total_commission' => $totalCommission,
            'projected_commission' => $projectedCommission,
            'total_override' => $totalOverride,
            'projected_override' => $projectedOverride,
        ]);

        // Ensure ProjectionUserCommission data exists when projected_commission = 1
        if ($projectedCommission == 1) {
            $this->ensureProjectionDataExists($pid, $sale);
        }
    }

    public function getRedLineData($checked)
    {
        $productId = $checked->product_id;
        $approvedDate = $checked->customer_signoff;
        $closerId = isset($checked->salesMasterProcess->closer1Detail->id) ? $checked->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($checked->salesMasterProcess->closer2Detail->id) ? $checked->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($checked->salesMasterProcess->setter1Detail->id) ? $checked->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($checked->salesMasterProcess->setter2Detail->id) ? $checked->salesMasterProcess->setter2Detail->id : null;
        if (! $approvedDate || $approvedDate == '0000-00-00') {
            return [
                'closer1_is_redline_missing' => 1,
                'closer2_is_redline_missing' => 1,
                'setter1_is_redline_missing' => 1,
                'setter2_is_redline_missing' => 1,
            ];
        }

        if (config('app.domain_name') == 'flex') {
            $saleState = $checked->customer_state;
        } else {
            $saleState = $checked->location_code;
        }

        $saleStandardRedline = null;
        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        } else {
            $state = State::where('state_code', $saleState)->first();
            $saleStateId = isset($state->id) ? $state->id : 0;
            $location = Locations::where('state_id', $saleStateId)->first();
            $locationId = isset($location->id) ? $location->id : 0;
            $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        }

        if ($approvedDate) {
            $companyProfile = CompanyProfile::first();
            $data['closer1_redline'] = '0';
            $data['closer1_redline_type'] = null;
            $data['closer1_is_redline_missing'] = 0;
            $data['closer1_commission_type'] = null;
            $data['closer2_redline'] = '0';
            $data['closer2_redline_type'] = null;
            $data['closer2_is_redline_missing'] = 0;
            $data['closer2_commission_type'] = null;
            $data['setter1_redline'] = '0';
            $data['setter1_redline_type'] = null;
            $data['setter1_is_redline_missing'] = 0;
            $data['setter1_commission_type'] = null;
            $data['setter2_redline'] = '0';
            $data['setter2_redline_type'] = null;
            $data['setter2_is_redline_missing'] = 0;
            $data['setter2_commission_type'] = null;
            $data['external_data'] = [];

            $externalData = [];
            if ($setterId) {
                $setter = User::where('id', $setterId)->first();
                $setterRedLine = 0;
                $checkSetterRedLine = 0;
                $setterRedLineAmountType = null;
                $setterMissingRedLine = 0;
                $userOrganizationData = checkUsersProductForCalculations($setterId, $approvedDate, $productId);
                $userOrganizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                if ($closerId == $setterId && @$userOrganizationHistory->self_gen_accounts == 1) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commissionType = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commissionHistory) {
                            $setterRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $setterRedLineAmountType = $commissionHistory->commission_type;
                        }
                    } else {
                        if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                            $setterRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $setterRedLineAmountType = $commissionHistory->commission_type;
                        } else {
                            $userRedLine = UserRedlines::where(['user_id' => $setterId, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($userRedLine) {
                                $checkSetterRedLine = 1;
                                $setterRedLine = $userRedLine->redline;
                                $setterRedLineAmountType = $userRedLine->redline_amount_type;
                            }
                        }
                    }
                } else {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $setterId, 'product_id' => $actualProductId, 'core_position_id' => '3'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commissionType = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commissionHistory) {
                            $setterRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $setterRedLineAmountType = $commissionHistory->commission_type;
                        }
                    } else {
                        if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                            $setterRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $setterRedLineAmountType = $commissionHistory->commission_type;
                        } else {
                            $userRedLine = UserRedlines::where(['user_id' => $setterId, 'core_position_id' => '3', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($userRedLine) {
                                $checkSetterRedLine = 1;
                                $setterRedLine = $userRedLine->redline;
                                $setterRedLineAmountType = $userRedLine->redline_amount_type;
                            }
                        }
                    }
                }

                $setterOfficeId = $setter->office_id;
                $userTransferHistory = UserTransferHistory::where('user_id', $setterId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $setterOfficeId = $userTransferHistory->office_id;
                }
                $setterLocation = Locations::with('state')->where('id', $setterOfficeId)->first();
                $locationId = isset($setterLocation->id) ? $setterLocation->id : 0;
                $location1RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                $setterStateRedLine = null;
                if ($location1RedLines) {
                    $setterStateRedLine = $location1RedLines->redline_standard;
                }

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                    $data['setter1_redline'] = $setterRedLine;
                    $data['setter1_redline_type'] = $setterRedLineAmountType;
                } else {
                    if ($setterRedLineAmountType != 'per kw' && $setterRedLineAmountType != 'per sale') {
                        if (strtolower($setterRedLineAmountType) == strtolower('Fixed')) {
                            $data['setter1_redline'] = $setterRedLine;
                            $data['setter1_redline_type'] = 'Fixed';
                        } elseif (strtolower($setterRedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($locationRedlines && $checkSetterRedLine && $location1RedLines) {
                                $redLine = $saleStandardRedline + ($setterRedLine - $setterStateRedLine);
                            } else {
                                $setterMissingRedLine = 1;
                            }
                            $data['setter1_redline'] = $redLine;
                            $data['setter1_redline_type'] = 'Shift Based on Location';
                        } elseif (strtolower($setterRedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetterRedLine) {
                                $setterProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setterRedLine + $setterProductRedLine;
                            } else {
                                $setterMissingRedLine = 1;
                            }

                            $data['setter1_redline'] = $redLine;
                            $data['setter1_redline_type'] = 'Shift Based on Product';
                        } elseif (strtolower($setterRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $locationRedlines && $checkSetterRedLine && $location1RedLines) {
                                $setterProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setterRedLine - ($setterStateRedLine - $saleStandardRedline) + $setterProductRedLine;
                            } else {
                                $setterMissingRedLine = 1;
                            }
                            $data['setter1_redline'] = $redLine;
                            $data['setter1_redline_type'] = 'Shift Based on Product & Location';
                        } else {
                            $setterMissingRedLine = 1;
                        }
                    } else {
                        $data['setter1_redline'] = $setterRedLine;
                        $data['setter1_redline_type'] = $setterRedLineAmountType;
                    }
                }

                $data['setter1_commission_type'] = $commissionType;
                $data['setter1_is_redline_missing'] = $setterMissingRedLine;
            }

            if ($setter2Id) {
                $setter2 = User::where('id', $setter2Id)->first();
                $setter2RedLine = 0;
                $checkSetter2RedLine = 0;
                $setter2RedLineAmountType = null;
                $setter2MissingRedLine = 0;
                $userOrganizationData = checkUsersProductForCalculations($setter2Id, $approvedDate, $productId);
                $userOrganizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                if ($closer2Id == $setter2Id && @$userOrganizationHistory->self_gen_accounts == 1) {
                    $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commission2Type = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commission2History) {
                            $setter2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $setter2RedLineAmountType = $commission2History->commission_type;
                        }
                    } else {
                        if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                            $setter2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $setter2RedLineAmountType = $commission2History->commission_type;
                        } else {
                            $user2RedLine = UserRedlines::where(['user_id' => $setter2Id, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($user2RedLine) {
                                $checkSetter2RedLine = 1;
                                $setter2RedLine = $user2RedLine->redline;
                                $setter2RedLineAmountType = $user2RedLine->redline_amount_type;
                            }
                        }
                    }
                } else {
                    $commission2History = UserCommissionHistory::where(['user_id' => $setter2Id, 'product_id' => $actualProductId, 'core_position_id' => '3'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commission2Type = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commission2History) {
                            $setter2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $setter2RedLineAmountType = $commission2History->commission_type;
                        }
                    } else {
                        if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                            $setter2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $setter2RedLineAmountType = $commission2History->commission_type;
                        } else {
                            $user2RedLine = UserRedlines::where(['user_id' => $setter2Id, 'core_position_id' => '3', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($user2RedLine) {
                                $checkSetter2RedLine = 1;
                                $setter2RedLine = $user2RedLine->redline;
                                $setter2RedLineAmountType = $user2RedLine->redline_amount_type;
                            }
                        }
                    }
                }

                $setter2OfficeId = $setter2->office_id;
                $userTransferHistory = UserTransferHistory::where('user_id', $setter2Id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $setter2OfficeId = $userTransferHistory->office_id;
                }
                $setter2Location = Locations::with('state')->where('id', $setter2OfficeId)->first();
                $locationId = isset($setter2Location->id) ? $setter2Location->id : 0;
                $location2RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                $setter2StateRedLine = null;
                if ($location2RedLines) {
                    $setter2StateRedLine = $location2RedLines->redline_standard;
                }

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                    $data['setter2_redline'] = $setter2RedLine;
                    $data['setter2_redline_type'] = $setter2RedLineAmountType;
                } else {
                    if ($setter2RedLineAmountType != 'per kw' && $setter2RedLineAmountType != 'per sale') {
                        if (strtolower($setter2RedLineAmountType) == strtolower('Fixed')) {
                            $data['setter2_redline'] = $setter2RedLine;
                            $data['setter2_redline_type'] = 'Fixed';
                        } elseif (strtolower($setter2RedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($locationRedlines && $checkSetter2RedLine && $location2RedLines) {
                                $redLine = $saleStandardRedline + ($setter2RedLine - $setter2StateRedLine);
                            } else {
                                $setter2MissingRedLine = 1;
                            }
                            $data['setter2_redline'] = $redLine;
                            $data['setter2_redline_type'] = 'Shift Based on Location';
                        } elseif (strtolower($setter2RedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkSetter2RedLine) {
                                $setter2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setter2RedLine + $setter2ProductRedLine;
                            } else {
                                $setter2MissingRedLine = 1;
                            }

                            $data['setter2_redline'] = $redLine;
                            $data['setter2_redline_type'] = 'Shift Based on Product';
                        } elseif (strtolower($setter2RedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($locationRedlines && $checkSetter2RedLine && $location2RedLines && $productRedLine) {
                                $setter2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $setter2RedLine - ($setter2StateRedLine - $saleStandardRedline) + $setter2ProductRedLine;
                            } else {
                                $setter2MissingRedLine = 1;
                            }
                            $data['setter2_redline'] = $redLine;
                            $data['setter2_redline_type'] = 'Shift Based on Product & Location';
                        } else {
                            $setter2MissingRedLine = 1;
                        }
                    } else {
                        $data['setter2_redline'] = $setter2RedLine;
                        $data['setter2_redline_type'] = $setter2RedLineAmountType;
                    }
                }

                $data['setter2_commission_type'] = $commission2Type;
                $data['setter2_is_redline_missing'] = $setter2MissingRedLine;
            }

            if ($closerId) {
                $closer = User::where('id', $closerId)->first();
                $closerRedLine = 0;
                $checkCloserRedLine = 0;
                $closerRedLineAmountType = null;
                $closerMissingRedLine = 0;
                $userOrganizationData = checkUsersProductForCalculations($closerId, $approvedDate, $productId);
                $userOrganizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                if ($setterId == $closerId && @$userOrganizationHistory->self_gen_accounts == 1) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commissionType = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commissionHistory) {
                            $closerRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $closerRedLineAmountType = $commissionHistory->commission_type;
                        }
                    } else {
                        if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                            $closerRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $closerRedLineAmountType = $commissionHistory->commission_type;
                        } else {
                            $userRedLine = UserRedlines::where(['user_id' => $closerId, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($userRedLine) {
                                $checkCloserRedLine = 1;
                                $closerRedLine = $userRedLine->redline;
                                $closerRedLineAmountType = $userRedLine->redline_amount_type;
                            }
                        }
                    }
                } else {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $closerId, 'product_id' => $actualProductId, 'core_position_id' => '2'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commissionType = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commissionHistory) {
                            $closerRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $closerRedLineAmountType = $commissionHistory->commission_type;
                        }
                    } else {
                        if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                            $closerRedLine = $commissionHistory->commission;
                            $commissionType = $commissionHistory->commission_type;
                            $closerRedLineAmountType = $commissionHistory->commission_type;
                        } else {
                            $userRedLine = UserRedlines::where(['user_id' => $closerId, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($userRedLine) {
                                $checkCloserRedLine = 1;
                                $closerRedLine = $userRedLine->redline;
                                $closerRedLineAmountType = $userRedLine->redline_amount_type;
                            }
                        }
                    }
                }

                $closerOfficeId = $closer->office_id;
                $userTransferHistory = UserTransferHistory::where('user_id', $closerId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $closerOfficeId = $userTransferHistory->office_id;
                }
                $closerLocation = Locations::with('state')->where('id', $closerOfficeId)->first();
                $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                $location3RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                $closerStateRedLine = null;
                if ($location3RedLines) {
                    $closerStateRedLine = $location3RedLines->redline_standard;
                }

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                    $data['closer1_redline'] = $closerRedLine;
                    $data['closer1_redline_type'] = $closerRedLineAmountType;
                } else {
                    if ($closerRedLineAmountType != 'per kw' && $closerRedLineAmountType != 'per sale') {
                        if (strtolower($closerRedLineAmountType) == strtolower('Fixed')) {
                            $data['closer1_redline'] = $closerRedLine;
                            $data['closer1_redline_type'] = 'Fixed';
                        } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($locationRedlines && $checkCloserRedLine && $location3RedLines) {
                                $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                            } else {
                                $closerMissingRedLine = 1;
                            }
                            $data['closer1_redline'] = $redLine;
                            $data['closer1_redline_type'] = 'Shift Based on Location';
                        } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloserRedLine) {
                                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closerRedLine + $closerProductRedLine;
                            } else {
                                $closerMissingRedLine = 1;
                            }

                            $data['closer1_redline'] = $redLine;
                            $data['closer1_redline_type'] = 'Shift Based on Product';
                        } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($locationRedlines && $checkCloserRedLine && $location3RedLines && $productRedLine) {
                                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closerRedLine - ($closerStateRedLine - $saleStandardRedline) + $closerProductRedLine;
                            } else {
                                $closerMissingRedLine = 1;
                            }
                            $data['closer1_redline'] = $redLine;
                            $data['closer1_redline_type'] = 'Shift Based on Product & Location';
                        } else {
                            $closerMissingRedLine = 1;
                        }
                    } else {
                        $data['closer1_redline'] = $closerRedLine;
                        $data['closer1_redline_type'] = $closerRedLineAmountType;
                    }
                }

                $data['closer1_commission_type'] = $commissionType;
                $data['closer1_is_redline_missing'] = $closerMissingRedLine;
            }

            if ($closer2Id) {
                $closer2 = User::where('id', $closer2Id)->first();
                $closer2RedLine = 0;
                $checkCloser2RedLine = 0;
                $closer2RedLineAmountType = null;
                $closer2MissingRedLine = 0;
                $userOrganizationData = checkUsersProductForCalculations($closer2Id, $approvedDate, $productId);
                $userOrganizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                if ($setter2Id == $closer2Id && @$userOrganizationHistory->self_gen_accounts == 1) {
                    $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commission2Type = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commission2History) {
                            $closer2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $closer2RedLineAmountType = $commission2History->commission_type;
                        }
                    } else {
                        if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                            $closer2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $closer2RedLineAmountType = $commission2History->commission_type;
                        } else {
                            $user2RedLine = UserRedlines::where(['user_id' => $closer2Id, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($user2RedLine) {
                                $checkCloser2RedLine = 1;
                                $closer2RedLine = $user2RedLine->redline;
                                $closer2RedLineAmountType = $user2RedLine->redline_amount_type;
                            }
                        }
                    }
                } else {
                    $commission2History = UserCommissionHistory::where(['user_id' => $closer2Id, 'product_id' => $actualProductId, 'core_position_id' => '2'])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    $commission2Type = 'percent';
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                        if ($commission2History) {
                            $closer2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $closer2RedLineAmountType = $commission2History->commission_type;
                        }
                    } else {
                        if ($commission2History && ($commission2History->commission_type == 'per kw' || $commission2History->commission_type == 'per sale')) {
                            $closer2RedLine = $commission2History->commission;
                            $commission2Type = $commission2History->commission_type;
                            $closer2RedLineAmountType = $commission2History->commission_type;
                        } else {
                            $user2RedLine = UserRedlines::where(['user_id' => $closer2Id, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($user2RedLine) {
                                $checkCloser2RedLine = 1;
                                $closer2RedLine = $user2RedLine->redline;
                                $closer2RedLineAmountType = $user2RedLine->redline_amount_type;
                            }
                        }
                    }
                }

                $closer2OfficeId = $closer2->office_id;
                $userTransferHistory = UserTransferHistory::where('user_id', $closer2Id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
                if ($userTransferHistory) {
                    $closer2OfficeId = $userTransferHistory->office_id;
                }
                $closer2Location = Locations::with('state')->where('id', $closer2OfficeId)->first();
                $locationId = isset($closer2Location->id) ? $closer2Location->id : 0;
                $location4RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                $closer2StateRedLine = null;
                if ($location4RedLines) {
                    $closer2StateRedLine = $location4RedLines->redline_standard;
                }

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                    $data['closer2_redline'] = $closer2RedLine;
                    $data['closer2_redline_type'] = $closer2RedLineAmountType;
                } else {
                    if ($closer2RedLineAmountType != 'per kw' && $closer2RedLineAmountType != 'per sale') {
                        if (strtolower($closer2RedLineAmountType) == strtolower('Fixed')) {
                            $data['closer2_redline'] = $closer2RedLine;
                            $data['closer2_redline_type'] = 'Fixed';
                        } elseif (strtolower($closer2RedLineAmountType) == strtolower('Shift Based on Location')) {
                            $redLine = 0;
                            if ($locationRedlines && $checkCloser2RedLine && $location4RedLines) {
                                $redLine = $saleStandardRedline + ($closer2RedLine - $closer2StateRedLine);
                            } else {
                                $closer2MissingRedLine = 1;
                            }
                            $data['closer2_redline'] = $redLine;
                            $data['closer2_redline_type'] = 'Shift Based on Location';
                        } elseif (strtolower($closer2RedLineAmountType) == strtolower('Shift Based on Product')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($productRedLine && $checkCloser2RedLine) {
                                $closer2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closer2RedLine + $closer2ProductRedLine;
                            } else {
                                $closer2MissingRedLine = 1;
                            }

                            $data['closer2_redline'] = $redLine;
                            $data['closer2_redline_type'] = 'Shift Based on Product';
                        } elseif (strtolower($closer2RedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                            $redLine = 0;
                            $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if ($locationRedlines && $checkCloser2RedLine && $location4RedLines && $productRedLine) {
                                $closer2ProductRedLine = $productRedLine->product_redline ?? 0;
                                $redLine = $closer2RedLine - ($closer2StateRedLine - $saleStandardRedline) + $closer2ProductRedLine;
                            } else {
                                $closer2MissingRedLine = 1;
                            }
                            $data['closer2_redline'] = $redLine;
                            $data['closer2_redline_type'] = 'Shift Based on Product & Location';
                        } else {
                            $closer2MissingRedLine = 1;
                        }
                    } else {
                        $data['closer2_redline'] = $closer2RedLine;
                        $data['closer2_redline_type'] = $closer2RedLineAmountType;
                    }
                }

                $data['closer2_commission_type'] = $commission2Type;
                $data['closer2_is_redline_missing'] = $closer2MissingRedLine;
            }

            if ($closerId && $setterId && $closerId == $setterId) {
                $redLine1 = $data['setter1_redline'];
                $redLine2 = $data['closer1_redline'];
                if ($redLine1 > $redLine2) {
                    $data['closer1_redline'] = $redLine2;
                    $data['closer1_redline_type'] = $data['closer1_redline_type'];
                } else {
                    $data['closer1_redline'] = $redLine1;
                    $data['closer1_redline_type'] = $data['setter1_redline_type'];
                }
            }

            if ($closer2Id && $setter2Id && $closer2Id == $setter2Id) {
                $redLine1 = $data['setter2_redline'];
                $redLine2 = $data['closer2_redline'];
                if ($redLine1 > $redLine2) {
                    $data['closer2_redline'] = $redLine2;
                    $data['closer2_redline_type'] = $data['closer2_redline_type'];
                } else {
                    $data['closer2_redline'] = $redLine1;
                    $data['closer2_redline_type'] = $data['setter2_redline_type'];
                }
            }

            if ($checked->externalSaleWorker->count() > 0) {
                foreach ($checked->externalSaleWorker as $worker) {
                    if ($worker->user_id) {
                        $closer = User::where('id', $worker->user_id)->first();
                        $closerRedLine = 0;
                        $checkCloserRedLine = 0;
                        $closerRedLineAmountType = null;
                        $closerMissingRedLine = 0;
                        $userOrganizationData = checkUsersProductForCalculations($worker->user_id, $approvedDate, $productId);
                        $userOrganizationHistory = $userOrganizationData['organization'];
                        $actualProductId = $userOrganizationData['product']->id;
                        if ($worker->type == 2 || $worker->type == 1) {
                            $corePosition = 2;
                        } elseif ($worker->type == 3) {
                            $corePosition = 3;
                        }
                        if ($worker->type == 1 && @$userOrganizationHistory->self_gen_accounts == 1) {
                            $commissionHistory = UserCommissionHistory::where(['user_id' => $worker->user_id, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            $commissionType = 'percent';
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                                if ($commissionHistory) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                }
                            } else {
                                if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                } else {
                                    $userRedLine = UserRedlines::where(['user_id' => $worker->user_id, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($userRedLine) {
                                        $checkCloserRedLine = 1;
                                        $closerRedLine = $userRedLine->redline;
                                        $closerRedLineAmountType = $userRedLine->redline_amount_type;
                                    }
                                }
                            }
                        } else {
                            $commissionHistory = UserCommissionHistory::where(['user_id' => $worker->user_id, 'product_id' => $actualProductId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            $commissionType = 'percent';
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                                if ($commissionHistory) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                }
                            } else {
                                if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                } else {
                                    $userRedLine = UserRedlines::where(['user_id' => $worker->user_id, 'core_position_id' => $corePosition, 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($userRedLine) {
                                        $checkCloserRedLine = 1;
                                        $closerRedLine = $userRedLine->redline;
                                        $closerRedLineAmountType = $userRedLine->redline_amount_type;
                                    }
                                }
                            }
                        }

                        $closerOfficeId = $closer->office_id;
                        $userTransferHistory = UserTransferHistory::where('user_id', $worker->user_id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
                        if ($userTransferHistory) {
                            $closerOfficeId = $userTransferHistory->office_id;
                        }
                        $closerLocation = Locations::with('state')->where('id', $closerOfficeId)->first();
                        $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                        $location3RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        $closerStateRedLine = null;
                        if ($location3RedLines) {
                            $closerStateRedLine = $location3RedLines->redline_standard;
                        }

                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf') || ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast')) {
                            $externalData[] = ['worker_id' => $worker->user_id, 'redline' => $closerRedLine, 'redline_type' => $closerRedLineAmountType];
                        } else {
                            if ($closerRedLineAmountType != 'per kw' && $closerRedLineAmountType != 'per sale') {
                                if (strtolower($closerRedLineAmountType) == strtolower('Fixed')) {
                                    $externalData[] = ['worker_id' => $worker->user_id, 'redline' => $closerRedLine, 'redline_type' => 'Fixed'];
                                } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Location')) {
                                    $redLine = 0;
                                    if ($locationRedlines && $checkCloserRedLine && $location3RedLines) {
                                        $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                                    } else {
                                        $closerMissingRedLine = 1;
                                    }
                                    $externalData[] = ['worker_id' => $worker->user_id, 'redline' => $redLine, 'redline_type' => 'Shift Based on Location'];
                                } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product')) {
                                    $redLine = 0;
                                    $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($productRedLine && $checkCloserRedLine) {
                                        $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                        $redLine = $closerRedLine + $closerProductRedLine;
                                    } else {
                                        $closerMissingRedLine = 1;
                                    }
                                    $externalData[] = ['worker_id' => $worker->user_id, 'redline' => $redLine, 'redline_type' => 'Shift Based on Product'];
                                } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                                    $redLine = 0;
                                    $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($locationRedlines && $checkCloserRedLine && $location3RedLines && $productRedLine) {
                                        $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                        $redLine = $closerRedLine - ($closerStateRedLine - $saleStandardRedline) + $closerProductRedLine;
                                    } else {
                                        $closerMissingRedLine = 1;
                                    }
                                    $externalData[] = ['worker_id' => $worker->user_id, 'redline' => $redLine, 'redline_type' => 'Shift Based on Product & Location'];
                                } else {
                                    $closerMissingRedLine = 1;
                                }
                            } else {
                                $externalData[] = ['worker_id' => $worker->user_id, 'redline' => $closerRedLine, 'redline_type' => $closerRedLineAmountType];
                            }
                        }

                        $externalData[] = ['worker_id' => $worker->user_id, 'commission_type' => $commissionType, 'is_redline_missing' => $closerMissingRedLine];
                    }
                }

            }

            if (! empty($externalData)) {
                $data['external_data'] = $externalData;
            }

            // Added to make redline 0 for MORTGAGE company type if domain is not firstcoast
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                $data['redline'] = 0;
            }

            // For external user redline
            return $data;
        }
    }

    public function upfrontTypePercentCalculationForPest($sale, $info, $companyProfile, $forExternal = 0)
    {
        $pid = $sale->pid;
        $userId = $info['id'];
        $productId = $sale->product_id;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $approvalDate = $sale->customer_signoff;
        $grossAmountValue = $sale->gross_account_value;
        $isHalf = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
        }

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
        $userOrganizationHistory = $userOrganizationData['organization'];
        $productId = $userOrganizationData['product']->id;

        $amount = 0;
        $subPositionId = @$userOrganizationHistory['sub_position_id'];
        $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $commission) {
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
        }
        if ($commission && $commission->commission_status == 1) {
            $commissionType = null;
            $commissionPercentage = 0;
            $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($commissionHistory) {
                if ($commissionHistory->tiers_id) {
                    $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                    if ($level) {
                        $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                            $q->where('level', $level->tier_level);
                        })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                        if ($commissionTier) {
                            $commissionPercentage = $commissionTier->value;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                } else {
                    $commissionPercentage = $commissionHistory->commission;
                    $commissionType = $commissionHistory->commission_type;
                }
            }

            if ($commissionPercentage && $commissionType) {
                if ($commissionType == 'per sale') {
                    $amount = $commissionPercentage * $x;
                } else {
                    $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                }

                if ($isHalf) {
                    $amount = ($amount / 2);
                }
                $commissionLimitType = $commission->commission_limit_type ?? null;
                if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                    // Apply percentage commission
                    $commissionAmount = $amount * ($commission->commission_limit / 100);
                    if ($amount > $commissionAmount) {
                        $amount = $commissionAmount;
                    }
                } else {
                    if ($commission->commission_limit && $amount > $commission->commission_limit) {
                        $amount = $commission->commission_limit;
                    }
                }
            }
        }

        return $amount;
    }

    public function upfrontTypePercentCalculationForSolar($sale, $info, $redLine, $companyProfile, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;

        if (! User::find($userId)) {
            return 0;
        }

        $isHalf = false;
        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
        $userOrganizationHistory = $userOrganizationData['organization'];
        $productId = $userOrganizationData['product']->id;

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $amount = 0;
        $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $commission) {
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
        }
        if ($commission && $commission->commission_status == 1) {
            $commissionHistory = null;
            if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                if ($isSelfGen) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                } elseif ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                    $corePosition = '';
                    if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }

                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    }
                }
            } else {
                $corePosition = '';
                if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                }
                if ($corePosition) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            $commissionType = null;
            $commissionPercentage = 0;
            if ($commissionHistory) {
                if ($commissionHistory->tiers_id) {
                    $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                    if ($level) {
                        $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                            $q->where('level', $level->tier_level);
                        })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                        if ($commissionTier) {
                            $commissionPercentage = $commissionTier->value;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                } else {
                    $commissionPercentage = $commissionHistory->commission;
                    $commissionType = $commissionHistory->commission_type;
                }
            }

            if ($commissionPercentage && $commissionType) {
                if ($commissionType == 'per kw') {
                    $amount = (($kw * $commissionPercentage) * $x);
                } elseif ($commissionType == 'per sale') {
                    $amount = $commissionPercentage * $x;
                } else {
                    $amount = ((($netEpc - $redLine) * $x) * $kw * 1000 * $commissionPercentage / 100);
                }

                if ($isHalf) {
                    $amount = ($amount / 2);
                }
                $commissionLimitType = $commission->commission_limit_type ?? null;
                if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                    // Apply percentage commission
                    $commissionAmount = $amount * ($commission->commission_limit / 100);
                    if ($amount > $commissionAmount) {
                        $amount = $commissionAmount;
                    }
                } else {
                    if ($commission->commission_limit && $amount > $commission->commission_limit) {
                        $amount = $commission->commission_limit;
                    }
                }
            }
        }

        return $amount;
    }

    public function upfrontTypePercentCalculationForTurf($sale, $info, $companyProfile, $redLine = 0, $forExternal = 0)
    {
        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $grossAmountValue = $sale->gross_account_value;
        // $redLine = 0;

        if (! User::find($userId)) {
            return 0;
        }

        $isHalf = false;
        $isSelfGen = false;
        if ($forExternal == 0) {
            if ($info['type'] == 'setter' || $info['type'] == 'setter2') {
                if ($info['type'] == 'setter' && ! empty($setter2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'setter2' && ! empty($setterId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' || $info['type'] == 'closer2') {
                if ($info['type'] == 'closer' && ! empty($closer2Id)) {
                    $isHalf = true;
                }
                if ($info['type'] == 'closer2' && ! empty($closerId)) {
                    $isHalf = true;
                }
            }
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
        $userOrganizationHistory = $userOrganizationData['organization'];
        $productId = $userOrganizationData['product']->id;

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $amount = 0;
        $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $commission) {
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
        }
        if ($commission) {
            $commissionHistory = null;
            if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                if ($isSelfGen) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                } elseif ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                    $corePosition = '';
                    if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }

                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    }
                }
            } else {
                $corePosition = '';
                if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                }
                if ($corePosition) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            $commissionType = null;
            $commissionPercentage = 0;
            if ($commissionHistory) {
                if ($commissionHistory->tiers_id) {
                    $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                    if ($level) {
                        $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                            $q->where('level', $level->tier_level);
                        })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                        if ($commissionTier) {
                            $commissionPercentage = $commissionTier->value;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                } else {
                    $commissionPercentage = $commissionHistory->commission;
                    $commissionType = $commissionHistory->commission_type;
                }
            }

            if ($commissionPercentage && $commissionType) {
                if ($commissionType == 'per kw') {
                    $amount = (($kw * $commissionPercentage) * $x);
                } elseif ($commissionType == 'per sale') {
                    $amount = $commissionPercentage * $x;
                } else {
                    $amount = (($grossAmountValue + ((($netEpc - $redLine) * $x) * $kw * 1000)) * $commissionPercentage / 100);
                }

                if ($isHalf) {
                    $amount = ($amount / 2);
                }
                $commissionLimitType = $commission->commission_limit_type ?? null;
                if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                    // Apply percentage commission
                    $commissionAmount = $amount * ($commission->commission_limit / 100);
                    if ($amount > $commissionAmount) {
                        $amount = $commissionAmount;
                    }
                } else {
                    if ($commission->commission_limit && $amount > $commission->commission_limit) {
                        $amount = $commission->commission_limit;
                    }
                }
            }
        }

        return $amount;
    }

    public function upfrontTypePercentCalculationForMortgage($sale, $info, $companyProfile, $redLine = 0, $forExternal = 0)
    {
        // Apply condition for MORTGAGE company type: if domain != 'firstcoast' then redline = 0
        if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
            $redLine = 0;
        }

        $kw = $sale->kw;
        $pid = $sale->pid;
        $userId = $info['id'];
        $netEpc = $sale->net_epc;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = isset($sale->salesMasterProcess->closer1Detail->id) ? $sale->salesMasterProcess->closer1Detail->id : null;
        $closer2Id = isset($sale->salesMasterProcess->closer2Detail->id) ? $sale->salesMasterProcess->closer2Detail->id : null;
        $setterId = isset($sale->salesMasterProcess->setter1Detail->id) ? $sale->salesMasterProcess->setter1Detail->id : null;
        $setter2Id = isset($sale->salesMasterProcess->setter2Detail->id) ? $sale->salesMasterProcess->setter2Detail->id : null;
        $grossAmountValue = $sale->gross_account_value;
        // $redLine = $redLine ? $redLine / 100 : 0; // Convert redLine from percent to decimal

        if (! User::find($userId)) {
            return 0;
        }

        $isSelfGen = false;

        if ($forExternal == 0) {
            if ($info['type'] == 'closer' && $setterId == $closerId) {
                $isSelfGen = true;
            }
            if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                $isSelfGen = true;
            }
        } else {
            if ($info['type'] == 'selfgen') {
                $isSelfGen = true;
            }
        }

        $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
        $userOrganizationHistory = $userOrganizationData['organization'];
        $productId = $userOrganizationData['product']->id;

        $x = 1;
        if (isset($companyProfile->company_margin) && $companyProfile->company_margin > 0) {
            $marginPercentage = $companyProfile->company_margin;
            $x = ((100 - $marginPercentage) / 100);
        }

        $amount = 0;
        $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $commission) {
            $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->whereNull('effective_date')->first();
        }
        if ($commission) {
            $commissionHistory = null;
            if (@$userOrganizationHistory['self_gen_accounts'] == 1) {
                if ($isSelfGen) {
                    $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->where('self_gen_user', '1')->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $commission) {
                        $commission = PositionCommission::where(['position_id' => @$userOrganizationHistory->sub_position_id, 'product_id' => $productId])->where('self_gen_user', '1')->whereNull('effective_date')->first();
                    }
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                } elseif ($userOrganizationHistory['position_id'] == '2' || $userOrganizationHistory['position_id'] == '3') {
                    $corePosition = '';
                    if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                        $corePosition = 3;
                    } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                        $corePosition = 2;
                    }

                    if ($corePosition) {
                        $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    }
                }
            } else {
                $corePosition = '';
                if ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '2' && ($info['type'] == 'setter' || $info['type'] == 'setter2')) {
                    $corePosition = 3;
                } elseif ($userOrganizationHistory['position_id'] == '3' && ($info['type'] == 'closer' || $info['type'] == 'closer2')) {
                    $corePosition = 2;
                }
                if ($corePosition) {
                    $commissionHistory = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvalDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            $commissionType = null;
            $commissionPercentage = 0;
            if ($commissionHistory) {
                if ($commissionHistory->tiers_id) {
                    $level = SaleTiersDetail::where(['pid' => $pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'])->whereNotNull('tier_level')->first();
                    if ($level) {
                        $commissionTier = UserCommissionHistoryTiersRange::whereHas('level', function ($q) use ($level) {
                            $q->where('level', $level->tier_level);
                        })->with('level')->where(['user_commission_history_id' => $commissionHistory->id])->first();
                        if ($commissionTier) {
                            $commissionPercentage = $commissionTier->value;
                            $commissionType = $commissionHistory->commission_type;
                        }
                    } else {
                        $commissionPercentage = $commissionHistory->commission;
                        $commissionType = $commissionHistory->commission_type;
                    }
                } else {
                    $commissionPercentage = $commissionHistory->commission;
                    $commissionType = $commissionHistory->commission_type;
                }
            }

            if ($commissionPercentage && $commissionType) {
                if ($commissionType == 'per sale') {
                    $amount = $commissionPercentage * $x;
                } else {
                    // Percentage-based commission: Different formula based on domain
                    if (strtolower(config('app.domain_name')) == 'firstcoast') {
                        // Firstcoast: Keep original formula using netEpc, redLine, and kw
                        $redLine = $redLine ? $redLine / 100 : 0;
                        $amount = ((((($netEpc - $redLine) * $x) * $kw)) * $commissionPercentage / 100);
                    } else {
                        // Other mortgage domains: Use gross_account_value based formula
                        $amount = (($grossAmountValue * $commissionPercentage * $x) / 100);
                    }
                }

                $commissionLimitType = $commission->commission_limit_type ?? null;
                if ($commissionLimitType == 'percent' && $commission->commission_limit > 0) {
                    // Apply percentage commission
                    $commissionAmount = $kw * ($commission->commission_limit / 100);
                    if ($amount > $commissionAmount) {
                        $amount = $commissionAmount;
                    }
                } else {
                    if ($commission->commission_limit && $amount > $commission->commission_limit) {
                        $amount = $commission->commission_limit;
                    }
                }
            }
        }

        return $amount;
    }

    public function userPerSale($userId, $saleState, $approvedDate, $commissionHistory, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per sale') {
            if ($history->commission > $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        } elseif ($history->commission_type == 'per kw') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
                'product_id' => $productId,
                'product_code' => $productCode,
            ];
        } elseif ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            if (! $userRedLine['redline_missing']) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function userPerKw($userId, $saleState, $approvedDate, $commissionHistory, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per kw') {
            if ($history->commission > $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        } elseif ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            if (! $userRedLine['redline_missing']) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function userPercentage($userId, $saleState, $approvedDate, $closerRedLine, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            if (! $userRedLine['redline_missing'] && $userRedLine['redline'] <= $closerRedLine) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function userRedline($userData, $saleState, $approvedDate, $productId)
    {
        $userId = $userData->id;
        $saleStandardRedline = null;
        $saleStandardRedlineCheck = 0;
        $companyProfile = CompanyProfile::first();
        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();

            if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $locationRedlines = 0;
                if (strtolower(config('app.domain_name')) == 'firstcoast') {
                    $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'ASC')->first();
                }
            }

            if ($locationRedlines) {
                $saleStandardRedlineCheck = 1;
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        } else {
            $state = State::where('state_code', $saleState)->first();
            $saleStateId = isset($state->id) ? $state->id : 0;
            $location = Locations::where('state_id', $saleStateId)->first();
            $locationId = isset($location->id) ? $location->id : 0;
            $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();

            if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $locationRedlines = 0;
                if (strtolower(config('app.domain_name')) == 'firstcoast') {
                    $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'ASC')->first();
                }
            }

            if ($locationRedlines) {
                $saleStandardRedlineCheck = 1;
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        }

        $closerRedLine = 0;
        $closerRedLineCheck = 0;
        $closerRedLineAmountType = null;
        $userRedLine = UserRedlines::where(['user_id' => $userId, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
        if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $userRedLine = 0;
            if (strtolower(config('app.domain_name')) == 'firstcoast') {
                $userRedLine = UserRedlines::where(['user_id' => $userId, 'core_position_id' => '2', 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'ASC')->orderBy('id', 'ASC')->first();
            }
        }
        if ($userRedLine) {
            $closerRedLineCheck = 1;
            $closerRedLine = $userRedLine->redline;
            $closerRedLineAmountType = $userRedLine->redline_amount_type;
        }

        $userTransferHistory = UserTransferHistory::where('user_id', $userId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
        if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
            $userTransferHistory = UserTransferHistory::where('user_id', $userId)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'ASC')->orderBy('id', 'ASC')->first();
        }
        if ($userTransferHistory) {
            $closerOfficeId = $userTransferHistory->office_id;
        } else {
            $closerOfficeId = $userData->office_id;
        }

        if (strtolower($closerRedLineAmountType) == strtolower('Fixed')) {
            return [
                'redline' => $closerRedLine ?? 0,
                'redline_type' => 'Fixed',
                'redline_missing' => false,
            ];
        } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Location')) {
            $closerStateRedLine = null;
            $closerLocation = Locations::where('id', $closerOfficeId)->first();
            $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
            $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $locationRedLines = 0;
                if (strtolower(config('app.domain_name')) == 'firstcoast') {
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'ASC')->first();
                }
            }
            if ($saleStandardRedlineCheck && $closerRedLineCheck && $locationRedLines) {
                $closerStateRedLine = $locationRedLines->redline_standard;
                $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);

                return [
                    'redline' => $redLine,
                    'redline_type' => 'Shift Based on Location',
                    'redline_missing' => false,
                ];
            }
        } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product')) {
            $productRedLine = ProductMilestoneHistories::where('product_id', $productId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $productRedLine = 0;
                if (strtolower(config('app.domain_name')) == 'firstcoast') {
                    $productRedLine = ProductMilestoneHistories::where('product_id', $productId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'ASC')->first();
                }
            }
            if ($productRedLine && $closerRedLineCheck) {
                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                $redLine = $closerRedLine + $closerProductRedLine;

                return [
                    'redline' => $redLine,
                    'redline_type' => 'Shift Based on Product',
                    'redline_missing' => false,
                ];
            }
        } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
            $closerLocation = Locations::where('id', $closerOfficeId)->first();
            $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
            $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            $productRedLine = ProductMilestoneHistories::where('product_id', $productId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if ($companyProfile->company_type === CompanyProfile::MORTGAGE_COMPANY_TYPE) {
                $locationRedLines = 0;
                $productRedLine = 0;
                if (strtolower(config('app.domain_name')) == 'firstcoast') {
                    $locationRedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'ASC')->first();
                    $productRedLine = ProductMilestoneHistories::where('product_id', $productId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'ASC')->first();
                }
            }
            if ($closerRedLineCheck && $locationRedLines && $saleStandardRedlineCheck && $productRedLine) {
                $closerProductRedLine = $productRedLine->product_redline ?? 0;
                $closerStateRedLine = $locationRedLines->redline_standard ?? 0;
                $redLine = $closerRedLine - ($closerStateRedLine - $saleStandardRedline) + $closerProductRedLine;

                return [
                    'redline' => $redLine,
                    'redline_type' => 'Shift Based on Product & Location',
                    'redline_missing' => false,
                ];
            }
        }

        return [
            'redline' => 0,
            'redline_type' => null,
            'redline_missing' => true,
        ];
    }

    public function pestUserPerSale($userId, $approvedDate, $commissionHistory, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per sale') {
            if ($history->commission > $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        } elseif ($history->commission_type == 'percent') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
                'product_id' => $productId,
                'product_code' => $productCode,
            ];
        }

        return false;
    }

    public function pestUserPercentage($userId, $approvedDate, $closerCommission, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            if ($history->commission > $closerCommission->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function turfUserPerSale($userId, $saleState, $approvedDate, $commissionHistory, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per sale') {
            if ($history->commission > $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        } elseif ($history->commission_type == 'per kw') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
                'product_id' => $productId,
                'product_code' => $productCode,
            ];
        } elseif ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            if (! $userRedLine['redline_missing']) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function turfUserPerKw($userId, $saleState, $approvedDate, $commissionHistory, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per kw') {
            if ($history->commission > $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        } elseif ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            if (! $userRedLine['redline_missing']) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function turfUserPercentage($userId, $saleState, $approvedDate, $closerRedLine, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            // if ($history->commission > $closerCommission->commission) {
            if (! $userRedLine['redline_missing'] && $userRedLine['redline'] <= $closerRedLine) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function mortgageUserPerSale($userId, $approvedDate, $commissionHistory, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'per sale') {
            if ($history->commission > $commissionHistory->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        } elseif ($history->commission_type == 'per kw') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
                'product_id' => $productId,
                'product_code' => $productCode,
            ];
        } elseif ($history->commission_type == 'percent') {
            return [
                'user_id' => $userId,
                'value' => $history->commission,
                'value_type' => $history->commission_type,
                'type' => $history->commission_type,
                'product_id' => $productId,
                'product_code' => $productCode,
            ];
        }

        return false;
    }

    public function mortgageUserPercentage($userId, $saleState, $approvedDate, $closerRedLine, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            $userData = User::where(['id' => $userId])->first();
            $userRedLine = $this->userRedline($userData, $saleState, $approvedDate, $productId);
            if (! $userRedLine['redline_missing'] && $userRedLine['redline'] <= $closerRedLine) {
                return [
                    'user_id' => $userId,
                    'value' => $userRedLine['redline'],
                    'value_type' => $userRedLine['redline_type'],
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function mortgageUserPercentageOld($userId, $approvedDate, $closerCommission, $eligibleUser)
    {
        $productId = $eligibleUser['product_id'];
        $productCode = $eligibleUser['product_code'];
        $history = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => 2])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
        if (! $history) {
            return false;
        }

        if ($history->commission_type == 'percent') {
            if ($history->commission > $closerCommission->commission) {
                return [
                    'user_id' => $userId,
                    'value' => $history->commission,
                    'value_type' => $history->commission_type,
                    'type' => $history->commission_type,
                    'product_id' => $productId,
                    'product_code' => $productCode,
                ];
            }
        }

        return false;
    }

    public function saleProductMappingChanges($pid)
    {
        $userCommissions = UserCommission::where(['pid' => $pid, 'status' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        $userReconCommissions = UserCommission::where(['pid' => $pid, 'recon_status' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get();
        $userCommissions = $userCommissions->merge($userReconCommissions);
        foreach ($userCommissions as $userCommission) {
            $userCommission->delete();
        }
        $userOverrides = UserOverrides::where(['pid' => $pid, 'status' => '1', 'overrides_settlement_type' => 'during_m2', 'is_displayed' => '1'])->get();
        $userReconOverrides = UserOverrides::where(['pid' => $pid, 'recon_status' => '1', 'overrides_settlement_type' => 'reconciliation', 'is_displayed' => '1'])->get();
        $userOverrides = $userOverrides->merge($userReconOverrides);
        foreach ($userOverrides as $userOverride) {
            $userOverride->delete();
        }
    }

    public function lockMilestones($sale, $info, $schema, $forExternal = 0)
    {
        $userId = $info['id'];
        $type = $schema->type;
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = $sale->sales_master_process->closer1_id;
        $closer2Id = $sale->sales_master_process->closer2_id;
        $setterId = $sale->sales_master_process->setter1_id;
        $setter2Id = $sale->sales_master_process->setter2_id;
        $schemaId = $schema->milestone_schema_id;

        if (! SaleTiersDetail::where(['pid' => $sale->pid, 'user_id' => $userId, 'type' => 'Upfront', 'sub_type' => $type, 'is_locked' => 1])->first()) {
            $isSelfGen = false;
            if ($forExternal == 0) {
                if ($info['type'] == 'closer' && $setterId == $closerId) {
                    $isSelfGen = true;
                }
                if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                    $isSelfGen = true;
                }
            } else {
                if ($info['type'] == 'selfgen') {
                    $isSelfGen = true;
                }
            }

            $organization = UserOrganizationHistory::where('effective_date', '<=', $approvalDate)->where('user_id', $userId)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId, 'effective_date' => $organization?->effective_date])->first();
            if (! $userOrganizationHistory) {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $productId = $product->id;
                $milestones = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($milestones && isset($milestones->milestone->milestone_trigger)) {
                    $triggerIndex = (preg_replace('/\D/', '', $type) - 1);
                    $trigger = @$milestones->milestone->milestone_trigger[$triggerIndex];
                    $schemaId = @$trigger->id;
                    $userOrganizationHistory = UserOrganizationHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                }
            }

            $subPositionId = @$userOrganizationHistory->sub_position_id;
            $upfront = PositionCommissionUpfronts::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $upfront) {
                $upfront = PositionCommissionUpfronts::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }

            if ($upfront && $upfront->upfront_status == 1) {
                $tierParam = [
                    'info' => $info,
                    'sale' => $sale,
                    'user_id' => $userId,
                    'schema_id' => $schemaId,
                    'product_id' => $productId,
                    'is_self_gen' => $isSelfGen,
                    'user_organization_history' => $userOrganizationHistory,
                ];

                $upFrontData = upFrontCalculationValue($tierParam);
                SaleTiersDetail::updateOrCreate(['pid' => $sale->pid, 'user_id' => $userId, 'type' => 'Upfront', 'sub_type' => $type], [
                    'pid' => $sale->pid,
                    'product_id' => $productId,
                    'schema_id' => isset($upFrontData['schema']['id']) ? $upFrontData['schema']['id'] : null,
                    'user_id' => $userId,
                    'tier_level' => isset($upFrontData['level']['level']) ? $upFrontData['level']['level'] : null,
                    'is_tiered' => isset($upFrontData['is_tiered']) ? $upFrontData['is_tiered'] : 0,
                    'tiers_type' => isset($upFrontData['schema']['tier_type']) ? $upFrontData['schema']['tier_type'] : null,
                    'type' => 'Upfront',
                    'sub_type' => $type,
                    'is_locked' => $upFrontData['is_locked'],
                ]);
            }
        }
    }

    public function lockCommissions($sale, $info, $forExternal = 0)
    {
        $userId = $info['id'];
        $productId = $sale->product_id;
        $approvalDate = $sale->customer_signoff;
        $closerId = $sale->sales_master_process->closer1_id;
        $closer2Id = $sale->sales_master_process->closer2_id;
        $setterId = $sale->sales_master_process->setter1_id;
        $setter2Id = $sale->sales_master_process->setter2_id;

        if (! SaleTiersDetail::where(['pid' => $sale->pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission', 'is_locked' => 1])->first()) {
            $isSelfGen = false;

            if ($forExternal == 0) {
                if ($info['type'] == 'closer' && $setterId == $closerId) {
                    $isSelfGen = true;
                }
                if ($info['type'] == 'closer2' && $setter2Id == $closer2Id) {
                    $isSelfGen = true;
                }
            } else {
                // Added this condition to handle external sales worker
                if ($info['type'] == 'selfgen') {
                    $isSelfGen = true;
                }
            }

            $userOrganizationData = checkUsersProductForCalculations($userId, $approvalDate, $productId);
            $userOrganizationHistory = $userOrganizationData['organization'];
            $productId = $userOrganizationData['product']->id;
            $subPositionId = @$userOrganizationHistory['sub_position_id'];
            $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->where('effective_date', '<=', $approvalDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $commission) {
                $commission = PositionCommission::where(['position_id' => @$subPositionId, 'product_id' => $productId])->whereNull('effective_date')->first();
            }

            if ($commission && $commission->commission_status == 1) {
                $tierParam = [
                    'info' => $info,
                    'sale' => $sale,
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'is_self_gen' => $isSelfGen,
                    'user_organization_history' => $userOrganizationHistory,
                ];

                $commissionData = commissionCalculationValue($tierParam);
                SaleTiersDetail::updateOrCreate(['pid' => $sale->pid, 'user_id' => $userId, 'type' => 'Commission', 'sub_type' => 'Commission'], [
                    'pid' => $sale->pid,
                    'product_id' => $productId,
                    'schema_id' => isset($commissionData['schema']['id']) ? $commissionData['schema']['id'] : null,
                    'user_id' => $userId,
                    'tier_level' => isset($commissionData['level']['level']) ? $commissionData['level']['level'] : null,
                    'is_tiered' => isset($commissionData['is_tiered']) ? $commissionData['is_tiered'] : 0,
                    'tiers_type' => isset($commissionData['schema']['tier_type']) ? $commissionData['schema']['tier_type'] : null,
                    'type' => 'Commission',
                    'sub_type' => 'Commission',
                    'is_locked' => $commissionData['is_locked'],
                ]);
            }
        }
    }

    // $forExternal = 1 for external sales worker
    public function lockSaleOverrides($pid, $info, $forExternal = 0)
    {
        $saleUserId = $info['id'];
        $recruiterIdData = User::where(['id' => $saleUserId])->first();
        if (! $recruiterIdData) {
            return false;
        }
        $saleMaster = SalesMaster::where('pid', $pid)->first();
        $approvedDate = isset($saleMaster->customer_signoff) ? $saleMaster->customer_signoff : null;
        $productId = $saleMaster->product_id;

        // OFFICE OVERRIDES CODE
        $officeId = $recruiterIdData->office_id;
        $userTransferHistory = UserTransferHistory::where('user_id', $saleUserId)->where('transfer_effective_date', '<=', $approvedDate)->whereNotNull('office_id')->orderBy('transfer_effective_date', 'DESC')->first();
        if ($userTransferHistory) {
            $officeId = $userTransferHistory->office_id;
        }

        if ($officeId) {
            $subQuery = UserTransferHistory::select(
                'id',
                'user_id',
                'transfer_effective_date',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY transfer_effective_date DESC, id DESC) as rn')
            )->where('transfer_effective_date', '<=', $approvedDate);

            $results = DB::table(DB::raw("({$subQuery->toSql()}) as subQuery"))
                ->mergeBindings($subQuery->getQuery())
                ->select('id')
                ->where('rn', 1);

            $userIdArr = UserTransferHistory::whereIn('id', $results->pluck('id'))->whereNotNull('office_id')->where('office_id', $officeId)->pluck('user_id')->toArray();
            $userIdArr1 = User::select('id', 'stop_payroll', 'sub_position_id', 'dismiss', 'office_overrides_amount', 'office_overrides_type')->whereIn('id', $userIdArr)->get();
            foreach ($userIdArr1 as $userData) {
                $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
                $organizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $positionId = $userData->sub_position_id;
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'product_id' => $actualProductId, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $overrideStatus || $overrideStatus->status == 0) {
                        $overrideHistory = UserOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $actualProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($overrideHistory && $overrideHistory->office_tiers_id) {
                            $this->lockOverrides($saleMaster, $userOrganizationData, $overrideHistory->office_tiers_id, $userData->id, 'Office');
                        }
                    }
                }
            }

            $userIdArr2 = AdditionalLocations::with('user:id,stop_payroll,sub_position_id,dismiss,office_overrides_amount,office_overrides_type')->where(['office_id' => $officeId])->whereNotIn('user_id', [$saleUserId])->get();
            foreach ($userIdArr2 as $userData) {
                $userData = $userData->user;
                $userOrganizationData = checkUsersProductForCalculations($userData->id, $approvedDate, $productId);
                $organizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $positionId = $userData->sub_position_id;
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '3'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $userData->id, 'product_id' => $actualProductId, 'type' => 'Office'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $overrideStatus || $overrideStatus->status == 0) {
                        $overrideHistory = UserAdditionalOfficeOverrideHistory::where(['user_id' => $userData->id, 'product_id' => $actualProductId, 'office_id' => $officeId])->where('override_effective_date', '<=', $approvedDate)->orderBy('id', 'DESC')->first();
                        if ($overrideHistory && $overrideHistory->tiers_id) {
                            $this->lockOverrides($saleMaster, $userOrganizationData, $overrideHistory->tiers_id, $userData->id, 'Additional Office', $officeId);
                        }
                    }
                }
            }
        }

        // DIRECT & INDIRECT OVERRIDES CODE
        if ($recruiterIdData && $recruiterIdData->recruiter_id) {
            $recruiterIds = $recruiterIdData->recruiter_id;
            if (! empty($recruiterIdData->additional_recruiter_id1)) {
                $recruiterIds .= ','.$recruiterIdData->additional_recruiter_id1;
            }
            if (! empty($recruiterIdData->additional_recruiter_id2)) {
                $recruiterIds .= ','.$recruiterIdData->additional_recruiter_id2;
            }

            $idsArr = explode(',', $recruiterIds);
            $directs = User::whereIn('id', $idsArr)->get();
            foreach ($directs as $value) {
                $userOrganizationData = checkUsersProductForCalculations($value->id, $approvedDate, $productId);
                $organizationHistory = $userOrganizationData['organization'];
                $actualProductId = $userOrganizationData['product']->id;
                $positionId = $value->sub_position_id;
                if ($organizationHistory) {
                    $positionId = $organizationHistory->sub_position_id;
                }

                $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $positionOverride) {
                    $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '1'])->whereNull('effective_date')->first();
                }
                if ($positionOverride && $positionOverride->status == 1) {
                    $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $value->id, 'product_id' => $actualProductId, 'type' => 'Direct'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $overrideStatus || $overrideStatus->status == 0) {
                        $overrideHistory = UserOverrideHistory::where(['user_id' => $value->id, 'product_id' => $actualProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if ($overrideHistory && $overrideHistory->direct_tiers_id) {
                            $this->lockOverrides($saleMaster, $userOrganizationData, $overrideHistory->direct_tiers_id, $value->id, 'Direct');
                        }
                    }
                }

                // INDIRECT
                if ($value->recruiter_id) {
                    $recruiterIds = $value->recruiter_id;
                    if (! empty($value->additional_recruiter_id1)) {
                        $recruiterIds .= ','.$value->additional_recruiter_id1;
                    }
                    if (! empty($value->additional_recruiter_id2)) {
                        $recruiterIds .= ','.$value->additional_recruiter_id2;
                    }
                    $idsArr = explode(',', $recruiterIds);

                    $inDirects = User::whereIn('id', $idsArr)->get();
                    foreach ($inDirects as $val) {
                        $userOrganizationData = checkUsersProductForCalculations($val->id, $approvedDate, $productId);
                        $organizationHistory = $userOrganizationData['organization'];
                        $actualProductId = $userOrganizationData['product']->id;
                        $positionId = $val->sub_position_id;
                        if ($organizationHistory) {
                            $positionId = $organizationHistory->sub_position_id;
                        }

                        $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $positionOverride) {
                            $positionOverride = PositionOverride::where(['position_id' => $positionId, 'product_id' => $actualProductId, 'override_id' => '2'])->whereNull('effective_date')->first();
                        }
                        if ($positionOverride && $positionOverride->status == 1) {
                            $overrideStatus = OverrideStatus::where(['user_id' => $saleUserId, 'recruiter_id' => $val->id, 'product_id' => $actualProductId, 'type' => 'Indirect'])->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            if (! $overrideStatus || $overrideStatus->status == 0) {
                                $overrideHistory = UserOverrideHistory::where(['user_id' => $val->id, 'product_id' => $actualProductId])->where('override_effective_date', '<=', $approvedDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                if ($overrideHistory) {
                                    if ($overrideHistory->indirect_tiers_id) {
                                        $this->lockOverrides($saleMaster, $userOrganizationData, $overrideHistory->indirect_tiers_id, $val->id, 'InDirect');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // END DIRECT & INDIRECT OVERRIDES CODE
    }

    public function lockOverrides($sale, $userOrganizationData, $tierId, $userId, $type, $officeId = null)
    {
        $productId = $userOrganizationData['product']->id;
        $userOrganizationHistory = $userOrganizationData['organization'];
        if (! SaleTiersDetail::where(['pid' => $sale->pid, 'user_id' => $userId, 'type' => 'Override', 'sub_type' => $type, 'is_locked' => 1])->first()) {
            $tierParam = [
                'sale' => $sale,
                'user_id' => $userId,
                'product_id' => $productId,
                'tier_id' => $tierId,
                'user_organization_history' => $userOrganizationHistory,
            ];

            $overrideData = overrideCalculationValue($tierParam);
            SaleTiersDetail::updateOrCreate(['pid' => $sale->pid, 'user_id' => $userId, 'type' => 'Override', 'sub_type' => $type], [
                'pid' => $sale->pid,
                'schema_id' => $tierId,
                'product_id' => $productId,
                'user_id' => $userId,
                'office_id' => $officeId,
                'tier_level' => isset($overrideData['level']['level']) ? $overrideData['level']['level'] : null,
                'is_tiered' => isset($overrideData['is_tiered']) ? $overrideData['is_tiered'] : 0,
                'tiers_type' => isset($overrideData['schema']['tier_type']) ? $overrideData['schema']['tier_type'] : null,
                'type' => 'Override',
                'sub_type' => $type,
                'is_locked' => $overrideData['is_locked'],
            ]);
        }
    }

    public function createProductDataForExternalWorker($data)
    {
        $pid = $data['pid'];
        $productId = $data['product_id'];
        $effectiveDate = $data['effective_date'];

        $paid = false;
        if (UserCommission::where(['pid' => $pid, 'status' => '3', 'is_last' => '1', 'settlement_type' => 'during_m2', 'is_displayed' => '1', 'worker_type' => 'external'])->first()) {
            $paid = true;
        }
        if (UserCommission::where(['pid' => $pid, 'is_last' => '1', 'settlement_type' => 'reconciliation', 'is_displayed' => '1', 'worker_type' => 'external'])->whereIn('recon_status', ['2', '3'])->first()) {
            $paid = true;
        }

        if ($paid) {
            $saleProducts = ExternalSaleProductMaster::where('pid', $pid)->groupBy('type')->get();
            foreach ($saleProducts as $key => $saleProduct) {
                $date = @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null;
                ExternalSaleProductMaster::where(['pid' => $pid, 'type' => $saleProduct->type])->update(['milestone_date' => $date]);
            }
        } else {
            $milestoneTriggers = [];
            if ($productId) {
                $milestone = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $productId)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (isset($milestone->milestone->milestone_trigger)) {
                    // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                    $milestoneTriggers = $milestone->milestone->milestone_trigger->values();
                } else {
                    $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                    $productId = $product->id;
                    $milestone = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $product->id)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (isset($milestone->milestone->milestone_trigger)) {
                        // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                        $milestoneTriggers = $milestone->milestone->milestone_trigger->values();
                    }
                }
            } else {
                $product = Products::withTrashed()->where('product_id', config('global_vars.DEFAULT_PRODUCT_ID'))->first();
                $productId = $product->id;
                $milestone = ProductMilestoneHistories::with('milestone.milestone_trigger')->where('product_id', $product->id)->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (isset($milestone->milestone->milestone_trigger)) {
                    // Use values() to ensure sequential numeric keys (0, 1, 2...) instead of record IDs
                    $milestoneTriggers = $milestone->milestone->milestone_trigger->values();
                }
            }

            ExternalSaleProductMaster::where('pid', $pid)->delete();
            $count = count($milestoneTriggers);
            foreach ($milestoneTriggers as $key => $milestoneTrigger) {
                // Reset flags for each iteration
                $isLast = 0;
                $isExempted = 0;
                $isOverRide = 0;

                if (($key + 1) == $count) {
                    $isLast = 1;
                }

                if ($milestone->clawback_exempt_on_ms_trigger_id && $milestone->clawback_exempt_on_ms_trigger_id == $milestoneTrigger->id) {
                    $isExempted = 1;
                }

                if ($milestone->override_on_ms_trigger_id && $milestone->override_on_ms_trigger_id == $milestoneTrigger->id) {
                    $isOverRide = 1;
                }

                $externalWorkerList = ExternalSaleWorker::where('pid', $pid)->get();
                foreach ($externalWorkerList as $worker) {
                    ExternalSaleProductMaster::create([
                        'pid' => $pid,
                        'product_id' => $productId,
                        'milestone_id' => $milestone->id,
                        'milestone_schema_id' => $milestoneTrigger->id,
                        'milestone_date' => @$data['milestone_dates'][$key]['date'] ? $data['milestone_dates'][$key]['date'] : null,
                        'type' => 'm'.($key + 1),
                        'is_last_date' => $isLast,
                        'is_exempted' => $isExempted,
                        'is_override' => $isOverRide,
                        'is_projected' => @$data['milestone_dates'][$key]['date'] ? 0 : 1,
                        'worker_id' => $worker->user_id,
                        'worker_type' => $worker->type, // Worker type (1 = self gen, 2= Closer, 3= Setter)

                    ]);
                }
            }
        }

        if (CompanySetting::where(['type' => 'tier', 'status' => '1'])->first()) {
            $overrideLocked = [];
            $sale = SalesMaster::with(['salesMasterProcess', 'productInfo' => function ($q) {
                $q->withTrashed();
            }])->where(['pid' => $pid])->first();
            $schemas = ExternalSaleProductMaster::with('milestoneSchemaTrigger')->where(['pid' => $pid])->get();
            foreach ($schemas as $schema) {
                $info['id'] = $schema->worker_id;
                if ($schema->worker_type == 1) {
                    $info['type'] = 'selfgen';
                } elseif ($schema->worker_type == 2) {
                    $info['type'] = 'closer';
                } elseif ($schema->worker_type == 3) {
                    $info['type'] = 'setter';
                }

                if ($info) {
                    if ($schema->is_last_date == '1') {
                        $this->lockCommissions($sale, $info, $forExternal = 1);
                    } else {
                        $this->lockMilestones($sale, $info, $schema, $forExternal = 1);
                    }

                    if ($schema->is_override == 1 && ! isset($overrideLocked[$info['id']])) {
                        $overrideLocked[$info['id']] = 1;
                        $this->lockSaleOverrides($pid, $info, $forExternal = 1);
                    }
                }
            }
        }
    }

    public function getRedLineDataForExternalWorker($checked)
    {
        $productId = $checked->product_id;
        $approvedDate = $checked->customer_signoff;
        $data = [];

        if (config('app.domain_name') == 'flex') {
            $saleState = $checked->customer_state;
        } else {
            $saleState = $checked->location_code;
        }

        $saleStandardRedline = null;
        $generalCode = Locations::where('general_code', $saleState)->first();
        if ($generalCode) {
            $locationRedlines = LocationRedlineHistory::where('location_id', $generalCode->id)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        } else {
            $state = State::where('state_code', $saleState)->first();
            $saleStateId = isset($state->id) ? $state->id : 0;
            $location = Locations::where('state_id', $saleStateId)->first();
            $locationId = isset($location->id) ? $location->id : 0;
            $locationRedlines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
            if ($locationRedlines) {
                $saleStandardRedline = $locationRedlines->redline_standard;
            }
        }

        if ($approvedDate) {

            $companyProfile = CompanyProfile::first();
            // $data['closer1_redline'] = '0';
            // $data['closer1_redline_type'] = NULL;
            // $data['closer1_is_redline_missing'] = 0;
            // $data['closer1_commission_type'] = NULL;
            // $data['closer2_redline'] = '0';
            // $data['closer2_redline_type'] = NULL;
            // $data['closer2_is_redline_missing'] = 0;
            // $data['closer2_commission_type'] = NULL;
            // $data['setter1_redline'] = '0';
            // $data['setter1_redline_type'] = NULL;
            // $data['setter1_is_redline_missing'] = 0;
            // $data['setter1_commission_type'] = NULL;
            // $data['setter2_redline'] = '0';
            // $data['setter2_redline_type'] = NULL;
            // $data['setter2_is_redline_missing'] = 0;
            // $data['setter2_commission_type'] = NULL;

            // $extrenalSaleWorkers = ExternalSaleProductMaster::where('pid', $pid)->get();
            if ($checked->externalSaleWorker->count() > 0) {
                foreach ($checked->externalSaleWorker as $worker) {
                    if ($worker->user_id) {
                        $closer = User::where('id', $worker->user_id)->first();
                        $closerRedLine = 0;
                        $checkCloserRedLine = 0;
                        $closerRedLineAmountType = null;
                        $closerMissingRedLine = 0;
                        $userOrganizationData = checkUsersProductForCalculations($worker->user_id, $approvedDate, $productId);
                        $userOrganizationHistory = $userOrganizationData['organization'];
                        $actualProductId = $userOrganizationData['product']->id;
                        if ($worker->type == 2 || $worker->type == 1) {
                            $corePosition = 2;
                        } elseif ($worker->type == 3) {
                            $corePosition = 3;
                        }
                        if ($worker->type == 1 && @$userOrganizationHistory->self_gen_accounts == 1) {
                            $commissionHistory = UserCommissionHistory::where(['user_id' => $worker->user_id, 'product_id' => $actualProductId])->whereNull('core_position_id')->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            $commissionType = 'percent';
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                                if ($commissionHistory) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                }
                            } else {
                                if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                } else {
                                    $userRedLine = UserRedlines::where(['user_id' => $worker->user_id, 'self_gen_user' => '1'])->where('start_date', '<=', $approvedDate)->whereNull('core_position_id')->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($userRedLine) {
                                        $checkCloserRedLine = 1;
                                        $closerRedLine = $userRedLine->redline;
                                        $closerRedLineAmountType = $userRedLine->redline_amount_type;
                                    }
                                }
                            }
                        } else {
                            $commissionHistory = UserCommissionHistory::where(['user_id' => $worker->user_id, 'product_id' => $actualProductId, 'core_position_id' => $corePosition])->where('commission_effective_date', '<=', $approvedDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                            $commissionType = 'percent';
                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                                if ($commissionHistory) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                }
                            } else {
                                if ($commissionHistory && ($commissionHistory->commission_type == 'per kw' || $commissionHistory->commission_type == 'per sale')) {
                                    $closerRedLine = $commissionHistory->commission;
                                    $commissionType = $commissionHistory->commission_type;
                                    $closerRedLineAmountType = $commissionHistory->commission_type;
                                } else {
                                    $userRedLine = UserRedlines::where(['user_id' => $worker->user_id, 'core_position_id' => $corePosition, 'self_gen_user' => '0'])->where('start_date', '<=', $approvedDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($userRedLine) {
                                        $checkCloserRedLine = 1;
                                        $closerRedLine = $userRedLine->redline;
                                        $closerRedLineAmountType = $userRedLine->redline_amount_type;
                                    }
                                }
                            }
                        }

                        $closerOfficeId = $closer->office_id;
                        $userTransferHistory = UserTransferHistory::where('user_id', $worker->user_id)->where('transfer_effective_date', '<=', $approvedDate)->orderBy('transfer_effective_date', 'DESC')->first();
                        if ($userTransferHistory) {
                            $closerOfficeId = $userTransferHistory->office_id;
                        }
                        $closerLocation = Locations::with('state')->where('id', $closerOfficeId)->first();
                        $locationId = isset($closerLocation->id) ? $closerLocation->id : 0;
                        $location3RedLines = LocationRedlineHistory::where('location_id', $locationId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->first();
                        $closerStateRedLine = null;
                        if ($location3RedLines) {
                            $closerStateRedLine = $location3RedLines->redline_standard;
                        }

                        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE) || ($companyProfile->company_type == CompanyProfile::TURF_COMPANY_TYPE && config('app.domain_name') == 'frdmturf')) {
                            $data[] = ['worker_id' => $worker->user_id, 'redline' => $closerRedLine, 'redline_type' => $closerRedLineAmountType];
                        } else {
                            if ($closerRedLineAmountType != 'per kw' && $closerRedLineAmountType != 'per sale') {
                                if (strtolower($closerRedLineAmountType) == strtolower('Fixed')) {
                                    $data[] = ['worker_id' => $worker->user_id, 'redline' => $closerRedLine, 'redline_type' => 'Fixed'];
                                } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Location')) {
                                    $redLine = 0;
                                    if ($locationRedlines && $checkCloserRedLine && $location3RedLines) {
                                        $redLine = $saleStandardRedline + ($closerRedLine - $closerStateRedLine);
                                    } else {
                                        $closerMissingRedLine = 1;
                                    }
                                    $data[] = ['worker_id' => $worker->user_id, 'redline' => $redLine, 'redline_type' => 'Shift Based on Location'];
                                } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product')) {
                                    $redLine = 0;
                                    $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($productRedLine && $checkCloserRedLine) {
                                        $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                        $redLine = $closerRedLine + $closerProductRedLine;
                                    } else {
                                        $closerMissingRedLine = 1;
                                    }
                                    $data[] = ['worker_id' => $worker->user_id, 'redline' => $redLine, 'redline_type' => 'Shift Based on Product'];
                                } elseif (strtolower($closerRedLineAmountType) == strtolower('Shift Based on Product & Location')) {
                                    $redLine = 0;
                                    $productRedLine = ProductMilestoneHistories::where('product_id', $actualProductId)->where('effective_date', '<=', $approvedDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                                    if ($locationRedlines && $checkCloserRedLine && $location3RedLines && $productRedLine) {
                                        $closerProductRedLine = $productRedLine->product_redline ?? 0;
                                        $redLine = $closerRedLine - ($closerStateRedLine - $saleStandardRedline) + $closerProductRedLine;
                                    } else {
                                        $closerMissingRedLine = 1;
                                    }
                                    $data[] = ['worker_id' => $worker->user_id, 'redline' => $redLine, 'redline_type' => 'Shift Based on Product & Location'];
                                } else {
                                    $closerMissingRedLine = 1;
                                }
                            } else {
                                $data[] = ['worker_id' => $worker->user_id, 'redline' => $closerRedLine, 'redline_type' => $closerRedLineAmountType];
                            }
                        }

                        $data[] = ['worker_id' => $worker->user_id, 'commission_type' => $commissionType, 'is_redline_missing' => $closerMissingRedLine];
                    }
                }

            }

            // Added to make redline 0 for MORTGAGE company type if domain is not firstcoast
            if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && strtolower(config('app.domain_name')) != 'firstcoast') {
                $data['redline'] = 0;
            }

            return $data;
        }
    }

    public function externalSalesRepData($schema)
    {
        if (! $schema) {
            return [];
        }

        if ($schema['worker_type'] == 2) {
            return [
                'type' => 'closer',
                'id' => $schema['worker_id'],
            ];
        } elseif ($schema['worker_type'] == 3) {
            return [
                'type' => 'setter',
                'id' => $schema['worker_id'],
            ];
        } elseif ($schema['worker_type'] == 1) {
            return [
                'type' => 'selfgen',
                'id' => $schema['worker_id'],
            ];
        }
    }
}
