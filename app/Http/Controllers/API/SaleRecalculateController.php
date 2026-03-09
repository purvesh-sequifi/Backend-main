<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\EditSaleTrait;
use App\Core\Traits\PayFrequencyTrait;
use App\Core\Traits\SetterSubroutineListTrait;
use App\Http\Controllers\Controller;
use App\Models\ClawbackSettlement;
use App\Models\CompanyProfile;
use App\Models\DeductionAlert;
use App\Models\LegacyApiRawDataHistory;
use App\Models\Payroll;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserCommission;
use App\Models\UserOverrides;
use App\Models\UserReconciliationWithholding;
use App\Services\SalesCalculationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleRecalculateController extends Controller
{
    use EditSaleTrait, PayFrequencyTrait, SetterSubroutineListTrait {
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
    public function index(Request $request) {}

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
            $returnSalesDate = $checked->return_sales_date;
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
            if ($dateCancelled || $returnSalesDate) {
            $dateCancelled = isset($dateCancelled) ? $dateCancelled : $returnSalesDate;
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
                $userCommissionM1Paid = UserCommission::where(['pid' => $pid, 'amount_type' => 'm1', 'status' => 3])->first();
                if ($userCommissionM1Paid) {
                    // changes 8-08-2023
                    // $subroutineThree = $this->SubroutineThree($checked);
                    // end changes 8-08-2023

                    // check  Is there an M2 Date?
                    if ($m2_date != null) {
                        $userCommissionM2Paid = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->first();
                        if ($userCommissionM2Paid) {
                            if (isset($userCommissionM2Paid->net_epc) && $userCommissionM2Paid->net_epc != $checked->net_epc) {
                                $subroutineNineNew = $this->subroutineEleven($checked);
                            }
                            // No action perform;

                        } else {
                            $this->callReconRoutine($checked);
                            // Run Subroutine 6
                            // $subroutineSix = $this->SubroutineSix($checked);
                            // Run Subroutine 8
                            $subroutineEight = $this->SubroutineEight($checked);

                            if (isset($setter1_id) && $setter1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps
                                    // $DeductionAlert = DeductionAlert::get();
                                }
                            }

                            if (isset($setter2_id) && $setter2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps
                                    // $DeductionAlert = DeductionAlert::get();
                                }
                            }

                            if (isset($closer1_id) && $closer1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps
                                    // $DeductionAlert = DeductionAlert::get();
                                }
                            }

                            if (isset($closer2_id) && $closer2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps
                                    // $DeductionAlert = DeductionAlert::get();

                                }
                            }

                        }

                    } else {

                        // No Further Action Required
                    }
                } else {

                    if ($m2_date != null) {

                        // Has M2 already been paid?
                        $userCommissionM2Paids = UserCommission::where(['pid' => $pid, 'amount_type' => 'm2', 'status' => 3])->first();
                        if ($userCommissionM2Paids) {
                            // No Further Action Required

                        } else {
                            $this->callReconRoutine($checked);
                            // Run Subroutine 6
                            // $subroutineSix = $this->SubroutineSix($checked);

                            // Run Subroutine #8 (Total Commission)
                            $subroutineEight = $this->SubroutineEight($checked);

                            if (isset($setter1_id) && $setter1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($setter2_id) && $setter2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {
                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($closer1_id) && $closer1_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {

                                    // $subroutineTen = $this->subroutineTen($checked);
                                    $subroutineNine = $this->subroutineNine($checked);
                                } else {

                                    $subroutineNine = $this->subroutineNine($checked);
                                    // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                }
                            }

                            if (isset($closer2_id) && $closer2_id != null) {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                if ($closerReconciliationWithholding > 0) {

                                    // $subroutineTen = $this->subroutineTen($checked);
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

                $dateCancelled = isset($dateCancelled) ? $dateCancelled : $returnSalesDate;
                $legacyApiRawDataHistory = LegacyApiRawDataHistory::where('pid', $checked->pid)->where('date_cancelled', '!=', null)->orderBy('id', 'desc')->first();
                $legacyApiRawDataHistory1 = LegacyApiRawDataHistory::where('pid', $checked->pid)->where('return_sales_date', '!=', null)->orderBy('id', 'desc')->first();
                if ($legacyApiRawDataHistory && empty($dateCancelled)) {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->update(['mark_account_status_id' => 3]);
                    $clawbackSettlement = ClawbackSettlement::where(['pid' => $checked->pid, 'status' => 1, 'clawback_status' => 0])->delete();
                }
                if ($legacyApiRawDataHistory1 && empty($dateCancelled)) {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->update(['mark_account_status_id' => 3]);
                    $clawbackSettlement = ClawbackSettlement::where(['pid' => $checked->pid, 'status' => 1])->delete();
                }

            }
        } finally {
            // Only clear the context if it was set (feature is enabled)
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
    }

    public function recalculateSaleData(Request $request): JsonResponse
    {
        $payroll = Payroll::whereIn('finalize_status', ['1', '2'])->first();
        if ($payroll) {
            return response()->json(['status' => false, 'Message' => 'At this time, we are unable to process your request to update sales information. Our system is currently finalizing and executing the payroll. Please try again later. Thank you for your patience.'], 400);
        }

        $companyProfile = CompanyProfile::first();
        if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
            $pid = $request->pid;
            $saleMasters = SalesMaster::whereHas('salesMasterProcess')->with('salesMasterProcess')->where('pid', $pid)->first();
            if (! $saleMasters) {
                return response()->json(['status' => false, 'Message' => 'Sale Not Found!'], 400);
            }

            $closer = $saleMasters->salesMasterProcess->closer1_id;
            if (! $closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The closer is missing. Kindly ensure that the closer is assigned to this sale.'], 400);
            }

            $closer = $saleMasters->gross_account_value;
            if (! $closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The gross account value is missing. Kindly ensure that the gross account value is present to this sale.'], 400);
            }

            (new ApiMissingDataController)->subroutine_process($pid);
            // $this->subroutine_process($pid);
        } else {
            $pid = $request->pid;
            $saleMasters = SalesMaster::whereHas('salesMasterProcess')->with('salesMasterProcess')->where('pid', $pid)->first();
            if (! $saleMasters) {
                return response()->json(['status' => false, 'Message' => 'Sale Not Found!'], 400);
            }

            $closer = $saleMasters->salesMasterProcess->closer1_id;
            if (! $closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The closer is missing. Kindly ensure that the closer is assigned to this sale.'], 400);
            }

            $closer = $saleMasters->net_epc;
            if (! $closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The net epc value is missing. Kindly ensure that the gross account value is present to this sale.'], 400);
            }

            $closer = $saleMasters->kw;
            if (! $closer) {
                return response()->json(['status' => false, 'Message' => 'Apologies, The kw value is missing. Kindly ensure that the gross account value is present to this sale.'], 400);
            }

            (new ApiMissingDataController)->subroutine_process($pid);
        }

        return response()->json(['status' => true, 'Message' => 'Recalculate Sale Data successfully'], 200);
    }

    public function updateCommissionFrequency_old(Request $request)
    {
        // $userCommission = UserCommission::where('pay_period_from','>','2023-12-05')->where('status', 1)->groupBy('pid')->get();
        $userCommission = UserCommission::where('pay_period_from', '2023-12-26')->where('status', 1)->groupBy('pid')->get();
        // return $userCommission;
        if (count($userCommission) > 0) {

            foreach ($userCommission as $key => $value) {
                $m1Commiss = UserCommission::where('pid', $value->pid)->where(['amount_type' => 'm1', 'status' => 1])->get();
                if (count($m1Commiss) > 0) {
                    foreach ($m1Commiss as $key1 => $m1Commis) {
                        $payFrequency = $this->payFrequency($m1Commis->date, $m1Commis->position_id, $m1Commis->user_id);
                        $updatedata = ['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to];

                        $m1PayFrom = $m1Commis->pay_period_from;
                        $m1PayTo = $m1Commis->pay_period_to;

                        UserCommission::where(['id' => $m1Commis->id, 'amount_type' => 'm1', 'status' => 1])->update($updatedata);
                        PayRoll::where(['user_id' => $m1Commis->user_id, 'pay_period_from' => $m1PayFrom, 'pay_period_to' => $m1PayTo, 'status' => 1])->update($updatedata);

                    }
                }

                $m2Commiss = UserCommission::where('pid', $value->pid)->where(['amount_type' => 'm2', 'status' => 1])->get();
                if (count($m2Commiss) > 0) {
                    foreach ($m2Commiss as $key2 => $m2Commis) {

                        $payFrequency = $this->payFrequency($m2Commis->date, $m2Commis->position_id, $m2Commis->user_id);
                        $updatedata = ['pay_period_from' => $payFrequency->pay_period_from, 'pay_period_to' => $payFrequency->pay_period_to];

                        $m2PayFrom = $m2Commis->pay_period_from;
                        $m2PayTo = $m2Commis->pay_period_to;
                        UserCommission::where(['pid' => $value->pid, 'user_id' => $m2Commis->user_id, 'amount_type' => 'm2', 'status' => 1, 'pay_period_from' => $m2PayFrom, 'pay_period_to' => $m2PayTo])->update($updatedata);
                        PayRoll::where(['user_id' => $m2Commis->user_id, 'pay_period_from' => $m2PayFrom, 'pay_period_to' => $m2PayTo, 'status' => 1])->update($updatedata);

                        $overrrides = UserOverrides::where(['sale_user_id' => $m2Commis->user_id, 'pid' => $value->pid, 'overrides_settlement_type' => 'during_m2', 'status' => 1])->get();
                        if (count($overrrides) > 0) {
                            foreach ($overrrides as $key3 => $over) {
                                $overridePayFrom = $over->pay_period_from;
                                $overridePayTo = $over->pay_period_to;
                                // $updatedata = ['pay_period_from'=> $payFrequency->pay_period_from, 'pay_period_to'=> $payFrequency->pay_period_to];
                                $update = UserOverrides::where(['id' => $over->id])->update($updatedata);

                                $payRoll = Payroll::where(['user_id' => $over->user_id, 'pay_period_from' => $overridePayFrom, 'pay_period_to' => $overridePayTo])->whereIn('status', [1, 2])->first();
                                if ($payRoll) {

                                    $updatePay = PayRoll::where(['user_id' => $over->user_id, 'pay_period_from' => $overridePayFrom, 'pay_period_to' => $overridePayTo, 'status' => 1])->update($updatedata);

                                }
                            }
                        }
                    }
                }

                $alertCenter = $this->closedPayrollData($value->pid);

            }
        }

        return response()->json(['status' => true, 'Message' => 'Update Frequency Wise Sale Data Successfully'], 200);

    }
}
