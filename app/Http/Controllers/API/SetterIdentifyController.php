<?php

namespace App\Http\Controllers\API;

use App\Core\Traits\SetterSubroutineListTrait;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use App\Models\LegacyApiNullData;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SetterIdentifyAlert;
use App\Models\User;
use App\Models\UserReconciliationWithholding;
use App\Services\SalesCalculationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetterIdentifyController extends Controller
{
    use SetterSubroutineListTrait;

    public function __construct(Request $request)
    {
        // $user = auth('api')->user();
    }

    public function getSetterDropdown(): JsonResponse
    {
        $data = User::with('office', 'additionalRedline', 'reconciliations', 'upfront', 'positionpayfrequencies')->where('dismiss', 0)->where('position_id', 3)->orWhere('self_gen_type', 3)->where('dismiss', 0)->get();

        return response()->json([
            'ApiName' => 'setter_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

    }

    public function getSetterMissingByPid(): JsonResponse
    {
        $user = auth('api')->user();
        $email = $user->email;
        $closer = SetterIdentifyAlert::where('sales_rep_email', $email)->get();

        return response()->json([
            'ApiName' => 'pid_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $closer,
        ], 200);
    }

    public function updateSetterByPid(Request $request)
    {
        $pid = $request->pid;
        $setters = $request->setters;

        $UpdateData = SaleMasterProcess::where('pid', $pid)->first();
        if (empty($UpdateData->closer1_id)) {
            return response()->json([
                'ApiName' => 'update_setter_id',
                'status' => true,
                'message' => 'Closer not available for this PID.',
            ], 400);

        }
        if (count($setters) > 1) {
            $UpdateData->setter1_id = $setters[0];
            $UpdateData->setter2_id = $setters[1];
        } else {
            $UpdateData->setter1_id = $setters[0];
        }
        $UpdateData->save();

        if ($setters[0]) {
            $legacySetterId = LegacyApiNullData::where('pid', $pid)->where('setter_id', null)->update(['setter_id' => $setters[0]]);
        }

        // subroutine  process
        $data = $this->subroutine_process($pid);

        // return $data;
        return response()->json([
            'ApiName' => 'update_setter_id',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $UpdateData,
        ], 200);
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
            if ($checked->salesMasterProcess->mark_account_status_id != 1 || $checked->salesMasterProcess->mark_account_status_id != 6) {
                // 'Have any payments already been issued? ';
                if ($m1_paid_status == 4 || $m2_paid_status == 8) {
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
            // check Is there an M1 Date?
            if ($m1_date) {
                // check  Has M1 already been paid?

                if ($m1_paid_status == 4) {

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
