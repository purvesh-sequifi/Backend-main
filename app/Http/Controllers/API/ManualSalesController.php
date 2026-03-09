<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiMissingDataValidatedRequest;
use App\Models\CompanyProfile;
use App\Models\ImportExpord;
use App\Models\LegacyApiRowData;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserReconciliationWithholding;
use App\Services\SalesCalculationContext;
use Illuminate\Http\Request;

class ManualSalesController extends Controller
{
    use EditSaleTrait, SetterSubroutineListTrait {
        EditSaleTrait::updateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m1dateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m1datePayrollData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m2dateSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::m2datePayrollData insteadof SetterSubroutineListTrait;
        EditSaleTrait::executedSalesData insteadof SetterSubroutineListTrait;
        EditSaleTrait::salesDataHistory insteadof SetterSubroutineListTrait;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // return $request;
    }

    public function addManualSaleData_old(ApiMissingDataValidatedRequest $request)
    {
        // return $request;
        $pid = $request->pid;

        $closer = User::whereIn('id', $request->rep_id)->get();
        $setter = User::whereIn('id', $request->setter_id)->get();
        // return $setter[0]->id;
        $excelData = [
            'ct' => '',
            'weekly_sheet_id' => null,
            'affiliate' => $request->affiliate,
            'pid' => $request->pid,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
            'kw' => isset($request->kw) ? $request->kw : null,
            'cancel_date' => isset($request->cancel_date) ? $request->cancel_date : null,
            'approved_date' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'state' => isset($request->customer_state) ? $request->customer_state : null,
            'product' => isset($request->product) ? $request->product : null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'epc' => isset($request->epc) ? $request->epc : null,
            'net_epc' => isset($request->net_epc) ? $request->net_epc : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'dealer_fee_dollar' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
            'show' => isset($request->show) ? $request->show : null,
            'redline' => isset($request->redline) ? $request->redline : null,
            'total_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
            'prev_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
            'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
            'm1_this_week' => isset($request->m1_amount) ? $request->m1_amount : null,
            'install_m2_this_week' => isset($request->m2_amount) ? $request->m2_amount : null,
            'prev_deducted' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
            'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
            'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
            'lead_cost' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
            'total_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
            'inactive_date' => null,
        ];
        $insertExcelData = ImportExpord::create($excelData);

        $apiData = [
            'legacy_data_id' => null,
            'pid' => $request->pid,
            'weekly_sheet_id' => null,
            'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
            'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
            'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
            'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
            'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
            'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
            'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
            'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
            'setter_id' => isset($setter[0]->id) ? $setter[0]->id : null,
            'employee_id' => null,
            'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
            'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'install_partner_id' => null,
            'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'scheduled_install' => null,
            'install_complete_date' => null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'date_cancelled' => isset($request->cancel_date) ? $request->cancel_date : null,
            'return_sales_date' => null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'cash_amount' => null,
            'loan_amount' => null,
            'kw' => isset($request->kw) ? $request->kw : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'adders' => isset($request->show) ? $request->show : null,
            'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
            'funding_source' => null,
            'financing_rate' => null,
            'financing_term' => null,
            'product' => isset($request->product) ? $request->product : null,
        ];

        $legacyApiRowData = LegacyApiRowData::where('pid', $pid)->first();
        if ($legacyApiRowData) {
            $updateApiData = LegacyApiRowData::where('pid', $pid)->update($apiData);
        } else {
            $insertApiData = LegacyApiRowData::create($apiData);
        }

        // Update data by previous comparison in Sales_Master

        $val = [
            'pid' => $pid,
            'kw' => isset($request->kw) ? $request->kw : null,
            'weekly_sheet_id' => null,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'install_partner_id' => null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
            'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
            'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
            'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
            'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
            'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
            'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
            'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
            'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
            'sales_rep_name' => isset($closer->first_name) ? $closer->first_name : null,
            'employee_id' => null,
            'date_cancelled' => null,
            'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
            'date_cancelled' => isset($request->cancel_date) ? $request->cancel_date : null,
            'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'product' => isset($request->product) ? $request->product : null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'epc' => isset($request->epc) ? $request->epc : null,
            'net_epc' => isset($request->net_epc) ? $request->net_epc : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
            'adders' => isset($request->show) ? $request->show : null,
            'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
            'redline' => isset($request->redline) ? $request->redline : null,
            'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
            'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
            'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
            'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : null,
            'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : null,
            'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
            'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
            'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
            'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
            'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
            // 'funding_source' => isset($request->funding_source)?$request->funding_source:null,
            // 'financing_rate' => isset($request->financing_rate)?$request->financing_rate:null,
            // 'financing_term' => isset($request->financing_term)?$request->financing_term:null,
            // 'scheduled_install' => isset($request->scheduled_install)?$request->scheduled_install:null,
            // 'install_complete_date' => isset($request->install_complete_date)?$request->install_complete_date:null,
            // 'return_sales_date' => isset($request->return_sales_date)?$request->return_sales_date:null,
            // 'cash_amount' => isset($request->cash_amount)?$request->cash_amount:null,
            // 'loan_amount' => isset($request->loan_amount)?$request->loan_amount:null,
        ];

        $check_SalesMaster = SalesMaster::where('pid', $pid)->first();
        // dd($calculate);
        if ($check_SalesMaster) {

            $updateData = SalesMaster::where('pid', $pid)->update($val);
            $closer = $request->rep_id;
            $setter = $request->setter_id;
            $data = [
                'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                'setter2_id' => isset($setter[1]) ? $setter[1] : null,
            ];
            SaleMasterProcess::where('pid', $pid)->update($data);

            if ($setter) {
                $subroutineProcess = $this->subroutine_process($pid);
            }

        } else {

            $insertData = SalesMaster::create($val);
            $closer = $request->rep_id;
            $setter = $request->setter_id;
            $data = [
                'sale_master_id' => $insertData->id,
                'weekly_sheet_id' => $insertData->weekly_sheet_id,
                'pid' => $pid,
                'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                'setter2_id' => isset($setter[1]) ? $setter[1] : null,
            ];
            SaleMasterProcess::create($data);

            if ($setter) {
                $subroutineProcess = $this->subroutine_process($pid);
            }

        }

        return response()->json(['status' => true, 'Message' => 'Add Data successfully'], 200);
    }

    public function addManualSaleData(ApiMissingDataValidatedRequest $request)
    {
        // return $request;
        $pid = $request->pid;
        $closers = $request->rep_id;
        $setters = $request->setter_id;
        $saleMasters = SaleMasterProcess::where('pid', $pid)->first();
        if ($saleMasters) {

            $executedSale = $this->executedSalesData($request);
            if ($executedSale) {
                // return response()->json(['status' => false, 'Message' => 'This pay period is closed'], 400);
            }

            // update sale with m1-m2 date
            $saleMasterData = SalesMaster::where('pid', $pid)->first();

            $m1comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->first();
            if ($request->date_cancelled == null && $m1comm) {
                return response()->json(['status' => false, 'Message' => 'This sale payroll is executed'], 400);
            }

            if (! empty($saleMasterData->m1_date) && empty($request->m1_date)) {
                $m1comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3])->first();
                if ($m1comm) {
                    return response()->json(['status' => false, 'Message' => 'This sale payroll is executed'], 200);
                }
                $this->m1dateSalesData($pid);
            }

            if (! empty($saleMasterData->m2_date) && empty($request->m2_date)) {
                $m2comm = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->first();
                if ($m2comm) {
                    return response()->json(['status' => false, 'Message' => 'This sale payroll is executed'], 200);
                }
                $this->m2dateSalesData($pid, $saleMasterData->m2_date);
            }
            // end update sale with m1-m2 date

            if ($closers[0] != $saleMasters->closer1_id) {
                $saleMasters->closer1_m1_paid_status = null;
                $saleMasters->save();
                $this->updateSalesData($saleMasters->closer1_id, 2, $pid);
            }

            if ($setters[0] != $saleMasters->setter1_id) {
                $saleMasters->setter1_m1_paid_status = null;
                $saleMasters->save();
                $this->updateSalesData($saleMasters->setter1_id, 3, $pid);
            }
        } else {
            $missingSale = $this->missingSalesData($request);
            if ($missingSale) {
                // return response()->json(['status' => false, 'Message' => 'This pay period is already closed, sale is send to alert center'], 400);
            }
        }

        $closer = User::whereIn('id', $request->rep_id)->get();
        $setter = User::whereIn('id', $request->setter_id)->get();
        // return $setter[0]->id;
        $excelData = [
            'ct' => '',
            'weekly_sheet_id' => null,
            'affiliate' => $request->affiliate,
            'pid' => $request->pid,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
            'kw' => isset($request->kw) ? $request->kw : null,
            'cancel_date' => isset($request->date_cancelled) ? $request->date_cancelled : null,
            'approved_date' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'state' => isset($request->customer_state) ? $request->customer_state : null,
            'product' => isset($request->product) ? $request->product : null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'epc' => isset($request->epc) ? $request->epc : null,
            'net_epc' => isset($request->net_epc) ? $request->net_epc : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'dealer_fee_dollar' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
            'show' => isset($request->show) ? $request->show : null,
            'redline' => isset($request->redline) ? $request->redline : null,
            'total_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
            'prev_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
            'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
            'm1_this_week' => isset($request->m1_amount) ? $request->m1_amount : null,
            'install_m2_this_week' => isset($request->m2_amount) ? $request->m2_amount : null,
            'prev_deducted' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
            'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
            'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
            'lead_cost' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
            'total_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
            'inactive_date' => null,
            'data_source_type' => 'manual',
        ];
        $insertExcelData = ImportExpord::create($excelData);

        $apiData = [
            'legacy_data_id' => null,
            'pid' => $request->pid,
            'weekly_sheet_id' => null,
            'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
            'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
            'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
            'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
            'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
            'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
            'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
            'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
            'setter_id' => isset($setter[0]->id) ? $setter[0]->id : null,
            'employee_id' => null,
            'sales_rep_name' => isset($closer[0]->first_name) ? $closer[0]->first_name : null,
            'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'install_partner_id' => null,
            'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : null,
            'scheduled_install' => null,
            'install_complete_date' => null,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : null,
            'return_sales_date' => null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'cash_amount' => null,
            'loan_amount' => null,
            'kw' => isset($request->kw) ? $request->kw : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'adders' => isset($request->show) ? $request->show : null,
            'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
            'funding_source' => null,
            'financing_rate' => null,
            'financing_term' => null,
            'product' => isset($request->product) ? $request->product : null,
            // 'data_source_type' => 'manual'
        ];

        $legacyApiRowData = LegacyApiRowData::where('pid', $pid)->first();
        if ($legacyApiRowData) {
            $updateApiData = LegacyApiRowData::where('pid', $pid)->update($apiData);
        } else {
            $apiData['data_source_type'] = 'manual';
            $insertApiData = LegacyApiRowData::create($apiData);
        }

        // Update data by previous comparison in Sales_Master

        $val = [
            'pid' => $pid,
            'kw' => isset($request->kw) ? $request->kw : null,
            'weekly_sheet_id' => null,
            'install_partner' => isset($request->installer) ? $request->installer : null,
            'install_partner_id' => null,
            'customer_name' => isset($request->customer_name) ? $request->customer_name : null,
            'customer_address' => isset($request->customer_address) ? $request->customer_address : null,
            'customer_address_2' => isset($request->customer_address2) ? $request->customer_address2 : null,
            'customer_city' => isset($request->customer_city) ? $request->customer_city : null,
            'customer_state' => isset($request->customer_state) ? $request->customer_state : null,
            'customer_zip' => isset($request->customer_zip) ? $request->customer_zip : null,
            'customer_email' => isset($request->customer_email) ? $request->customer_email : null,
            'customer_phone' => isset($request->customer_phone) ? $request->customer_phone : null,
            'homeowner_id' => isset($request->homeowner_id) ? $request->homeowner_id : null,
            'proposal_id' => isset($request->proposal_id) ? $request->proposal_id : null,
            'sales_rep_name' => isset($closer->first_name) ? $closer->first_name : null,
            'employee_id' => null,
            'sales_rep_email' => isset($closer[0]->email) ? $closer[0]->email : null,
            'date_cancelled' => isset($request->date_cancelled) ? $request->date_cancelled : null,
            'customer_signoff' => isset($request->approved_date) ? $request->approved_date : null,
            'm1_date' => isset($request->m1_date) ? $request->m1_date : $request->m2_date,
            'm2_date' => isset($request->m2_date) ? $request->m2_date : null,
            'product' => isset($request->product) ? $request->product : null,
            'gross_account_value' => isset($request->gross_account_value) ? $request->gross_account_value : null,
            'epc' => isset($request->epc) ? $request->epc : null,
            'net_epc' => isset($request->net_epc) ? $request->net_epc : null,
            'dealer_fee_percentage' => isset($request->dealer_fee_percentage) ? $request->dealer_fee_percentage : null,
            'dealer_fee_amount' => isset($request->dealer_fee_amount) ? $request->dealer_fee_amount : null,
            'adders' => isset($request->show) ? $request->show : null,
            'adders_description' => isset($request->adders_description) ? $request->adders_description : null,
            'redline' => isset($request->redline) ? $request->redline : null,
            'total_amount_for_acct' => isset($request->total_for_acct) ? $request->total_for_acct : null,
            'prev_amount_paid' => isset($request->prev_paid) ? $request->prev_paid : null,
            'last_date_pd' => isset($request->last_date_pd) ? $request->last_date_pd : null,
            'm1_amount' => isset($request->m1_amount) ? $request->m1_amount : null,
            'm2_amount' => isset($request->m2_amount) ? $request->m2_amount : null,
            'prev_deducted_amount' => isset($request->prev_deducted_amount) ? $request->prev_deducted_amount : null,
            'cancel_fee' => isset($request->cancel_fee) ? $request->cancel_fee : null,
            'cancel_deduction' => isset($request->cancel_deduction) ? $request->cancel_deduction : null,
            'lead_cost_amount' => isset($request->lead_cost_amount) ? $request->lead_cost_amount : null,
            'adv_pay_back_amount' => isset($request->adv_pay_back_amount) ? $request->adv_pay_back_amount : null,
            'total_amount_in_period' => isset($request->total_amount_in_period) ? $request->total_amount_in_period : null,
            // 'data_source_type' => 'manual'
            // 'funding_source' => isset($request->funding_source)?$request->funding_source:null,
            // 'financing_rate' => isset($request->financing_rate)?$request->financing_rate:null,
            // 'financing_term' => isset($request->financing_term)?$request->financing_term:null,
            // 'scheduled_install' => isset($request->scheduled_install)?$request->scheduled_install:null,
            // 'install_complete_date' => isset($request->install_complete_date)?$request->install_complete_date:null,
            // 'return_sales_date' => isset($request->return_sales_date)?$request->return_sales_date:null,
            // 'cash_amount' => isset($request->cash_amount)?$request->cash_amount:null,
            // 'loan_amount' => isset($request->loan_amount)?$request->loan_amount:null,
        ];

        $check_SalesMaster = SalesMaster::where('pid', $pid)->first();
        // dd($calculate);
        if ($check_SalesMaster) {

            $updateData = SalesMaster::where('pid', $pid)->update($val);
            $closer = $request->rep_id;
            $setter = $request->setter_id;
            $data = [
                'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                'setter2_id' => isset($setter[1]) ? $setter[1] : null,
            ];
            SaleMasterProcess::where('pid', $pid)->update($data);

            if ($setter) {
                $subroutineProcess = $this->subroutine_process($pid);
                $alertCenter = $this->closedPayrollData($pid);
            }

        } else {
            $val['data_source_type'] = 'manual';
            $insertData = SalesMaster::create($val);
            $closer = $request->rep_id;
            $setter = $request->setter_id;
            $data = [
                'sale_master_id' => $insertData->id,
                'weekly_sheet_id' => $insertData->weekly_sheet_id,
                'pid' => $pid,
                'closer1_id' => isset($closer[0]) ? $closer[0] : null,
                'closer2_id' => isset($closer[1]) ? $closer[1] : null,
                'setter1_id' => isset($setter[0]) ? $setter[0] : null,
                'setter2_id' => isset($setter[1]) ? $setter[1] : null,
            ];
            SaleMasterProcess::create($data);

            if ($setter) {
                $subroutineProcess = $this->subroutine_process($pid);
                $alertCenter = $this->closedPayrollData($pid);
            }

        }

        return response()->json(['status' => true, 'Message' => 'Add Data successfully'], 200);
    }

    public function subroutine_process($pid)
    {
        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

        if (!$checked) {
            return;
        }

        // Set context for custom field conversion (Trick Subroutine approach)
        // This enables auto-conversion of 'custom field' to 'per sale' in model events
        $companyProfile = SalesCalculationContext::getCachedCompanyProfile() ?? CompanyProfile::first();

        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        try {
            // Only set context when Custom Sales Fields feature is enabled
            // This ensures zero impact on companies without the feature
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::set($checked, $companyProfile);
            }

            $dateCancelled = $checked->date_cancelled;
            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            $epc = $checked->epc;
            $netEpc = $checked->net_epc;
            $customerState = $checked->customer_state;
            $kw = $checked->kw;

            $m1_paid_status = $checked->salesMasterProcess->setter1_m1_paid_status;
            $m2_paid_status = $checked->salesMasterProcess->setter1_m2_paid_status;
            $approvedDate = $checked->customer_signoff;

            $closer1_id = $checked->salesMasterProcess->closer1_id;
            $closer2_id = $checked->salesMasterProcess->closer2_id;
            $setter1_id = $checked->salesMasterProcess->setter1_id;
            $setter2_id = $checked->salesMasterProcess->setter2_id;

            // Is there a clawback date = dateCancelled ?
            if ($dateCancelled) {
            if ($checked->salesMasterProcess->mark_account_status_id == 1 || $checked->salesMasterProcess->mark_account_status_id == 6) {
                // 'No clawback calculations required ';
            } elseif (empty($m1_date) && empty($m2_date)) {
                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                if ($saleMasterProcess) {
                    $saleMasterProcess->mark_account_status_id = 6;
                    $saleMasterProcess->save();
                }
            } else {
                $subroutineFive = $this->subroutineFive($checked);
                // 'Have any payments already been issued? ';
                if ($m1_paid_status == 4 || $m2_paid_status == 8) {
                    // run subroutine 5
                    // $subroutineFive = $this->subroutineFive($checked);
                } else {
                    // All pending payments or due payments are set to zero.

                    // $reconciliationWithholding = UserReconciliationWithholding::where(['pid' => $checked->pid])
                    // ->update(['withhold_amount'=>'0','status'=>'canceled']);

                    // $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                    // $saleMasterProcess->closer1_m1 = 0;
                    // $saleMasterProcess->closer2_m1 = 0;
                    // $saleMasterProcess->setter1_m1 = 0;
                    // $saleMasterProcess->setter2_m1 = 0;
                    // $saleMasterProcess->closer1_m2 = 0;
                    // $saleMasterProcess->closer2_m2 = 0;
                    // $saleMasterProcess->setter1_m2 = 0;
                    // $saleMasterProcess->setter2_m2 = 0;
                    // $saleMasterProcess->mark_account_status_id = 6;
                    // $saleMasterProcess->save();

                }
            }

        } else {
            // check Is there an M1 Date?
            if ($m1_date) {
                // check  Has M1 already been paid?

                if ($m1_paid_status == 4) {
                    // changes 8-08-2023
                    // $subroutineThree = $this->SubroutineThree($checked);
                    // end changes 8-08-2023

                    // check  Is there an M2 Date?
                    if ($m2_date != null) {

                        // Run Subroutine 6
                        $subroutineSix = $this->SubroutineSix($checked);

                        $subroutineEight = $this->SubroutineEight($checked);

                        if ($m2_paid_status == 8) {
                            // echo $subroutineEight['setter_commission'];die;
                            // Does total paid match total from Subroutine #8?
                            $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            // dd($pullTotalCommission);
                            $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                            // dd($totalPaid);
                            if (round($totalPaid) !== round($pullTotalCommission)) {
                                // echo"yesy";die;
                                // Run Subroutine #12 (Sale Adjustments)
                                $subroutineTwelve = $this->SubroutineTwelve($checked);
                            }
                        } else {
                            // echo 'check';die;
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
                        // echo"asda";die;
                        // No Further Action Required
                    }
                } else {

                    if ($m2_date != null) {
                        // Run Subroutine 6
                        $subroutineSix = $this->SubroutineSix($checked);

                        // Redline Value
                        // Run Subroutine #8 (Total Commission)
                        $subroutineEight = $this->SubroutineEight($checked);

                        // Has M2 already been paid?
                        if ($m2_paid_status == 8) {

                            // Does total paid match total from Subroutine #8?
                            $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                            if (round($totalPaid) != round($pullTotalCommission)) {
                                // Run Subroutine #12 (Sale Adjustments)
                                $subroutineTwelve = $this->SubroutineTwelve($checked);
                            }
                        } else {

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

                        $subroutineThree = $this->SubroutineThree($checked);

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
        } finally {
            // Only clear the context if it was set (feature is enabled)
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
    }
}
